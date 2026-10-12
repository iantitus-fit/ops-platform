<?php

use App\Channels\TextKeywords;

it('reads what Twilio would call a customer text, whole body, trimmed, any case (D-39)', function (string $body, ?string $expected) {
    expect(TextKeywords::twilioOptOutType($body))->toBe($expected);
})->with([
    'stop' => ['stop', 'STOP'],
    'stop with spaces and a capital' => [' Stop ', 'STOP'],
    'unsubscribe' => ['UNSUBSCRIBE', 'STOP'],
    'cancel' => ['cancel', 'STOP'],
    'end' => ['end', 'STOP'],
    'quit' => ['quit', 'STOP'],
    'stopall' => ['stopall', 'STOP'],
    'revoke' => ['revoke', 'STOP'],
    'optout' => ['optout', 'STOP'],
    'start' => ['start', 'START'],
    'unstop' => ['unstop', 'START'],
    'yes' => ['yes', 'START'],
    'help' => ['help', 'HELP'],
    'info' => ['info', 'HELP'],
    'a sentence that starts with stop' => ['stop the noise please', null],
    'stop in the middle' => ['please stop by Tuesday', null],
    'yes with more words' => ['yes please', null],
    'an empty body' => ['', null],
]);

it('decides what a text does, trusting the provider and backing it up with the same words (D-39, D-40, Pitfall 14)', function (?string $optOutType, string $body, bool $optedOut, ?string $expected) {
    expect(TextKeywords::actionFor($optOutType, $body, $optedOut))->toBe($expected);
})->with([
    'the provider says STOP' => ['STOP', 'stop', false, 'stop'],
    'the provider says STOP for any body' => ['STOP', 'Whatever', false, 'stop'],
    'the backup matcher catches Stop' => [null, 'Stop', false, 'stop'],
    'the provider says START' => ['START', 'start', true, 'start'],
    'yes from someone who is not opted out is an answer' => ['START', 'yes', false, null],
    'yes with no provider word from someone who is not opted out is an answer' => [null, 'Yes', false, null],
    'yes from someone who is opted out opts back in' => ['START', 'yes', true, 'start'],
    'the backup matcher catches UNSTOP' => [null, 'UNSTOP', true, 'start'],
    'the provider says HELP' => ['HELP', 'help', false, 'help'],
    'a sentence that starts with stop is an ordinary message' => [null, 'stop the noise please', false, null],
    'no keyword and no provider word' => [null, 'Are you coming today?', true, null],
]);

it('lists the same words Twilio matches', function () {
    expect(TextKeywords::OPT_OUT_WORDS)->toBe(['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'REVOKE', 'OPTOUT'])
        ->and(TextKeywords::OPT_IN_WORDS)->toBe(['START', 'YES', 'UNSTOP'])
        ->and(TextKeywords::HELP_WORDS)->toBe(['HELP', 'INFO']);
});
