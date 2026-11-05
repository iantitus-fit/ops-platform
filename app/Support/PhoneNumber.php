<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumber
{
    /**
     * Turn whatever a customer or a form gave us into E.164, such as "+12025550123".
     *
     * Returns null for an empty, unparseable or invalid number, so junk never becomes the
     * key a contact is matched on. Numbers without a country code are read in the region
     * from config('channels.phone_region'). Extensions are dropped.
     */
    public static function toE164(?string $raw): ?string
    {
        $trimmed = trim((string) $raw);

        if ($trimmed === '') {
            return null;
        }

        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        try {
            $number = $phoneNumberUtil->parse($trimmed, config('channels.phone_region'));
        } catch (NumberParseException) {
            return null;
        }

        if (! $phoneNumberUtil->isValidNumber($number)) {
            return null;
        }

        return $phoneNumberUtil->format($number, PhoneNumberFormat::E164);
    }

    /**
     * An E.164 number the way a customer reads it in the United States, such as "(312) 555-0147". A value that does not
     * parse comes back as it was given.
     */
    public static function national(string $e164): string
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        try {
            return $phoneNumberUtil->format($phoneNumberUtil->parse($e164), PhoneNumberFormat::NATIONAL);
        } catch (NumberParseException) {
            return $e164;
        }
    }
}
