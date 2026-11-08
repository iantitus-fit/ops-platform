<?php

namespace App\Support;

/**
 * The one rule that writes a booking address where it is kept (D-12).
 *
 * D-12 says a copy of the database or of the business folder holds no working link. The address is /book/{token}
 * (routes/web.php) and the token is the 64 hex characters MintBookingLink makes, which the leak test pins with
 * route('booking.show', ...). hide() works by pattern, so it also covers a token that reached a string any other way (a
 * customer's paste, an exception message) without the token being carried around. A bare 64-hex string, such as the
 * stored token_hash, is left alone, because only an address works.
 */
final class BookingLinkTokens
{
    /**
     * What stands in the address where the token was.
     */
    public const string HIDDEN = '[link]';

    /**
     * Write every booking address in the text as /book/[link]: '/book/' followed by 64 or more hex characters, in either
     * letter case. Every other character comes back exactly as it was.
     */
    public static function hide(string $text): string
    {
        return preg_replace('~(/book/)[0-9a-f]{64,}~i', '$1'.self::HIDDEN, $text) ?? $text;
    }
}
