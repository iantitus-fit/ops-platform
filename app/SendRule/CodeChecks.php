<?php

namespace App\SendRule;

/**
 * The hard veto on text that goes out without the owner: a price, a discount, a promise or a phrase
 * the voice file says never to use (D-02). The sync runs it on the owner's fixed replies, and the send
 * rule runs it on every message the assistant writes. A finding is never overruled by a model.
 */
class CodeChecks
{
    /**
     * Words that make a nearby number a price. These lists are the hard veto (D-02): they live in code,
     * never in the business folder, and are shown to the owner on the Business Folder page.
     *
     * @var list<string>
     */
    public const PRICE_WORDS = ['price', 'prices', 'cost', 'costs', 'charge', 'charges', 'fee', 'fees', 'quote', 'estimate', 'rate', 'total', 'pay', 'bill'];

    /**
     * Phrases that offer a discount. Part of the hard veto (D-02).
     *
     * @var list<string>
     */
    public const DISCOUNT_PHRASES = ['discount', 'percent off', '% off', 'coupon', 'promo', 'special offer', 'for free', 'free of charge', 'at no charge', 'no charge', 'waive', 'waived'];

    /**
     * Phrases that make a promise. Part of the hard veto (D-02).
     *
     * @var list<string>
     */
    public const PROMISE_PHRASES = ['guarantee', 'guaranteed', 'promise', 'promised', 'for sure', 'definitely', '100%', 'no matter what', 'warranty covers'];

    /**
     * A money amount taken off the price, such as "$50 off" or "50 dollars off", which no phrase above can
     * spell because the number changes.
     *
     * @var list<string>
     */
    private const DISCOUNT_AMOUNT_PATTERNS = [
        '/\$\s?\d+(?:,\d{3})*(?:\.\d+)?\s*off(?![\p{L}\p{N}_])/iu',
        '/(?<![\p{L}\p{N}_])\d+(?:,\d{3})*(?:\.\d+)?\s*(?:dollars?|bucks?)\s+off(?![\p{L}\p{N}_])/iu',
    ];

    /**
     * A dollar amount, or a number followed by dollars or bucks.
     *
     * @var list<string>
     */
    private const PRICE_AMOUNT_PATTERNS = [
        '/\$\s?\d+(?:,\d{3})*(?:\.\d+)?/u',
        '/(?<![\p{L}\p{N}_])\d+(?:,\d{3})*(?:\.\d+)?\s+(?:dollars?|bucks?)(?![\p{L}\p{N}_])/iu',
    ];

    /**
     * How many words may sit between a number and a price word for the number to count as a price.
     */
    private const PRICE_WORD_DISTANCE = 3;

    /**
     * Every rule the text breaks, in the order price, discount, promise, never_say, each once with its
     * earliest match as written in the text.
     *
     * Matching ignores case, works on whole words and phrases, and treats curly and straight
     * apostrophes alike. An empty never_say list vetoes nothing on its own.
     *
     * @param  array<array-key, mixed>  $neverSay  The voice file's never_say phrases; entries that are not text are ignored.
     * @return list<array{rule: string, match: string}>
     */
    public static function findings(string $text, array $neverSay): array
    {
        $findings = [];

        $price = self::earliest($text, self::PRICE_AMOUNT_PATTERNS) ?? self::numberNearPriceWord($text);

        $discount = self::earliest($text, [
            ...array_map(self::phrasePattern(...), self::DISCOUNT_PHRASES),
            ...self::DISCOUNT_AMOUNT_PATTERNS,
        ]);

        $promise = self::earliest($text, array_map(self::phrasePattern(...), self::PROMISE_PHRASES));

        $spoken = self::earliest($text, array_map(
            self::phrasePattern(...),
            array_values(array_filter($neverSay, fn (mixed $phrase) => is_string($phrase) && trim($phrase) !== '')),
        ));

        foreach (['price' => $price, 'discount' => $discount, 'promise' => $promise, 'never_say' => $spoken] as $rule => $match) {
            if ($match !== null) {
                $findings[] = ['rule' => $rule, 'match' => $match];
            }
        }

        return $findings;
    }

    /**
     * The rule in words an owner reads in a rejected-folder line or an alert.
     */
    public static function describe(string $rule): string
    {
        return match ($rule) {
            'price' => 'mentions a price',
            'discount' => 'offers a discount',
            'promise' => 'makes a promise',
            'never_say' => 'uses a phrase the voice file says never to use',
            default => 'breaks a rule',
        };
    }

    /**
     * The earliest match in the text of any of the patterns, as written in the text.
     *
     * @param  list<string>  $patterns
     */
    private static function earliest(string $text, array $patterns): ?string
    {
        $best = null;
        $bestOffset = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            if ($bestOffset === null || $matches[0][1] < $bestOffset) {
                $best = $matches[0][0];
                $bestOffset = $matches[0][1];
            }
        }

        return $best;
    }

    /**
     * A plain number within three words of a price word, such as "the fee is 89". Phone numbers, dates
     * and times are not plain numbers, so "call me at 8" and "2026-12-25" are left alone.
     */
    private static function numberNearPriceWord(string $text): ?string
    {
        preg_match_all('/\S+/u', $text, $found);

        $words = array_map(fn (string $word) => trim($word, ".,;:!?()[]\"'“”‘’"), $found[0]);
        $priceWordPositions = [];

        foreach ($words as $position => $word) {
            if (in_array(mb_strtolower($word), self::PRICE_WORDS, true)) {
                $priceWordPositions[] = $position;
            }
        }

        foreach ($words as $position => $word) {
            if (preg_match('/^\d+(?:,\d{3})*(?:\.\d+)?$/', $word) !== 1) {
                continue;
            }

            foreach ($priceWordPositions as $priceWordPosition) {
                if (abs($priceWordPosition - $position) <= self::PRICE_WORD_DISTANCE) {
                    return $word;
                }
            }
        }

        return null;
    }

    /**
     * A pattern for one phrase: any case, any run of spaces between words, either kind of apostrophe, and
     * a word edge wherever the phrase starts or ends with a letter or digit, so "compromise" is not "promise".
     */
    private static function phrasePattern(string $phrase): string
    {
        $words = preg_split('/\s+/u', trim($phrase), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $quoted = array_map(
            fn (string $word) => preg_replace("/['’‘ʼ`´]/u", "['’‘ʼ`´]", preg_quote($word, '/')),
            $words,
        );

        $wordCharacter = '/^[\p{L}\p{N}_]/u';
        $before = preg_match($wordCharacter, $words[0] ?? '') === 1 ? '(?<![\p{L}\p{N}_])' : '';
        $after = preg_match('/[\p{L}\p{N}_]$/u', $words[array_key_last($words)] ?? '') === 1 ? '(?![\p{L}\p{N}_])' : '';

        return '/'.$before.implode('\s+', $quoted).$after.'/iu';
    }
}
