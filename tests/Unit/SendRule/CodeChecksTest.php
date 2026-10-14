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
