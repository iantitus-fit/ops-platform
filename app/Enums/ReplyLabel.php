<?php

namespace App\Enums;

/**
 * The fixed vocabulary for what a reply is about. The business folder's rules/sending.md sorts these into
 * the replies that may go out on their own and the ones that always wait for the owner (D-08).
 */
enum ReplyLabel: string
{
    case IntakeQuestion = 'intake_question';
    case Scheduling = 'scheduling';
    case FaqAnswer = 'faq_answer';
    case Price = 'price';
    case Promise = 'promise';
    case Discount = 'discount';
    case Complaint = 'complaint';
    case Other = 'other';

    /**
     * The only labels a folder may list under send_on_own.
     *
     * @return list<self>
     */
    public static function sendOnOwnAllowed(): array
    {
        return [self::IntakeQuestion, self::Scheduling, self::FaqAnswer];
    }

    /**
     * The hard floor: labels that never go out without the owner, whatever the folder says (D-08).
     *
     * @return list<self>
     */
    public static function neverOnItsOwn(): array
    {
        return [self::Price, self::Promise, self::Discount, self::Complaint];
    }

    /**
     * Every label as the text an owner writes in the folder.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
