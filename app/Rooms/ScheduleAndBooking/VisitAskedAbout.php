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
    /**
     * The reason told to the owner when the contact has no booked visit still ahead (D-45 analog).
     */
    public const string NO_VISIT_AHEAD = 'no visit is booked ahead';

    /**
     * The reason told to the owner when the line that would go now differs from the line the approve step showed. That
     * covers the visit moving, another visit becoming the one asked about, and the line's own words changing in the
     * booking file (WR-09, 08 IN-13, Phase 9 D-26). A draft already sent keeps the words stored with it.
     */
    public const string VISIT_CHANGED = 'the line changed after it was shown';

    /**
     * The reason told to the owner when booking is on but the live booking file was synced before visit_line existed, so
     * the owner knows the fix is a line in their file (WR-10).
     */
    public const string NO_VISIT_LINE = 'the booking file has no visit_line yet, so add it and sync again';

    public function __construct(private readonly ActiveConfig $config) {}

    /**
     * Whether a text is about scheduling: the reading's intent is `scheduling`, or the reply is labeled scheduling.
     */
    public static function aboutScheduling(?string $readingIntent, ?ReplyLabel $label): bool
    {
        return $readingIntent === 'scheduling' || $label === ReplyLabel::Scheduling;
    }

    /**
     * The contact's earliest booked visit still ahead, when the text is about scheduling; otherwise null. Two visits at
     * the same minute go by id, the order CustomerCard uses, so the line, the owner's alert and the card the model reads
     * name the same visit (IN-11).
     */
    public function find(Contact $contact, ?string $readingIntent, ?ReplyLabel $label): ?Appointment
    {
        if (! self::aboutScheduling($readingIntent, $label)) {
            return null;
        }

        return $contact->appointments()->booked()->ahead()->orderBy('starts_at')->orderBy('id')->first();
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

    /**
     * Why a visit line cannot ride now, in words for the owner, or null when it can (D-45 analog). The checks run in
     * this order and the first failure wins: online booking is off (the room is off or there is no booking file), then
     * the live booking file has no visit_line because it was synced before the field existed (WR-10), then the contact
     * has no booked visit ahead. The label and the reading are not a reason: they are fixed on the draft, and a line is
     * only drawn when they hold. The screen draws its line only when this is null, and the send asks it again, so the two
     * agree.
     */
    public function whyNoLine(Contact $contact): ?string
    {
        $settings = BookingSettings::fromConfig($this->config);

        if ($settings === null) {
            return ServiceToBook::BOOKING_OFF;
        }

        if ($settings->visitLine === null) {
            return self::NO_VISIT_LINE;
        }

        if (! $contact->appointments()->booked()->ahead()->exists()) {
            return self::NO_VISIT_AHEAD;
        }

        return null;
    }
}
