<?php

namespace App\Enums;

/**
 * The events that can alert the owner. The business folder's owner_alert lists the ones that alert right away (D-09).
 */
enum AlertReason: string
{
    case DraftWaiting = 'draft_waiting';
    case Complaint = 'complaint';
    case AskedForPerson = 'asked_for_person';
    case LifeSafety = 'life_safety';
    case PausedMessage = 'paused_message';
    case OptedOutMessage = 'opted_out_message';
    case FrontDeskFailed = 'front_desk_failed';
    case ReplyCap = 'reply_cap';
    case CheckIn = 'check_in';
    case SafetyCheckDown = 'safety_check_down';
    case SafetyCheckMissing = 'safety_check_missing';

    /**
     * A customer booked a visit on the booking page (Phase 8 D-33). Its text names the visit and quotes no customer text.
     */
    case AppointmentBooked = 'appointment_booked';

    /**
     * A customer with a booked visit ahead texted about scheduling (Phase 8 D-34). It takes the place of draft_waiting
     * and check_in for that text and gives way to complaint, asked_for_person, life_safety, paused_message and
     * front_desk_failed.
     */
    case ChangeRequest = 'change_request';

    /**
     * Every event as the text an owner writes in the folder.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The event as the owner reads it in an alert or on a screen.
     */
    public function plainWords(): string
    {
        return match ($this) {
            self::DraftWaiting => 'Draft waiting',
            self::Complaint => 'Complaint',
            self::AskedForPerson => 'Asked for a person',
            self::LifeSafety => 'Safety message sent',
            self::PausedMessage => "New message while you're handling it",
            self::OptedOutMessage => 'Opted-out customer texted',
            self::FrontDeskFailed => 'Front desk could not answer',
            self::ReplyCap => 'Reply limit reached',
            self::CheckIn => 'Customer checked in',
            self::SafetyCheckDown => 'Safety check is down',
            self::SafetyCheckMissing => 'Safety check did not run',
            self::AppointmentBooked => 'Visit booked',
            self::ChangeRequest => 'Booked customer asked about the visit',
        };
    }
}
