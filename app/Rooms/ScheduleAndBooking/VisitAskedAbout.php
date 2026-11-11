<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Enums\ReplyLabel;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\Contact;

/**
 * The one place that holds D-34's "asked about a booked visit" condition and D-39 (as amended)'s visit line, so the front
 * desk's send (RunFrontDesk) and the owner's approval of a held draft (SendOwnerReply, plan 08-25) ask the same question
 * and cannot drift apart, the way ServiceToBook holds D-01 and D-02 for both paths.
 *
 * A text is about scheduling when the reading's intent is the owner's `scheduling` intent or the reply is labeled
 * scheduling. The visit is the contact's earliest booked visit still ahead. It is read through the contact's own
 * appointments() relation, so another customer's visit is never found, and this class writes nothing (D-04): it uses
 * query reads only.
 */
class VisitAskedAbout
{
    public function __construct(private readonly ActiveConfig $config) {}

    /**
     * Whether a text is about scheduling: the reading's intent is `scheduling`, or the reply is labeled scheduling.
     */
    public static function aboutScheduling(?string $readingIntent, ?ReplyLabel $label): bool
    {
        return $readingIntent === 'scheduling' || $label === ReplyLabel::Scheduling;
    }

    /**
     * The contact's earliest booked visit still ahead, when the text is about scheduling; otherwise null.
     */
    public function find(Contact $contact, ?string $readingIntent, ?ReplyLabel $label): ?Appointment
    {
        if (! self::aboutScheduling($readingIntent, $label)) {
            return null;
        }

        return $contact->appointments()->booked()->ahead()->orderBy('starts_at')->first();
    }

    /**
     * The visit as the owner is told about it in a change-request alert: "AC repair, Thursday, November 5 at 10:00 AM",
     * in the business zone (D-34).
     */
    public function describe(Appointment $visit): string
    {
        $zone = $this->config->displayTimezone();

        return $visit->service_name.', '.BookingSettings::dayText($visit->starts_at, $zone).' at '.BookingSettings::timeText($visit->starts_at, $zone);
    }

    /**
     * The owner's visit line for the visit, ready to put in a text, or null when none may be built: the room is off, the
     * folder has no booking file, or the live version has no visit_line (D-03, D-39 amended).
     */
    public function line(Appointment $visit): ?string
    {
        return BookingSettings::fromConfig($this->config)?->renderVisit($visit->service_name, $visit->starts_at, $this->config->displayTimezone());
    }
}
