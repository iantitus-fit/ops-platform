<?php

namespace App\Rooms\OwnerIntelligence;

use App\Channels\TextKeywords;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Time to booked (BOOK-06): how long a contact took to book, from their first real inbound message to their first
 * booking ever. It only reads and stores nothing (D-04): every number is worked out on read from the two recorded
 * facts, in exactly one SELECT, for one contact and for the whole summary alike.
 *
 * The clock starts at the contact's earliest inbound message across all of their conversations (D-01) and stops at the
 * earliest booked_at across all of their appointments, whichever project and whatever the visit date, a page booking or
 * a booking by hand, even one cancelled later (D-02). The elapsed time is wall-clock seconds worked out from Unix
 * timestamps, and a contact whose first message is not strictly before the first booking has no number (D-03).
 *
 * The work is in PHP and not in SQL because keywords are never matched in SQL (TextKeywords) and SQLite has no median.
 */
class TimeToBooked
{
    /**
     * What the summary line says when no contact first booked in the window.
     */
    public const string EMPTY_LINE = 'No new customers booked in the last 30 days.';

    /**
     * The window, in days: today and the 29 days before it (A2).
     */
    public const int WINDOW_DAYS = 30;

    public function __construct(private readonly ActiveConfig $config) {}

    /**
     * The seconds from this contact's first real message to their first booking, or null when they have no number: an
     * unknown contact, no inbound message, never booked, or a first message that is not before the first booking.
     */
    public function forContact(int $contactId): ?int
    {
        return $this->secondsByContact(null, $contactId)[$contactId] ?? null;
    }

    /**
     * How many new customers booked in the last 30 days and the median seconds they took (D-07, A2).
     *
     * A new customer here is a contact whose first booking ever fell in the window, counted in the business zone from
     * business/profile.md: today and the 29 days before it, from the start of today, so the window keeps local midnight
     * across a daylight change. Only contacts with a number are counted (D-03). The median is the middle value, or the
     * mean of the two middle values for an even count, and is not rounded to a minute here: DurationText rounds when it
     * formats, so a median under a minute reads "under a minute". Sorting happens in PHP because SQLite has no median.
     *
     * @return array{count: int, median_seconds: int|null}
     */
    public function summary(): array
    {
        $cutoff = CarbonImmutable::now($this->config->displayTimezone())
            ->startOfDay()
            ->subDays(self::WINDOW_DAYS - 1)
            ->utc();

        $seconds = array_values($this->secondsByContact($cutoff, null));
        sort($seconds);
        $count = count($seconds);

        if ($count === 0) {
            return ['count' => 0, 'median_seconds' => null];
        }

        $middle = intdiv($count, 2);

        return [
            'count' => $count,
            'median_seconds' => $count % 2 === 1
                ? $seconds[$middle]
                : (int) round(($seconds[$middle - 1] + $seconds[$middle]) / 2),
        ];
    }

    /**
     * The one sentence for the median (D-07), built here so the dashboard section and records/time-to-booked.md read
     * alike: "Half of your 12 new customers in the last 30 days booked within 42 minutes.", "Your 1 new customer in the
     * last 30 days booked within 42 minutes.", "... booked in under a minute." for a median under a minute, and
     * EMPTY_LINE when no new customer booked in the window.
     */
    public function summaryLine(): string
    {
        $summary = $this->summary();

        if ($summary['count'] === 0 || $summary['median_seconds'] === null) {
            return self::EMPTY_LINE;
        }

        $time = DurationText::format($summary['median_seconds']);
        $booked = $time === 'under a minute' ? 'booked in under a minute' : 'booked within '.$time;

        return $summary['count'] === 1
            ? 'Your 1 new customer in the last '.self::WINDOW_DAYS.' days '.$booked.'.'
            : 'Half of your '.$summary['count'].' new customers in the last '.self::WINDOW_DAYS.' days '.$booked.'.';
    }

    /**
     * The seconds to booked for every contact that has a number, in one SELECT.
     *
     * A grouped Appointment query gives each contact's first booking as a small table inside the query, and joinSub()
     * joins every inbound message that came before it, so one read returns every contact's candidate messages however
     * many contacts there are. The Appointment kind scope keeps schedule blocks out, and a block has no contact anyway.
     * The cutoff keeps only contacts whose first booking is at or after it; the contact id keeps only that contact.
     *
     * @param  CarbonImmutable|null  $firstBookedSinceUtc  keep contacts first booked at or after this moment
     * @param  int|null  $contactId  keep only this contact
     * @return array<int, int> seconds by contact id
     */
    private function secondsByContact(?CarbonImmutable $firstBookedSinceUtc, ?int $contactId): array
    {
        $firstBookings = Appointment::query()
            ->whereNotNull('contact_id')
            ->selectRaw('contact_id, min(booked_at) as first_booked_at')
            ->groupBy('contact_id');

        if ($firstBookedSinceUtc !== null) {
            $firstBookings->havingRaw('min(booked_at) >= ?', [$firstBookedSinceUtc->format('Y-m-d H:i:s')]);
        }

        if ($contactId !== null) {
            $firstBookings->where('contact_id', $contactId);
        }

        $rows = Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->joinSub($firstBookings, 'first_booking', 'first_booking.contact_id', '=', 'conversations.contact_id')
            ->where('messages.direction', MessageDirection::In->value)
            ->whereColumn('messages.created_at', '<', 'first_booking.first_booked_at')
            ->orderBy('conversations.contact_id')
            ->orderBy('messages.created_at')
            ->orderBy('messages.id')
            ->select([
                'conversations.contact_id',
                'messages.id',
                'messages.channel',
                'messages.body',
                'messages.meta',
                'messages.created_at',
                'first_booking.first_booked_at',
            ])
            ->get();

        return $this->firstRealMessageSeconds($rows);
    }

    /**
     * Walk each contact's messages in order and time the first real one.
     *
     * This mirrors RecordInbound::keywordIn(), which decided at record time whether a text was a keyword (D-01, 07 D-23):
     * only an SMS is matched, by TextKeywords::actionFor() with the provider's own opt-out word, the body and whether the
     * contact is opted out at that point. Each contact starts not opted out, a STOP opts them out and a START opts them
     * back in, so YES is a keyword only while they are opted out. A web form is never a keyword, even one that reads
     * STOP. The first message that is not a keyword starts the clock, and a contact whose every message was a keyword
     * has no entry. The elapsed time is the difference of two Unix timestamps, and 0 or less gives no entry (A4).
     *
     * @param  Collection<int, Message>  $rows  messages before each contact's first booking, in contact then time order
     * @return array<int, int> seconds by contact id
     */
    private function firstRealMessageSeconds(Collection $rows): array
    {
        $seconds = [];
        $optedOut = [];

        foreach ($rows as $row) {
            $contact = (int) $row->contact_id;

            if (isset($seconds[$contact])) {
                continue;
            }

            if ($row->channel === MessageChannel::Sms) {
                $action = TextKeywords::actionFor($row->meta['opt_out_type'] ?? null, (string) $row->body, $optedOut[$contact] ?? false);

                if ($action !== null) {
                    $optedOut[$contact] = match ($action) {
                        'stop' => true,
                        'start' => false,
                        default => $optedOut[$contact] ?? false,
                    };

                    continue;
                }
            }

            $elapsed = CarbonImmutable::parse($row->first_booked_at, 'UTC')->getTimestamp() - $row->created_at->getTimestamp();

            if ($elapsed > 0) {
                $seconds[$contact] = $elapsed;
            }
        }

        return $seconds;
    }
}
