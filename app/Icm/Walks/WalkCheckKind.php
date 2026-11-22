<?php

namespace App\Icm\Walks;

/**
 * The closed list of things a walk file may check (Phase 10 D-09). An owner writes a check as one of these phrases
 * under expect, and nothing else is accepted. Some may follow a customer text, some follow a booking, a wait or the end
 * of the walk, and the owner-alerted check may sit in either place.
 */
enum WalkCheckKind: string
{
    case SentOnItsOwn = 'sent_on_its_own';
    case HeldForTheOwner = 'held_for_the_owner';
    case HandedToTheOwner = 'handed_to_the_owner';
    case Labeled = 'labeled';
    case CarriesPriceSentence = 'carries_price_sentence';
    case CarriesBookingLink = 'carries_booking_link';
    case CarriesVisitLine = 'carries_visit_line';
    case OwnerAlerted = 'owner_alerted';
    case Booked = 'booked';
    case ReminderQueued = 'reminder_queued';
    case CardUpdated = 'card_updated';
    case RecordsUpdated = 'records_updated';

    /**
     * The phrase as an owner writes it. The two that take a word after them show it in angle brackets.
     */
    public function phrase(): string
    {
        return match ($this) {
            self::SentOnItsOwn => 'sent on its own',
            self::HeldForTheOwner => 'held for the owner',
            self::HandedToTheOwner => 'handed to the owner',
            self::Labeled => 'labeled <label>',
            self::CarriesPriceSentence => "carries the owner's price sentence for <service slug>",
            self::CarriesBookingLink => 'carries the booking link',
            self::CarriesVisitLine => 'carries the visit line',
            self::OwnerAlerted => 'owner alerted',
            self::Booked => 'booked',
            self::ReminderQueued => 'reminder queued',
            self::CardUpdated => 'card updated',
            self::RecordsUpdated => 'records updated',
        };
    }

    /**
     * Whether the phrase ends in a word the owner fills in: a reply label or a service slug.
     */
    public function takesWord(): bool
    {
        return $this === self::Labeled || $this === self::CarriesPriceSentence;
    }

    /**
     * Whether the check may sit under a customer text.
     */
    public function afterSays(): bool
    {
        return match ($this) {
            self::Booked, self::ReminderQueued, self::CardUpdated, self::RecordsUpdated => false,
            default => true,
        };
    }

    /**
     * Whether the check may sit under a booking or a wait, or at the end of the walk.
     */
    public function afterOutcome(): bool
    {
        return match ($this) {
            self::OwnerAlerted, self::Booked, self::ReminderQueued, self::CardUpdated, self::RecordsUpdated => true,
            default => false,
        };
    }
}
