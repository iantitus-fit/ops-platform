<?php

namespace App\Enums;

/**
 * What an automated message is for. Owner replies and customer texts have no kind.
 */
enum MessageKind: string
{
    case AgentReply = 'agent_reply';
    case HoldingReply = 'holding_reply';
    case HandoffReply = 'handoff_reply';
    case LifeSafety = 'life_safety';

    /** The owner's code-assembled answer plus the partial holding line for what is still open (D-30). */
    case PartialHolding = 'partial_holding';

    /** The owner's complaint reply, alone or leading an answer (D-30). */
    case ComplaintHolding = 'complaint_holding';

    /** The owner's discount reply (D-30). */
    case DiscountHolding = 'discount_holding';

    /** The check-in line that tells the customer the owner is still working on it (D-30). */
    case CheckInReply = 'check_in_reply';

    /** The line that confirms extra information the customer added while a draft waits (D-30). */
    case AddedInfoReply = 'added_info_reply';

    /** Owner price or offer sentences sent by code, with or without the model's own words (D-21, D-30). */
    case CodeAnswer = 'code_answer';

    /**
     * A text the platform starts about a booked appointment, the confirmation or a reminder. Code writes it from an
     * owner line in the booking file, never the model's words. It goes out with no reading of a customer text, only
     * under the conditions OutboundSender checks (D-26, Ian's amendment of 02.1 D-49). It starts no wait and is not
     * built from owner-written sentences.
     */
    case BookingText = 'booking_text';

    /**
     * The kinds that start a wait: the customer was told the owner will answer, so no second full holding reply goes
     * out while that question is still open (D-14, D-30).
     *
     * @return list<self>
     */
    public static function startsWait(): array
    {
        return [self::HoldingReply, self::PartialHolding, self::ComplaintHolding, self::DiscountHolding];
    }

    /**
     * The kinds of text that code puts together from owner-written sentences and fixed lines. Whether the model's own
     * words are inside is recorded in the message's meta.parts.
     *
     * @return list<self>
     */
    public static function assembledByCode(): array
    {
        return [
            self::PartialHolding,
            self::ComplaintHolding,
            self::DiscountHolding,
            self::CheckInReply,
            self::AddedInfoReply,
            self::CodeAnswer,
        ];
    }

    /**
     * The kinds the reply cap leaves out. The cap counts texts that answer a customer text (02 D-52 as amended by 10
     * D-20). The safety line never counts, so it can neither use up the cap nor be stopped; booking confirmations and
     * reminders do not, because a booking or the clock starts them and they cannot ping-pong with a customer.
     *
     * @return list<self>
     */
    public static function notCountedByReplyCap(): array
    {
        return [self::LifeSafety, self::BookingText];
    }
}
