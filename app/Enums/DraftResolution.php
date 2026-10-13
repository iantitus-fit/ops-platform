<?php

namespace App\Enums;

/**
 * How a held draft ended. A draft is never deleted: it is kept with the way it ended, so the owner's choices stay
 * on the record as labeled data (D-28).
 */
enum DraftResolution: string
{
    case Approved = 'approved';
    case Edited = 'edited';
    case Discarded = 'discarded';
    case Replaced = 'replaced';
}
