<?php

namespace App\Rooms\Money;

use App\Support\PhoneNumber;

/**
 * Finds what looks like a card number or a 9-digit identifying number (bank routing, Social Security, employer ID) in
 * the text of the money notes (ROOM-14; 05 D-16, D-22, D-26).
 *
 * A false refusal costs the owner a rewrite of one sentence, while a missed card number costs far more, so the scan
 * leans toward refusing. It cannot see a bank account number, which has no shape; the money room's CONTEXT.md tells the
 * owner so, and the later no-secrets scan covers the folder (CONN-01).
 */
class AccountNumberScan
{
    /**
     * A span is a maximal run of digits joined by single spaces or single dashes, with an optional leading plus (D-22).
     * Two spans touching by one space or dash are therefore one span, and a comma or two spaces keep them apart.
     */
    private const SPAN = '/\+?\d+(?:[ -]\d+)*/';

    /**
     * A ZIP+4 as it is normally written: five digits, a dash, four digits. The one 9-digit span that passes (D-26).
     */
    private const ZIP_PLUS_FOUR = '/^\d{5}-\d{4}$/';

    /**
     * Whether any span in the text looks like a card number or a 9-digit identifying number. Each span is judged whole,
     * never in part (D-22).
     */
    public function findsAccountNumber(string $text): bool
    {
        preg_match_all(self::SPAN, $text, $matches);

        foreach ($matches[0] as $span) {
            if ($this->isAccountNumber($span)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide one span, in this order:
     *
     * a. Exactly 9 digits and no leading plus is a hit however it is grouped (123456789, 123-45-6789, 12-3456789,
     *    123 45 6789, 123-456-789), unless it is a ZIP+4 written 60601-1234 (D-26). The phone test is not run here: no
     *    9-digit number is a US phone, and the few 9-digit strings libphonenumber reads as one (113100000 to 113109999,
     *    some 011 numbers) are indistinguishable from Social Security shapes (D-22).
     * b. Otherwise a span libphonenumber reads as a phone is skipped. The test runs on the whole span with any leading
     *    plus, so +34 912345678 is a Spanish phone and passes (D-22).
     * c. Otherwise a span with a leading plus and exactly 9 digits that is not a phone is a hit (+123456789, D-26), and
     *    so is a span of 13 to 19 digits passing the card check digit (D-16). Every other span passes.
     *
     * Card grouping is not tightened: a false refusal is cheaper than a missed card (D-22).
     */
    private function isAccountNumber(string $span): bool
    {
        $digits = preg_replace('/\D/', '', $span);
        $count = strlen($digits);
        $hasPlus = str_starts_with($span, '+');

        if ($count === 9 && ! $hasPlus) {
            return preg_match(self::ZIP_PLUS_FOUR, $span) !== 1;
        }

        if (PhoneNumber::toE164($span) !== null) {
            return false;
        }

        if ($count === 9) {
            return true;
        }

        return $count >= 13 && $count <= 19 && $this->passesCardCheckDigit($digits);
    }

    /**
     * The card check digit (mod 10, also called Luhn): from the right, double every second digit, subtract 9 from a
     * result over 9, and the sum must be a multiple of 10. Hand-written because the only copy in vendor/ is Faker's, which
     * is a development dependency and not available in production.
     */
    private function passesCardCheckDigit(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($position = strlen($digits) - 1; $position >= 0; $position--) {
            $digit = (int) $digits[$position];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
