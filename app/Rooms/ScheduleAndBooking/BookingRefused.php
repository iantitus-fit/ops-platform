<?php

namespace App\Rooms\ScheduleAndBooking;

use DomainException;

/**
 * A booking the platform will not make, in plain words the owner reads. When the refusal is about one thing the owner
 * typed, `field` names it (the form field's name) so the screen can show the sentence beside that field; a refusal about
 * the whole booking has no field and the screen shows it as a notice.
 */
class BookingRefused extends DomainException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    /**
     * The room is not in the live version, so nothing may be booked (D-03).
     */
    public static function roomOff(): self
    {
        return new self('The schedule and booking room is off.');
    }

    /**
     * The service is not in the business folder (any more).
     */
    public static function noSuchService(): self
    {
        return new self('That service is no longer in the business folder.', 'service');
    }

    /**
     * The start is now or has passed.
     */
    public static function pastStart(): self
    {
        return new self('Pick a time that has not passed.', 'starts_at');
    }

    /**
     * The time overlaps a booked appointment (with its drive buffer) or a block, and only one job may run at a time
     * (D-07).
     */
    public static function taken(): self
    {
        return new self('That time is taken by another booking or blocked time. Pick another time.', 'starts_at');
    }

    /**
     * The chosen job is closed, belongs to another customer or is gone (D-25).
     */
    public static function projectNotOpen(): self
    {
        return new self('That job is closed. Pick an open job or a new one.', 'project');
    }

    /**
     * The visit note is longer than the 200 characters a note may hold.
     */
    public static function noteTooLong(): self
    {
        return new self('A note can be at most 200 characters.', 'note');
    }

    /**
     * Another booking holds the booking lock and did not let go in time (D-07).
     */
    public static function busy(): self
    {
        return new self('Another booking is being made. Try again.');
    }
}
