<?php

namespace App\Enums;

/**
 * What the send rule decided about a reply: let it go out, or keep it as a draft for the owner.
 */
enum GateOutcome: string
{
    case Send = 'send';
    case Hold = 'hold';
}
