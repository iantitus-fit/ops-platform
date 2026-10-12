<?php

use App\SendRule\ClassifierResult;
use App\SendRule\TriageQuestions;
use App\SendRule\TriageReading;

/**
 * Ian's thresholds (D-45, D-46, D-47, D-56), as config/gate.php holds them: price and discount keep the strict
 * 0.075, promise and complaint use 0.15, and gate_check_max_yes is only the floor of the asks_for_person unsure band.
 *
 * @return array<string, float>
 */
function readingThresholds(): array
{
    return [
        'intent_min_confidence' => 0.85,
        'gate_check_max_yes' => 0.075,
        'price_max_yes' => 0.075,
        'promise_max_yes' => 0.15,
        'discount_max_yes' => 0.075,
        'complaint_max_yes' => 0.15,
        'life_safety_min_yes' => 0.075,
        'asks_for_person_min_yes' => 0.85,
    ];
}

function readingQuestions(): TriageQuestions
{
    return new TriageQuestions(
        intentQuestion: 'What does `customer_message` want?',
        intents: ['other' => 'Anything else', 'scheduling' => 'Book', 'intake' => 'Problem', 'faq' => 'Question', 'price_question' => 'Cost', 'complaint' => 'Unhappy', 'emergency' => 'Urgent'],
        sendableIntents: ['scheduling', 'intake', 'faq'],
        yesNo: ['price' => 'p', 'promise' => 'p', 'discount' => 'd', 'complaint' => 'c', 'life_safety' => 'l', 'asks_for_person' => 'a'],
    );
}

/**
 * What Jev said, with every yes or no at 0.01 unless a row says otherwise.
 *
 * @param  array<string, float>  $yesNo
 */
function readingResult(string $intent = 'faq', float $confidence = 0.95, array $yesNo = []): ClassifierResult
{
    return new ClassifierResult(
        model: 'typesafe/jev-1.13-20260917',
        intent: $intent,
        intentConfidence: $confidence,
        intentProbabilities: [$intent => $confidence],
        yesNo: array_merge(['price' => 0.01, 'promise' => 0.01, 'discount' => 0.01, 'complaint' => 0.01, 'life_safety' => 0.01, 'asks_for_person' => 0.01], $yesNo),
    );
}

function readingOf(ClassifierResult $result): TriageReading
{
    return TriageReading::read($result, readingQuestions(), readingThresholds());
}

/*
| Each row: Jev's intent, its confidence, the yes or no answers that differ from 0.01, then whether the reply is
| sendable, the reasons in priority order, and the two flags.
*/
dataset('jev readings', [
    'a clear sendable intent' => ['faq', 0.95, [], true, [], false, false],
    'intent confidence exactly at the minimum' => ['faq', 0.85, [], true, [], false, false],
    'intent confidence just under the minimum' => ['faq', 0.8499, [], false, ['classifier_unsure:intent'], false, false],
    'an intent that never sends' => ['price_question', 0.99, [], false, ['classifier_intent:price_question'], false, false],
    'other never sends' => ['other', 0.99, [], false, ['classifier_intent:other'], false, false],
    'an intent that never sends and is also unsure' => ['other', 0.4, [], false, ['classifier_intent:other', 'classifier_unsure:intent'], false, false],
    'price exactly at the limit' => ['faq', 0.95, ['price' => 0.075], true, [], false, false],
    'price just over the limit' => ['faq', 0.95, ['price' => 0.0751], false, ['classifier:price'], false, false],
    'promise exactly at its bar' => ['faq', 0.95, ['promise' => 0.15], true, [], false, false],
    'promise just over its bar' => ['faq', 0.95, ['promise' => 0.1501], false, ['classifier:promise'], false, false],
    'promise between the old and the new bar' => ['faq', 0.95, ['promise' => 0.0751], true, [], false, false],
    'complaint exactly at its bar' => ['faq', 0.95, ['complaint' => 0.15], true, [], false, false],
    'complaint just over its bar' => ['faq', 0.95, ['complaint' => 0.1501], false, ['classifier:complaint'], false, false],
    'complaint between the old and the new bar' => ['faq', 0.95, ['complaint' => 0.0751], true, [], false, false],
    'discount exactly at the limit' => ['faq', 0.95, ['discount' => 0.075], true, [], false, false],
    'discount just over the limit' => ['faq', 0.95, ['discount' => 0.0751], false, ['classifier:discount'], false, false],
    'the UAT intake reading' => ['intake', 1.0, ['promise' => 0.09, 'complaint' => 0.07], true, [], false, false],
    'a promise' => ['faq', 0.95, ['promise' => 0.5], false, ['classifier:promise'], false, false],
    'a discount' => ['faq', 0.95, ['discount' => 0.9], false, ['classifier:discount'], false, false],
    'a complaint' => ['faq', 0.95, ['complaint' => 0.3], false, ['classifier:complaint'], false, false],
    'life safety exactly at the limit' => ['faq', 0.95, ['life_safety' => 0.075], true, [], false, false],
    'life safety just over the limit' => ['faq', 0.95, ['life_safety' => 0.0751], false, ['classifier:life_safety'], true, false],
    'asks for a person exactly at the minimum' => ['faq', 0.95, ['asks_for_person' => 0.85], false, ['classifier:asks_for_person'], false, true],
    'asks for a person just under the minimum' => ['faq', 0.95, ['asks_for_person' => 0.8499], false, ['classifier_unsure:asks_for_person'], false, false],
    'asks for a person in the middle' => ['faq', 0.95, ['asks_for_person' => 0.5], false, ['classifier_unsure:asks_for_person'], false, false],
    'asks for a person at the clear-no limit' => ['faq', 0.95, ['asks_for_person' => 0.075], true, [], false, false],
    'asks for a person just over the clear-no limit' => ['faq', 0.95, ['asks_for_person' => 0.0751], false, ['classifier_unsure:asks_for_person'], false, false],
    'several failures at once are listed by priority' => ['faq', 0.5, ['price' => 0.9, 'complaint' => 0.9], false, ['classifier:complaint', 'classifier:price', 'classifier_unsure:intent'], false, false],
    'every failure at once' => ['emergency', 0.5, ['price' => 0.9, 'promise' => 0.9, 'discount' => 0.9, 'complaint' => 0.9, 'life_safety' => 0.9, 'asks_for_person' => 0.9], false, ['classifier:life_safety', 'classifier:asks_for_person', 'classifier:complaint', 'classifier:price', 'classifier:promise', 'classifier:discount', 'classifier_intent:emergency', 'classifier_unsure:intent'], true, true],
]);

