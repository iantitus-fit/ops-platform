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
        'gas is leaking',
        'gas leaking',
        'smells of gas',
        'smell of propane',
        'gas odor',
        'gas odour',
        'smelling gas',
        'smelled gas',
        'smells gas',
        'gas hissing',
        'gas is hissing',
        'hissing gas',
        'smelling propane',
        'smelled propane',
        'smells propane',
        'smelling natural gas',
        'smelled natural gas',
        'smells natural gas',
        'smelling sulfur',
        'smelled sulfur',
        'smells sulfur',
    ];

    /**
     * Phrases that mean fire, smoke, a carbon monoxide alarm, electrical sparking or an electric shock. "Burning"
     * appears only in phrases that put it next to a smell or "something", never on its own, so a "burning question"
     * is not a fire.
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
        'electrical fire',
        'house is burning',
        'burnt smell',
        'smells burnt',
        'something burning',
        'something is burning',
        'smells burning',
        'smelling burning',
        'smelled burning',
        'smell of burning',
        'smells of burning',
        'electrocuted',
        'electrocution',
        'electric shock',
        'electrical shock',
        'got shocked',
        'got a shock',
    ];

    /**
     * Words that put water in the picture. On their own they are an ordinary repair call.
     *
     * @var list<string>
     */
    public const WATER_WORDS = ['water', 'flood', 'flooding', 'flooded', 'leak', 'leaking', 'wet'];

    /**
     * Words for something electrical. Together with a water word they mean water may be reaching live power.
     * Only words that mean live power are listed: a bare "appliance" or "panel" next to a leak is an ordinary
     * repair call, such as a dishwasher or a water heater cover. A plugged-in appliance still counts through
     * "plugged in" and "plugged-in".
     *
     * @var list<string>
     */
    public const ELECTRICAL_WORDS = ['outlet', 'outlets', 'socket', 'electrical panel', 'breaker panel', 'plugged in', 'plugged-in', 'wiring'];

    /**
     * Appliance names that contain a water word but are not water on their own. They are removed from the text before
     * the water words are checked, so "the wiring on my water heater" is no hazard while "water is pouring onto the
     * water heater wiring" still is.
     *
     * @var list<string>
     */
    private const NOT_WATER_PHRASES = ['water heater'];

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

        if (self::containsAny(self::withoutApplianceNames($text), self::WATER_WORDS) && self::containsAny($text, self::ELECTRICAL_WORDS)) {
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
     * The text with each appliance name that is not water taken out, matched the way every other phrase is.
     */
    private static function withoutApplianceNames(string $text): string
    {
        foreach (self::NOT_WATER_PHRASES as $phrase) {
            $text = (string) preg_replace(CodeChecks::phrasePattern($phrase), ' ', $text);
        }

        return $text;
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
