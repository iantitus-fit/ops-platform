<?php

namespace App\Rooms\OwnerIntelligence;

use App\Enums\MessageDirection;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\Message;
use Carbon\CarbonImmutable;

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

        $seconds = [];

        foreach ($rows as $row) {
            $contact = (int) $row->contact_id;

            if (isset($seconds[$contact])) {
                continue;
            }

            $elapsed = CarbonImmutable::parse($row->first_booked_at, 'UTC')->getTimestamp() - $row->created_at->getTimestamp();

            if ($elapsed > 0) {
                $seconds[$contact] = $elapsed;
            }
        }

        return $seconds;
    }
}
