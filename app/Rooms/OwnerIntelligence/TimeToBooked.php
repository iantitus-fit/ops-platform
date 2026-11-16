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
