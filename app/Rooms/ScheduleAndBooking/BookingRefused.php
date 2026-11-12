<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Enums\BookingRefusal;
use DomainException;

/**
 * A booking the platform will not make, in plain words the owner reads. When the refusal is about one thing the owner
 * typed, `field` names it (the form field's name) so the screen can show the sentence beside that field; a refusal about
 * the whole booking has no field and the screen shows it as a notice. `reason` says why it was refused, set by each named
 * constructor, so a caller decides by the reason and never by reading the sentence (WR-04).
 */
class BookingRefused extends DomainException
{
    public function __construct(public readonly BookingRefusal $reason, string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    /**
     * The room is not in the live version, so nothing may be booked (D-03).
     */
    public static function roomOff(): self
    {
        return new self(BookingRefusal::RoomOff, 'The schedule and booking room is off.');
    }

    /**
     * The service is not in the business folder (any more).
     */
    public static function noSuchService(): self
    {
        return new self(BookingRefusal::NoSuchService, 'That service is no longer in the business folder.', 'service');
    }

    /**
     * The start is now or has passed.
     */
    public static function pastStart(): self
    {
        return new self(BookingRefusal::PastStart, 'Pick a time that has not passed.', 'starts_at');
    }

    /**
     * The time overlaps a booked appointment (with its drive buffer) or a block, and only one job may run at a time
     * (D-07).
     */
    public static function taken(): self
    {
        return new self(BookingRefusal::Taken, 'That time is taken by another booking or blocked time. Pick another time.', 'starts_at');
    }

    /**
     * The chosen job is closed, belongs to another customer or is gone (D-25).
     */
    public static function projectNotOpen(): self
    {
        return new self(BookingRefusal::ProjectNotOpen, 'That job is closed. Pick an open job or a new one.', 'project');
    }

    /**
     * The visit note is longer than the 200 characters a note may hold.
     */
    public static function noteTooLong(): self
    {
        return new self(BookingRefusal::NoteTooLong, 'A note can be at most 200 characters.', 'note');
    }

    /**
     * The visit has not started, so it cannot be marked done yet (D-14).
     */
    public static function notStarted(): self
    {
        return new self(BookingRefusal::NotStarted, 'A visit can be marked done only once it has started.');
    }

    /**
     * The visit is cancelled or done, so its note is no longer the owner's to change (D-18).
     */
    public static function notBooked(): self
    {
        return new self(BookingRefusal::NotBooked, 'Only a booked visit can have its note changed.');
    }

    /**
     * A block's end is not after its start (D-16).
     */
    public static function endBeforeStart(): self
    {
        return new self(BookingRefusal::EndBeforeStart, 'The end must be after the start.', 'ends_at');
    }

    /**
     * A block's note is longer than the 100 characters a block note may hold (D-16).
     */
    public static function blockNoteTooLong(): self
    {
        return new self(BookingRefusal::BlockNoteTooLong, 'A block note can be at most 100 characters.', 'note');
    }

    /**
     * The block would sit over a booked visit, so the visit has to be cancelled first (decided gap 1). The sentence
     * names the booking so the owner knows which one to cancel.
     */
    public static function blockOverBooking(string $name, string $day, string $time): self
    {
        return new self(BookingRefusal::BlockOverBooking, "{$name} is booked on {$day} at {$time}. Cancel that visit first, or pick another time.", 'starts_at');
    }

    /**
     * Another booking holds the booking lock and did not let go in time (D-07).
     */
    public static function busy(): self
    {
        return new self(BookingRefusal::Busy, 'Another booking is being made. Try again.');
    }

    /**
     * The booking link cannot book a time now: it was used, revoked, replaced, expired, or its service is no longer
     * bookable online (D-02, D-13).
     */
    public static function linkClosed(): self
    {
        return new self(BookingRefusal::LinkClosed, 'This link cannot book a time now.');
    }
}
