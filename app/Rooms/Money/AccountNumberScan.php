<?php

namespace App\Rooms\Money;

use App\Support\PhoneNumber;

/**
 * Finds what looks like a card number or a 9-digit identifying number (bank routing, Social Security, employer ID) in
 * the text of the money notes (ROOM-14; 05 D-16, D-22, D-26, D-28).
 *
 * A false refusal costs the owner a rewrite of one sentence, while a missed card number costs far more, so the scan
 * leans toward refusing. It cannot see a bank account number, which has no shape; the money room's CONTEXT.md tells the
 * owner so, and the later no-secrets scan covers the folder (CONN-01).
 */
class AccountNumberScan
{
    /**
     * The most digits a card number can have. A run of whole digit groups is never extended past it (D-28), so the work
     * for a span stays linear in its length.
     */
    private const MOST_CARD_DIGITS = 19;

    /**
     * A span is a maximal run of digit groups joined by one separator each, with an optional leading plus (D-22, D-28).
     * A separator is a dot, any one Unicode dash (the hyphen included), or any run of horizontal whitespace (spaces,
     * tabs, non-breaking and other Unicode spaces, two spaces included) holding at most one line break (\R, so a Windows
     * line break is one). So a markdown hard break and a list item continued on an indented line join their groups,
     * while a blank line, a comma, or a space beside a dot keeps two spans apart. The whitespace quantifiers are
     * possessive because whitespace never matches a digit, so giving characters back could never help. Digits are
     * written 0-9 so a Unicode digit is never read as a group.
     */
    private const SPAN = '/\+?[0-9]+(?:(?:[.\p{Pd}]|\h*+\R\h*+|\h++)[0-9]+)*/u';

    /**
     * A ZIP+4 as it is normally written: five digits, an ASCII hyphen, four digits. The one 9-digit span that passes
     * (D-26).
     */
    private const ZIP_PLUS_FOUR = '/^[0-9]{5}-[0-9]{4}\z/';

    /**
     * Whether any span in the text looks like a card number or a 9-digit identifying number. Each span is judged whole
     * first (D-22); a span over 19 digits is then also judged on its own group boundaries (D-28).
     *
     * @throws NumberCheckCouldNotFinish when a pattern cannot finish, so a scan that stops early never passes (D-30)
     */
    public function findsAccountNumber(string $text): bool
    {
        $this->finished(preg_match_all(self::SPAN, mb_scrub($text, 'UTF-8'), $matches));

        foreach ($matches[0] as $span) {
            if ($this->isAccountNumber($span) || $this->hasAccountNumberRun($span)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide one span as a whole, in this order:
     *
     * a. Exactly 9 digits and no leading plus is a hit however it is grouped (123456789, 123-45-6789, 12-3456789,
     *    123 45 6789, 123-456-789), unless it is a ZIP+4 written 60601-1234 (D-26). The phone test is not run here: no
     *    9-digit number is a US phone, and the few 9-digit strings libphonenumber reads as one (113100000 to 113109999,
     *    some 011 numbers) are indistinguishable from Social Security shapes (D-22).
     * b. Otherwise a span libphonenumber reads as a phone is skipped. The test runs on the whole span with any leading
     *    plus, so +34 912345678 is a Spanish phone and passes (D-22).
     * c. Otherwise a span with a leading plus and exactly 9 digits that is not a phone is a hit (+123456789, D-26), and
     *    so is a span of 13 to 19 digits passing the card check digit (D-16). Every other span passes here.
     *
     * Card grouping is not tightened: a false refusal is cheaper than a missed card (D-22).
     */
    private function isAccountNumber(string $span): bool
    {
        $digits = $this->finished(preg_replace('/[^0-9]/', '', $span));
        $count = strlen($digits);
        $hasPlus = str_starts_with($span, '+');

        if ($count === 9 && ! $hasPlus) {
            return $this->finished(preg_match(self::ZIP_PLUS_FOUR, $span)) !== 1;
        }

        if (PhoneNumber::toE164($span) !== null) {
            return false;
        }

        if ($count === 9) {
            return true;
        }

        return $count >= 13 && $count <= self::MOST_CARD_DIGITS && $this->passesCardCheckDigit($digits);
    }

    /**
     * Whether a span over 19 digits holds, on its own group boundaries, a card or a 9-digit number (D-28). It checks
     * every contiguous run of whole digit groups (never part of a group), so "2026-10-25 4111 1111 1111 1111" is
     * refused for the card in it. A run is a hit when it has exactly 9 digits, or 13 to 19 digits passing the card check
     * digit, unless the run's own text is a whole phone number. The run's text keeps the span's leading plus when the
     * run starts at the span's first group. The ZIP+4 exception does not apply to a run: D-28 gives the phone test as the
     * only one. A run stops growing once it passes 19 digits, so a span costs at most 19 runs per group.
     */
    private function hasAccountNumberRun(string $span): bool
    {
        $digits = $this->finished(preg_replace('/[^0-9]/', '', $span));

        if (strlen($digits) <= self::MOST_CARD_DIGITS) {
            return false;
        }

        $this->finished(preg_match_all('/[0-9]+/', $span, $groups, PREG_OFFSET_CAPTURE));
        $groups = $groups[0];
        $groupCount = count($groups);
        $digitsBefore = [];
        $seen = 0;

        foreach ($groups as $index => [$group]) {
            $digitsBefore[$index] = $seen;
            $seen += strlen($group);
        }

        for ($first = 0; $first < $groupCount; $first++) {
            $count = 0;

            for ($last = $first; $last < $groupCount; $last++) {
                $count += strlen($groups[$last][0]);

                if ($count > self::MOST_CARD_DIGITS) {
                    break;
                }

                if ($count !== 9 && ($count < 13 || ! $this->passesCardCheckDigit(substr($digits, $digitsBefore[$first], $count)))) {
                    continue;
                }

                $start = $first === 0 ? 0 : $groups[$first][1];
                $end = $groups[$last][1] + strlen($groups[$last][0]);

                if (PhoneNumber::toE164(substr($span, $start, $end - $start)) === null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Pass a pattern call's result through, or stop the scan when the call could not finish: PHP's pattern functions
     * return false or null when they hit a limit, and a "no match" read from that would let the file through (D-30).
     *
     * @template TResult
     *
     * @param  TResult|false|null  $result
     * @return TResult
     *
     * @throws NumberCheckCouldNotFinish
     */
    private function finished(mixed $result): mixed
    {
        if ($result === false || $result === null) {
            throw new NumberCheckCouldNotFinish(preg_last_error_msg());
        }

        return $result;
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
