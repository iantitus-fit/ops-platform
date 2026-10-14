<?php

namespace App\SendRule;

use App\Models\Message;

/**
 * Reads why a held draft was held, from the verdict stored on it and the services its run recorded, so code can tell
 * a draft that only a price sentence could answer from one that needs the owner (D-21).
 *
 * Nothing here asks the model anything. The stored verdict is the send rule's own record and the service lists are
 * written by code from its own price picks, so a close made from them is as trustworthy as the hold was.
 */
class HeldReasons
{
    /**
     * The reason codes that mean a draft was held for its price and nothing else.
     *
     * @var list<string>
     */
    public const array PRICE_CODES = ['label:price', 'code:price', 'classifier:price', 'classifier_intent:price_question', 'price:no_sentence'];

    /**
     * Whether the draft was held on nothing but price, so that a price sentence is a complete answer to it.
     *
     * Every recorded check has to agree, because a draft held on price and a complaint, a promise, a failed check or a
     * life-safety reading still has something left for the owner after a price sentence goes out:
     *  - the reason code is one of the price codes;
     *  - the length check did not fail;
     *  - every code finding is the price rule;
     *  - the label check did not fail, or it failed only because the label was price;
     *  - when Jev read it, every reason Jev gave is a price reason and neither the safety nor the hand-off reading is set.
     *
     * A missing or malformed verdict is not price alone: the code cannot show the draft waited on nothing else.
     */
    public static function priceAlone(Message $draft): bool
    {
        $gate = $draft->gate;

        if (! is_array($gate)) {
            return false;
        }

        $code = $gate['reason']['code'] ?? null;

        if (! is_string($code) || ! in_array($code, self::PRICE_CODES, true)) {
            return false;
        }

        $checks = $gate['checks'] ?? [];

        return is_array($checks)
            && ($checks['length']['passed'] ?? null) !== false
            && self::onlyPriceFindings($checks['code']['findings'] ?? [])
            && (($checks['label']['passed'] ?? null) !== false || ($checks['label']['label'] ?? null) === 'price')
            && self::readingIsPriceAlone($checks['classifier']['reading'] ?? null);
    }

    /**
     * Whether a price sentence for the services in $sentSlugs finishes this draft (D-21).
     *
     * The draft and every draft in its meta.replaces chain must each have been held on price alone, because a
     * replacement draft carries on what the older ones asked. Each draft's run recorded which services it looked a
     * price sentence up for (price_slugs) and which of those got no sentence (price_open). The close keys on those:
     *  - a draft whose run sent a sentence for every service it looked up held on something no sentence answers, so
     *    nothing closes it;
     *  - otherwise every service the chain still waits on must have a sentence in this run, and a sentence only for some
     *    other service never closes it, however the model read the new text.
     *
     * A draft whose run looked up no service, such as a price question that named none or a draft stored before these
     * keys existed, gives code nothing to match. It closes only when the model clearly says the new text touches it
     * (touches_waiting exactly true). On false or null it stays live: a wrong close would take a question the customer
     * was told is with the owner out of the owner's queue and drop its after-hours alert, while a missed close only
     * leaves a customer who already has the price and an owner the thread warns with "The customer wrote again since
     * this draft".
     *
     * @param  list<string>  $sentSlugs  The services a price sentence goes out for in this run.
     */
    public static function answeredBy(Message $draft, array $sentSlugs, ?bool $touchesWaiting): bool
    {
        if ($sentSlugs === []) {
            return false;
        }

        $stillOpen = [];
        $namedNoService = false;

        foreach ([$draft, ...self::replacedDrafts($draft)] as $member) {
            if (! self::priceAlone($member)) {
                return false;
            }

            $looked = self::slugs($member->meta['price_slugs'] ?? null);
            $open = self::slugs($member->meta['price_open'] ?? null);

            if ($looked === []) {
                $namedNoService = true;
            } elseif ($open === []) {
                return false;
            }

            $stillOpen = [...$stillOpen, ...$open];
        }

        if (array_diff($stillOpen, $sentSlugs) !== []) {
            return false;
        }

        return ! $namedNoService || $touchesWaiting === true;
    }

    /**
     * The drafts this one replaced, read from its meta.replaces list.
     *
     * @return list<Message>
     */
    private static function replacedDrafts(Message $draft): array
    {
        $ids = array_values(array_filter($draft->meta['replaces'] ?? [], is_int(...)));

        return $ids === [] ? [] : Message::query()->whereKey($ids)->get()->all();
    }

    /**
     * @return list<string>
     */
    private static function slugs(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /**
     * Whether every stored code finding is the price rule. A findings list that is not a list of findings is not.
     */
    private static function onlyPriceFindings(mixed $findings): bool
    {
        if (! is_array($findings)) {
            return false;
        }

        foreach ($findings as $finding) {
            if (! is_array($finding) || ($finding['rule'] ?? null) !== 'price') {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether Jev's stored reading, when there is one, holds the draft for price reasons only.
     */
    private static function readingIsPriceAlone(mixed $reading): bool
    {
        if ($reading === null) {
            return true;
        }

        if (! is_array($reading) || ! is_array($reading['reasons'] ?? [])) {
            return false;
        }

        foreach ($reading['reasons'] ?? [] as $reason) {
            if (! in_array($reason, ['classifier:price', 'classifier_intent:price_question'], true)) {
                return false;
            }
        }

        return ($reading['life_safety'] ?? false) === false && ($reading['asks_for_person'] ?? false) === false;
    }
}
