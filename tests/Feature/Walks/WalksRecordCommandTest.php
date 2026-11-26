<?php

use App\Enums\VersionStatus;
use App\Icm\Walks\WalkFingerprint;
use App\Models\IcmVersion;
use App\Models\WalkRecording;
use App\SendRule\FakeClassifier;

require_once __DIR__.'/WalkHelpers.php';

/**
 * A walk where the customer asks the price of an AC repair and the owner expects the owner's own sentence for it.
 */
function recordPriceWalkText(): string
{
    return "---\ntype: walk_test\nname: Dana asks the price\npersona: A homeowner asking about cost\nchannel: sms\nscript:\n  - says: \"How much is an AC repair?\"\n    expect: [carries the owner's price sentence for ac-repair]\n---\nA walk.\n";
}

/**
 * The one reply the fake front desk model gives to the price walk: its own words and a flag that the customer asked the price.
 *
 * @return array<string, mixed>
 */
function recordPriceReply(): array
{
    return ['reply' => 'Happy to help with that.', 'label' => 'faq_answer', 'service_slug' => 'ac-repair', 'price_services' => ['ac-repair']];
}

it('records a walk and the next sync replays it (D-03, D-06)', function () {
    $live = activateExampleFolder();
    $walk = layWalk('tests/price.md', recordPriceWalkText());

    scriptFrontDeskReplies(recordPriceReply());
    fakeClassifier()
        ->queueReadings(['intent' => 'intake', 'confidence' => 0.97, 'yes_no' => ['price' => 0.02]])
        ->queueAnswers(['intent' => 'intake', 'confidence' => 0.93, 'yes_no' => ['promise' => 0.04]]);

    $this->artisan('walks:record', ['walk' => ['tests/price.md']])
        ->expectsOutputToContain('Recorded tests/price.md')
        ->assertExitCode(0);

    $row = WalkRecording::query()->sole();
    $turn = $row->payload['turns']['1'];

    expect($row->walk_path)->toBe('tests/price.md')
        ->and($row->variant)->toBe(WalkRecording::AS_BUILT)
        ->and($row->script_hash)->toBe(WalkFingerprint::scriptHash($walk))
        ->and($row->fingerprint)->toBe(WalkFingerprint::forActiveConfig())
        ->and($row->payload['recorded_with'])->toHaveKeys(['agent', 'jev'])
        ->and($turn['agent'])->toBe([walkReply(recordPriceReply())])
        ->and($turn['readings'])->toHaveCount(1)
        ->and($turn['readings'][0]['customer_message'])->toBe('How much is an AC repair?')
        ->and($turn['readings'][0]['result'])->toHaveKeys(['yes_no', 'intent_confidence'])
        ->and($turn['readings'][0]['result']['intent_confidence'])->toBe(0.97)
        ->and($turn['readings'][0]['result']['yes_no']['price'])->toBe(0.02)
        ->and($turn['readings'][0])->not->toHaveKeys(['sendable', 'reasons'])
        ->and($turn['words'][0]['draft_reply'])->not->toBe('')
        ->and($turn['words'][0]['result']['intent_confidence'])->toBe(0.93)
        ->and($turn['words'][0]['result']['yes_no']['promise'])->toBe(0.04)
        ->and($turn['words'][0]['result'])->not->toHaveKeys(['sendable', 'reasons']);

    $this->artisan('icm:sync')->assertExitCode(0);

    $active = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();

    expect($active->id)->not->toBe($live->id)
        ->and(WalkRecording::query()->count())->toBe(1);
});

it('hands out queued Jev scores in order before the fixed answer (D-03)', function () {
    $classifier = new FakeClassifier;
    $classifier->queueReadings(['intent' => 'scheduling', 'confidence' => 0.9], ['intent' => 'faq', 'confidence' => 0.88])
        ->queueAnswers(['intent' => 'intake', 'confidence' => 0.91, 'yes_no' => ['price' => 0.03]]);

    $questions = walkTriageQuestions();

    $first = $classifier->classify('one', '', $questions);
    $second = $classifier->classify('two', '', $questions);
    $third = $classifier->classify('three', '', $questions);
    $words = $classifier->classify('four', 'a reply', $questions);
    $wordsAfter = $classifier->classify('five', 'another reply', $questions);

    expect([$first->intent, $first->intentConfidence])->toBe(['scheduling', 0.9])
        ->and([$second->intent, $second->intentConfidence])->toBe(['faq', 0.88])
        ->and([$third->intent, $third->intentConfidence])->toBe(['faq', 0.95])
        ->and([$words->intent, $words->intentConfidence, $words->yesNo['price']])->toBe(['intake', 0.91, 0.03])
        ->and([$wordsAfter->intent, $wordsAfter->intentConfidence, $wordsAfter->yesNo['price']])->toBe(['faq', 0.95, 0.01])
        ->and($classifier->allCalls())->toHaveCount(5)
        ->and($classifier->calls())->toHaveCount(2)
        ->and($classifier->readingCalls())->toHaveCount(3);
});
