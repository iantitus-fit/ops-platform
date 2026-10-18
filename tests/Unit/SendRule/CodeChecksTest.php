<?php

use App\SendRule\CodeChecks;

it('finds a price, a discount, a promise or a never_say phrase and reports the first match of each rule', function (string $text, array $neverSay, array $expected) {
    expect(CodeChecks::findings($text, $neverSay))->toBe($expected);
})->with([
    'a dollar amount' => ['A visit starts at $89.', [], [['rule' => 'price', 'match' => '$89']]],
    'a dollar amount with a space' => ['A visit starts at $ 89.', [], [['rule' => 'price', 'match' => '$ 89']]],
    'a number followed by dollars' => ['That is 89 dollars.', [], [['rule' => 'price', 'match' => '89 dollars']]],
    'a number near a price word' => ['The fee is 89', [], [['rule' => 'price', 'match' => '89']]],
    'a number after the word costs' => ['It costs 120', [], [['rule' => 'price', 'match' => '120']]],
    'percent off' => ['Take 10% off today', [], [['rule' => 'discount', 'match' => '% off']]],
    'the word discount' => ['We can offer a discount', [], [['rule' => 'discount', 'match' => 'discount']]],
    'free of charge' => ['The check is free of charge', [], [['rule' => 'discount', 'match' => 'free of charge']]],
    'at no charge' => ['We do it at no charge', [], [['rule' => 'discount', 'match' => 'at no charge']]],
    'I guarantee it' => ['I guarantee it', [], [['rule' => 'promise', 'match' => 'guarantee']]],
    'we promise' => ['We promise to be there', [], [['rule' => 'promise', 'match' => 'promise']]],
    'compromise is not promise' => ['We can reach a compromise', [], []],
    'never_say ignores case and keeps the written case' => ['No Problem!', ['no problem'], [['rule' => 'never_say', 'match' => 'No Problem']]],
    'never_say treats a curly apostrophe like a straight one' => ['Don’t worry about it', ["don't worry"], [['rule' => 'never_say', 'match' => 'Don’t worry']]],
    'never_say written with a curly apostrophe matches a straight one' => ["Don't worry about it", ['don’t worry'], [['rule' => 'never_say', 'match' => "Don't worry"]]],
    'never_say is multibyte safe' => ['Es ist ÄUSSERST günstig', ['äußerst günstig', 'äusserst günstig'], [['rule' => 'never_say', 'match' => 'ÄUSSERST günstig']]],
    'all four rules come back in order' => ['$50 off, guaranteed', ['guaranteed'], [
        ['rule' => 'price', 'match' => '$50'],
        ['rule' => 'discount', 'match' => '$50 off'],
        ['rule' => 'promise', 'match' => 'guaranteed'],
        ['rule' => 'never_say', 'match' => 'guaranteed'],
    ]],
]);

it('finds a number in digits or words next to a money word as a price (WR-04)', function (string $text, string $match) {
    expect(CodeChecks::findings($text, []))->toBe([['rule' => 'price', 'match' => $match]]);
})->with([
    'number words and dollars' => ['It runs about five thousand dollars.', 'thousand dollars'],
    'a number word and grand' => ['Figure five grand for a new unit.', 'five grand'],
    'digits and grand' => ['Figure 5 grand for a new unit.', '5 grand'],
    'k shorthand' => ['It is around 5k installed.', '5k'],
    'k shorthand with a space' => ['It is around 5 K installed.', '5 K'],
    'a hyphenated number word and bucks' => ['The visit is eighty-nine bucks.', 'eighty-nine bucks'],
    'a number word and a dollar' => ['That part is twenty dollars.', 'twenty dollars'],
    'hundred and bucks' => ['A couple hundred bucks should cover it.', 'hundred bucks'],
]);

it('leaves a number that sits next to no money word alone (WR-04)', function (string $text) {
    expect(CodeChecks::findings($text, []))->toBe([]);
})->with([
    'a unit size' => 'That fits a 3 tons system.',
    'a count in words' => 'You have two systems in the house.',
    'around the clock' => 'We answer 24/7.',
    'a time to arrive' => 'A tech can be there within 2 hours.',
    'a size in words' => 'A three ton unit is common for that size.',
    'grand without a number' => 'We serve Grand Rapids.',
]);

it('finds nothing in ordinary scheduling text', function (string $text) {
    expect(CodeChecks::findings($text, []))->toBe([]);
})->with([
    'an opening time' => 'We open at 8 tomorrow',
    'a time to call' => 'Can I call you at 8?',
    'a range of years' => 'About 5 to 10 years old',
    'a phone number' => 'Call us at +13125550147',
    'a date' => 'We are closed on 2026-12-25',
    'a day question' => 'Are you free Tuesday?',
    'a length of time' => 'It takes about 90 minutes',
]);

