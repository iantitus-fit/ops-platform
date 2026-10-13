<?php

namespace App\SendRule;

/**
 * Spots a customer asking for a person by phrases that cannot mean anything else (D-47).
 *
 * Only unambiguous phrases are listed. A bare "human" or "call me" is left out on purpose, because "it was human
 * error" and "call me when you're on the way" are not a request to reach the owner. Everything vaguer is left to
 * the agent's own wants_person and to Jev's asks_for_person, which holds a draft when it is unsure.
 */
class AsksForPerson
{
    /**
     * @var list<string>
     */
    public const PHRASES = [
        'real person',
        'live person',
        'actual person',
        'talk to someone',
        'speak to someone',
        'talk with someone',
        'speak with someone',
        'talk to a person',
        'speak to a person',
        'talk with a person',
        'speak with a person',
        'talk to a human',
        'speak to a human',
        'talk to the owner',
        'speak to the owner',
        'talk to a manager',
        'speak to a manager',
    ];

    /**
     * Whether the customer's text contains one of the phrases, ignoring case, spacing and the kind of apostrophe,
     * and matching whole words only.
     */
    public static function inText(string $text): bool
    {
        foreach (self::PHRASES as $phrase) {
            if (preg_match(CodeChecks::phrasePattern($phrase), $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
