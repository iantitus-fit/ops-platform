<?php

namespace App\Enums;

/**
 * Why the assistant stepped back from a conversation (D-23). Every reason uses the same paused state and the same
 * hand back.
 */
enum PausedReason: string
{
    case TakenOver = 'taken_over';
    case AskedForPerson = 'asked_for_person';
    case OwnerReplied = 'owner_replied';
    case LifeSafety = 'life_safety';
    case ReplyCap = 'reply_cap';

    /**
     * The opening line of the banner on a paused conversation, or null when the banner needs no lead because the
     * owner already knows why: they took over, or they replied themselves.
     */
    public function bannerLead(): ?string
    {
        return match ($this) {
            self::TakenOver, self::OwnerReplied => null,
            self::AskedForPerson => 'The customer asked for a person.',
            self::LifeSafety => 'A safety message went out.',
            self::ReplyCap => 'The reply limit was reached.',
        };
    }

    /**
     * The line the timeline keeps when the assistant paused for this reason.
     */
    public function timelineSummary(): string
    {
        return match ($this) {
            self::TakenOver => 'Owner took over the conversation',
            self::AskedForPerson => 'Customer asked for a person; the assistant stepped back',
            self::OwnerReplied => 'Owner replied; the assistant stepped back',
            self::LifeSafety => 'Safety message sent; the assistant stepped back',
            self::ReplyCap => 'Reply limit reached; the assistant stepped back',
        };
    }
}