it('reads Jev answers against the thresholds', function (string $intent, float $confidence, array $yesNo, bool $sendable, array $reasons, bool $lifeSafety, bool $asksForPerson) {
    $reading = readingOf(readingResult($intent, $confidence, $yesNo));

    expect($reading->sendable)->toBe($sendable)
        ->and($reading->reasons)->toBe($reasons)
        ->and($reading->reasonCode)->toBe($reasons[0] ?? null)
        ->and($reading->lifeSafety)->toBe($lifeSafety)
        ->and($reading->asksForPerson)->toBe($asksForPerson);
})->with('jev readings');

it('puts the strongest reason first when several apply', function () {
    $reading = readingOf(readingResult('faq', 0.5, ['price' => 0.9, 'complaint' => 0.9]));

    expect($reading->reasonCode)->toBe('classifier:complaint');
});

it('holds a reply when Jev left out one of the yes or no answers', function () {
    $result = new ClassifierResult('m', 'faq', 0.95, [], ['price' => 0.01]);

    $reading = readingOf($result);

    expect($reading->sendable)->toBeFalse()
        ->and($reading->reasons)->not->toBeEmpty();
});

it('copies the thresholds it used, the reasons and the two flags into the array form', function () {
    $reading = readingOf(readingResult('faq', 0.95, ['life_safety' => 0.5]));

    expect($reading->toArray())->toBe([
        'sendable' => false,
        'reason_code' => 'classifier:life_safety',
        'reasons' => ['classifier:life_safety'],
        'life_safety' => true,
        'asks_for_person' => false,
        'thresholds' => readingThresholds(),
    ]);
});

it('compares the numbers as they came with no rounding', function () {
    expect(readingOf(readingResult('faq', 0.84999999999))->sendable)->toBeFalse()
        ->and(readingOf(readingResult('faq', 0.85000000001, ['price' => 0.07500000001]))->reasons)->toBe(['classifier:price'])
        ->and(readingOf(readingResult('faq', 0.85000000001, ['price' => 0.07499999999]))->sendable)->toBeTrue()
        ->and(readingOf(readingResult('faq', 0.95, ['promise' => 0.15000000001]))->reasons)->toBe(['classifier:promise']);
});

it('uses the same thresholds as config/gate.php', function () {
    $config = require dirname(__DIR__, 3).'/config/gate.php';

    expect(readingThresholds())->toBe($config['classifier']);
});
