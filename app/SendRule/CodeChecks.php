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
     * Words that spell money without "$" and digits (D-37). A price or offer sentence that uses one is
     * refused at sync, because the sync can only compare an amount written as "$" and digits. Listed in
     * code, like the veto lists.
     *
     * @var list<string>
     */
    public const MONEY_WORDS = ['hundred', 'thousand', 'million', 'grand', 'dollar', 'dollars', 'buck', 'bucks'];

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
     * Numbers spelled in words. Only a number next to a money word counts as a price (WR-04), so "two
     * systems" and "a three ton unit" are left alone.
     *
     * @var list<string>
     */
    private const NUMBER_WORDS = [
        'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty', 'thirty',
        'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety', 'hundred', 'thousand', 'million',
    ];

    /**
     * A "$" amount, as dollarAmountsInCents() reads one.
     */
    private const DOLLAR_AMOUNT = '\$\s?\d+(?:,\d{3})*(?:\.\d{1,2})?';

    /**
     * What joins a second amount to a "$" amount: plus, to, through, or, a plus sign or a dash.
     */
    private const JOINED_TO_AMOUNT = '(?:\s*,)?\s*(?:plus|\+|to|through|or|-|–)\s*';

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

        $price = self::earliest($text, [...self::PRICE_AMOUNT_PATTERNS, ...self::spelledAmountPatterns()]) ?? self::numberNearPriceWord($text);

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
     * Every "$" amount in the text as integer cents, unique and in ascending order. Only "$" and digits
     * count as a written amount (D-37), so "120 dollars" gives nothing. The conversion never touches a
     * float: "$89" is 8900, "$89.50" is 8950, "$89.5" is 8950, "$2,000" is 200000. "$5k" reads as the
     * "$5" it starts with, 500 cents, which can never equal a real declared amount, so a shorthand
     * amount fails closed.
     *
     * @return list<int>
     */
    public static function dollarAmountsInCents(string $text): array
    {
        preg_match_all('/\$\s?(\d+(?:,\d{3})*)(?:\.(\d{1,2}))?/u', $text, $found, PREG_SET_ORDER);

        $cents = [];

        foreach ($found as $amount) {
            $dollars = (int) str_replace(',', '', $amount[1]);
            $hundredths = (int) str_pad($amount[2] ?? '', 2, '0');

            $cents[] = $dollars * 100 + $hundredths;
        }

        $cents = array_values(array_unique($cents));
        sort($cents);

        return $cents;
    }

    /**
     * Words that, right after a number, say it counts something other than money: "3 tons and up", "plus 2
     * hours on site", "or two visits". A number followed by one is never read as an amount (WR-05).
     */
    private const COUNT_WORDS = '(?:hours?|hrs?|minutes?|mins?|days?|weeks?|months?|years?|visits?|tons?|systems?|units?|rooms?|zones?|floors?|stor(?:y|ies)|sq|square|feet|foot|ft|btus?|seer|am|pm|people|techs?|technicians?|times?|filters?|vents?|stages?|percent|%)';

    /**
     * Every amount in the text that is shaped like money but written without a leading "$", as integer cents,
     * unique and ascending (WR-05, D-37): digits with the "$" after them ("120$"), a number joined to a "$"
     * amount ("$89 or 120", "$4,000 to 6,000", "between $4,000 and 6,000"), a number after plus anywhere in the
     * sentence ("plus 150 for parts"), and a number followed by more, extra or additional ("150 extra"). A number followed by a word that counts something else, such as
     * "3 tons and up" or "plus 2 hours", is not one. The sync compares these with the file's amounts like any
     * "$" amount, so one checked amount cannot carry an unchecked one.
     *
     * @return list<int>
     */
    public static function unsignedAmountsInCents(string $text): array
    {
        $number = '(\d+(?:,\d{3})*(?:\.\d{1,2})?)';
        $notCounting = '(?![\d,.]*\d)(?!\s*-?\s*'.self::COUNT_WORDS.'(?![\p{L}]))';

        $cents = [];

        foreach ([
            '/(?<![\$\d,.])'.$number.'\s?\$/u',
            '/'.self::DOLLAR_AMOUNT.self::JOINED_TO_AMOUNT.$number.$notCounting.'/iu',
            '/between\s+'.self::DOLLAR_AMOUNT.'\s+and\s+'.$number.$notCounting.'/iu',
            '/(?:(?<![\p{L}\p{N}_])plus|\+)\s*'.$number.$notCounting.'/iu',
            '/(?<![\$\d,.])'.$number.'\s+(?:more|extra|additional)(?![\p{L}])(?!\s+'.self::COUNT_WORDS.'(?![\p{L}]))/iu',
        ] as $pattern) {
            preg_match_all($pattern, $text, $found, PREG_SET_ORDER);

            foreach ($found as $amount) {
                [$dollars, $hundredths] = array_pad(explode('.', str_replace(',', '', $amount[1])), 2, '');
                $cents[] = (int) $dollars * 100 + (int) str_pad($hundredths, 2, '0');
            }
        }

        $cents = array_values(array_unique($cents));
        sort($cents);

        return $cents;
    }

    /**
     * The earliest amount shaped like money but spelled in words, as written, or null (WR-05, D-37): a number
     * word joined to a "$" amount or after plus ("$89 plus eighty more per hour"), or followed by more, extra or additional
     * ("eighty more"). "one" alone is left out, because "or one of our plans" is not an amount, and so is a
     * number followed by a word that counts something else ("or two visits").
     */
    public static function spelledAmountIn(string $text): ?string
    {
        $word = '(?:'.implode('|', self::NUMBER_WORDS).')';
        $spelled = '('.$word.'(?:[\s-]+'.$word.')*)';
        $notCounting = '(?!\s+'.self::COUNT_WORDS.'(?![\p{L}]))';

        $best = null;
        $bestOffset = null;

        foreach ([
            '/'.self::DOLLAR_AMOUNT.self::JOINED_TO_AMOUNT.$spelled.'(?![\p{L}])'.$notCounting.'/iu',
            '/(?<![\p{L}\p{N}_])plus\s+'.$spelled.'(?![\p{L}])'.$notCounting.'/iu',
            '/(?<![\p{L}\p{N}_])'.$spelled.'\s+(?:more|extra|additional)(?![\p{L}])'.$notCounting.'/iu',
        ] as $pattern) {
            preg_match_all($pattern, $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($found as $match) {
                if (mb_strtolower($match[1][0]) === 'one') {
                    continue;
                }

                if ($bestOffset === null || $match[1][1] < $bestOffset) {
                    $best = $match[1][0];
                    $bestOffset = $match[1][1];
                }
            }
        }

        return $best;
    }

    /**
     * Every percentage in the text as the digits written, in order of appearance, for "15%" and
     * "15 percent" alike. "12.5%" gives "12.5", so it can never match a percent field of 12.
     *
     * @return list<string>
     */
    public static function percentsIn(string $text): array
    {
        preg_match_all('/(\d+(?:\.\d+)?)(?:\s?%|\s+percent(?![\p{L}\p{N}_]))/iu', $text, $found);

        return $found[1];
    }

    /**
     * The earliest money word or k shorthand as written ("thousand", "Grand", "5k", "$5k", "5 K"), or null.
     * Words match whole words only, so "grandfather" is not "grand". A "$" amount in digits is not a money
     * word. The sync refuses a price or offer sentence that has one (D-37).
     */
    public static function moneyWordIn(string $text): ?string
    {
        return self::earliest($text, [
            ...array_map(self::phrasePattern(...), self::MONEY_WORDS),
            '/(?<![\p{L}\p{N}_])\$?\d+(?:\.\d+)?\s?[kK](?![\p{L}\p{N}_])/u',
        ]);
    }

    /**
     * A plain number within three words of a price word, such as "the fee is 89", through the veto's own
     * rule. The sync uses it to refuse an amount written without a "$" sign.
     */
    public static function plainNumberNearPriceWord(string $text): ?string
    {
        return self::numberNearPriceWord($text);
    }

    /**
     * A number next to a money word, in digits or words: "5 grand", "eighty-nine bucks", "five thousand
     * dollars" or "5k" (WR-04). A number with no money word beside it, such as "3 tons" or "within 2 hours",
     * is not one. "89 dollars" is already a price amount above.
     *
     * @return list<string>
     */
    private static function spelledAmountPatterns(): array
    {
        $word = '(?:'.implode('|', self::NUMBER_WORDS).')';

        return [
            '/(?<![\p{L}\p{N}_])(?:\d+(?:,\d{3})*(?:\.\d+)?|'.$word.'(?:-'.$word.')?)\s+(?:dollars?|bucks?|grand)(?![\p{L}\p{N}_])/iu',
            '/(?<![\p{L}\p{N}_])\d+(?:\.\d+)?\s?k(?![\p{L}\p{N}_])/iu',
        ];
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
     * Public so AsksForPerson matches its phrases exactly the way the veto lists do.
     */
    public static function phrasePattern(string $phrase): string
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
