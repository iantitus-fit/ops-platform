<?php

namespace App\SendRule;

/**
 * Spots a customer describing a dangerous situation and holds the words sent back (D-15, D-16).
 *
 * The word lists and the three sentences are code, never folder content, so no edit to the business folder can
 * weaken the instruction to call 911. The folder fills in one thing only: the gas company's phone number. A false
 * alarm costs the customer a moment and a missed one can cost a life, so when a text could mean danger the line
 * is sent. Plain flooding, a burst pipe or water on the floor, is not a safety matter and does not trigger it.
 *
 * Matching uses CodeChecks::phrasePattern(), so case, spacing, curly apostrophes and whole words work exactly as
 * they do for the other hard vetoes.
 */
class LifeSafety
{
    /**
     * Phrases that mean a gas smell. Phrases only: the bare words propane, natural gas, sulfur and sulphur are
     * left out because customers ask about propane and natural gas equipment all the time.
     *
     * @var list<string>
     */
    public const GAS_PHRASES = [
        'smell gas',
        'smells like gas',
        'gas smell',
        'smell of gas',
        'gas leak',
        'leaking gas',
        'rotten egg',
        'rotten eggs',
        'smells like sulfur',
        'sulfur smell',
        'smell sulfur',
        'smells like sulphur',
        'sulphur smell',
        'smells like propane',
        'propane smell',
        'smell propane',
        'propane leak',
        'smells like natural gas',
        'smell natural gas',
    ];

    /**
     * Phrases that mean fire, smoke, a carbon monoxide alarm or electrical sparking.
     *
     * @var list<string>
     */
    public const FIRE_PHRASES = [
        'on fire',
        'a fire',
        'fire in',
        'caught fire',
        'catching fire',
        'flames',
        'smoke',
        'smoking',
        'burning smell',
        'smells like burning',
        'smell burning',
        'carbon monoxide',
        'co alarm',
        'co detector',
        'spark',
        'sparks',
        'sparking',
        'arcing',
    ];

    /**
     * Words that put water in the picture. On their own they are an ordinary repair call.
     *
     * @var list<string>
     */
    public const WATER_WORDS = ['water', 'flood', 'flooding', 'flooded', 'leak', 'leaking', 'wet'];

    /**
     * Words for something electrical. Together with a water word they mean water may be reaching live power.
     *
     * @var list<string>
     */
    public const ELECTRICAL_WORDS = ['outlet', 'outlets', 'socket', 'electrical panel', 'breaker panel', 'panel', 'plugged in', 'appliance', 'appliances', 'wiring'];

    /**
     * The sentence for a gas smell. Nothing from the folder is added to it except the gas company's number.
     */
    private const GAS_LINE = 'If you smell gas, leave the building now with everyone, and call 911 from outside.';

    private const GAS_COMPANY_LINE = 'Then call the gas company at';

    private const GENERAL_LINE = 'Please get everyone out to safety now and call 911 from outside.';

    private const WATER_LINE = 'Please stay out of the water, keep away from anything electrical, and call 911.';

    /**
     * Which danger the text describes: gas, general (fire, smoke, carbon monoxide, sparks), water reaching
     * something electrical, or null. Gas is checked first, then general, then water.
     */
    public static function hazardIn(string $text): ?string
    {
        if (self::containsAny($text, self::GAS_PHRASES)) {
            return 'gas';
        }

        if (self::containsAny($text, self::FIRE_PHRASES)) {
            return 'general';
        }

        if (self::containsAny($text, self::WATER_WORDS) && self::containsAny($text, self::ELECTRICAL_WORDS)) {
            return 'water';
        }

        return null;
    }

    /**
     * The safety sentence for a hazard. It tells the customer to get to safety and call 911 and never gives a
     * repair or shut-off step. The utility phone is added to the gas sentence only when it is a plain phone number.
     */
    public static function line(string $hazard, ?string $utilityPhone): string
    {
        return match ($hazard) {
            'gas' => self::gasLine($utilityPhone),
            'water' => self::WATER_LINE,
            default => self::GENERAL_LINE,
        };
    }

    private static function gasLine(?string $utilityPhone): string
    {
        $phone = trim((string) $utilityPhone);

        if (preg_match('/^\+?[0-9][0-9 ()\-.]{5,19}$/', $phone) !== 1) {
            return self::GAS_LINE;
        }

        return self::GAS_LINE.' '.self::GAS_COMPANY_LINE.' '.$phone.'.';
    }

    /**
     * @param  list<string>  $phrases
     */
    private static function containsAny(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (preg_match(CodeChecks::phrasePattern($phrase), $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
