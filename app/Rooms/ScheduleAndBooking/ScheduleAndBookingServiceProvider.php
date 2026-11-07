<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\BookingSend;
use App\Models\ScheduleBlock;
use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;
use App\Rooms\TimelineEntryType;

/**
 * The room where the business's visits are booked and kept (ROOM-12, D-35). It has no outside system in v2.0: its
 * contract says connector none, because a calendar elsewhere such as Google Calendar is backlog 999.4. It is optional,
 * so a business may leave rooms/schedule_and_booking/ out (neverLeftOutBecause is null), and it takes no `needs` field,
 * which is closed as not needed in v2.0.
 *
 * It owns one file, the booking file, and three tables: appointments, booking_links and booking_sends. Schedule blocks
 * share the appointments table (A3), so they add no table of their own. Every entry on a customer's timeline uses
 * platform words only (06 D-05), and the owner's note is never put in a trusted summary.
 *
 * The appointment actions write through RecordRoomEntry, so a room that is off writes nothing (04 D-08). The room adds
 * no agent action of its own; its writes come in later plans through named actions (D-15). register() and boot() read
 * nothing from ActiveConfig, the database or Filament.
 */
class ScheduleAndBookingServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'schedule_and_booking',
            label: 'schedule and booking',
            fileTypes: [new BookingFileType],
            sections: [ScheduleSection::class],
            tables: ['appointments', 'booking_links', 'booking_sends'],
            timelineEntries: [
                new TimelineEntryType('visit_booked', 'Visit booked', trusted: true),
                new TimelineEntryType('visit_cancelled', 'Visit cancelled', trusted: true),
                new TimelineEntryType('visit_done', 'Visit done', trusted: true),
                new TimelineEntryType('visit_note_changed', 'Visit note changed', trusted: true),
                new TimelineEntryType('booking_text_not_sent', 'Booking text not sent', trusted: true),
                new TimelineEntryType('automatic_texts_changed', 'Automatic texts changed', trusted: true),
            ],
            morphMap: [
                'appointment' => Appointment::class,
                'schedule_block' => ScheduleBlock::class,
                'booking_link' => BookingLink::class,
                'booking_send' => BookingSend::class,
            ],
        );
    }
}