it('vetoes nothing on its own when the never_say list is empty, and the other checks still run', function () {
    expect(CodeChecks::findings('Anything', []))->toBe([])
        ->and(CodeChecks::findings('Anything at $20', []))->toBe([['rule' => 'price', 'match' => '$20']]);
});

it('ignores a never_say entry that is blank or not text', function () {
    expect(CodeChecks::findings('Anything at all', ['', '   ', 7, null]))->toBe([]);
});

it('describes each rule in plain words', function (string $rule, string $words) {
    expect(CodeChecks::describe($rule))->toBe($words);
})->with([
    ['price', 'mentions a price'],
    ['discount', 'offers a discount'],
    ['promise', 'makes a promise'],
    ['never_say', 'uses a phrase the voice file says never to use'],
]);

it('keeps the word lists in code as lists of strings', function () {
    expect(CodeChecks::PRICE_WORDS)->toContain('price', 'fee', 'quote')
        ->and(CodeChecks::DISCOUNT_PHRASES)->toContain('discount', '% off', 'free of charge')
        ->and(CodeChecks::PROMISE_PHRASES)->toContain('guarantee', 'promise', 'definitely');
});

it('reads every dollar amount in a sentence as integer cents, unique and sorted', function (string $text, array $expected) {
    $cents = CodeChecks::dollarAmountsInCents($text);

    expect($cents)->toBe($expected);

    foreach ($cents as $amount) {
        expect($amount)->toBeInt();
    }
})->with([
    'the same amount twice' => ['AC repair starts with an $89 diagnostic visit, and we take the $89 off', [8900]],
    'ranges sorted ascending and unique' => ['$2,000 to $5,000 installed for a smaller unit and $5,000 to $10,000 for a larger one', [200000, 500000, 1000000]],
    'dollars and cents' => ['The visit is $89.50', [8950]],
    'one decimal digit is tens of cents' => ['The visit is $89.5', [8950]],
    'a space after the dollar sign' => ['The visit is $ 120', [12000]],
    'an amount at the end of a sentence' => ['A new unit runs $2,000.', [200000]],
    'k shorthand reads as the digits before it, which never equals a real amount' => ['A new unit runs $5k', [500]],
    'no dollar sign' => ['We open at 8 tomorrow', []],
    'dollars spelled out are not an amount' => ['That is 120 dollars', []],
]);

it('finds percentages as the digits written, in order', function (string $text, array $expected) {
    expect(CodeChecks::percentsIn($text))->toBe($expected);
})->with([
    'a percent sign' => ['takes 15% off', ['15']],
    'the word percent' => ['15 percent off', ['15']],
    'a decimal percent keeps its digits' => ['12.5% off', ['12.5']],
    'two percents in order' => ['10% and 20%', ['10', '20']],
    'no percent' => ['no percent here', []],
]);

it('finds the earliest money word or k shorthand as written', function (string $text, ?string $expected) {
    expect(CodeChecks::moneyWordIn($text))->toBe($expected);
})->with([
    'thousand' => ['runs 5 thousand', 'thousand'],
    'grand' => ['about 5 grand', 'grand'],
    'case is kept' => ['Five Hundred', 'Hundred'],
    'k shorthand' => ['costs 5k', '5k'],
    'k shorthand with a dollar sign' => ['$5k installed', '$5k'],
    'k shorthand with a space and a capital' => ['about 5 K', '5 K'],
    'dollars spelled out' => ['120 dollars', 'dollars'],
    'a buck' => ['a buck', 'buck'],
    'grandfather is not grand' => ['grandfather', null],
    'dollar amounts written with a dollar sign are fine' => ['$2,000 to $5,000', null],
    'tons are not money' => ['3 tons', null],
]);

it('finds a plain number near a price word through the same rule the veto uses', function (string $text, ?string $expected) {
    expect(CodeChecks::plainNumberNearPriceWord($text))->toBe($expected);
})->with([
    'costs and a number' => ['It costs 4000', '4000'],
    'a fee and a number' => ['The fee is 89', '89'],
    'a dollar amount is not a plain number' => ['$89 diagnostic fee', null],
    'a size far from any price word' => ['under 3 tons, a replacement system typically runs $2,000', null],
]);

it('lists the money words in code', function () {
    expect(CodeChecks::MONEY_WORDS)->toBe(['hundred', 'thousand', 'million', 'grand', 'dollar', 'dollars', 'buck', 'bucks']);
});
