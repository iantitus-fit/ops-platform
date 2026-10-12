<?php

namespace App\Enums;

/**
 * Who asked for a text to be sent, which decides how the one outbound path treats it: an owner waits for
 * no one, an automated text waits briefly for the owner's send, and the safety line is let through
 * checks the others must pass.
 */
enum SendOrigin: string
{
    case Owner = 'owner';
    case Automated = 'automated';
    case LifeSafety = 'life_safety';
}
