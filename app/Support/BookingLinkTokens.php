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

    /**
     * Whether the text names a booking address at all: '/book/' in any letter case, so the hidden form, an address with
     * a token and a full URL all count.
     *
     * This is the front desk's rule (D-12, Ian 2026-11-07). Stored texts and the card write a sent link as
     * /book/[link], the model reads them, and an address the model writes would reach the customer broken, so any
     * '/book/' in the model's words holds the reply. It errs toward holding, and it is never run on the owner's lines or
     * on the link line code appends.
     */
    public static function namesBookingAddress(string $text): bool
    {
        return stripos($text, '/book/') !== false;
    }

    /**
     * Whether the text holds the hidden form, '/book/[link]' in any letter case. The hidden form never opens for a
     * customer, so the chokepoint refuses to send it; an address with a real token is not this form.
     */
    public static function holdsHiddenAddress(string $text): bool
    {
        return stripos($text, '/book/'.self::HIDDEN) !== false;
    }
}
