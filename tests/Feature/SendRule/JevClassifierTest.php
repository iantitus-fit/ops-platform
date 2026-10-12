<?php

use App\Icm\ActiveConfig;
use App\SendRule\ClassifierFailed;
use App\SendRule\ClassifierResult;
use App\SendRule\JevClassifier;
use App\SendRule\TriageQuestions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const JEV_URL = 'openrouter.ai/api/v1/systemone';

/**
 * A valid Jev answer for the example folder's triage questions.
 *
 * @param  array<string, mixed>  $overrides  Merged over the top-level response (model, answers).
 * @return array<string, mixed>
 */
function jevAnswersPayload(array $overrides = []): array
{
    return [
        'model' => 'typesafe/jev-1.13-20260917',
        'answers' => [
            'intent' => [
                'type' => 'choice',
                'choice' => 'price_question',
                'probabilities' => [
                    'other' => 0.04,
                    'scheduling' => 0.02,
                    'intake' => 0.02,
                    'faq' => 0.02,
                    'price_question' => 0.88,
                    'complaint' => 0.01,
                    'emergency' => 0.01,
                ],
                'confidence' => 0.86,
            ],
            'price' => ['type' => 'noul', 'noul' => 0.02],
            'promise' => ['type' => 'noul', 'noul' => 0.03],
            'discount' => ['type' => 'noul', 'noul' => 0.01],
            'complaint' => ['type' => 'noul', 'noul' => 0.04],
            'life_safety' => ['type' => 'noul', 'noul' => 0.0],
            'asks_for_person' => ['type' => 'noul', 'noul' => 0.05],
        ],
        'usage' => ['total_tokens' => 120],
        ...$overrides,
    ];
}

function exampleTriageQuestions(): TriageQuestions
{
    activateExampleFolder();

    return TriageQuestions::fromConfig(app(ActiveConfig::class)->triage());
}

it('puts the owner triage file in the live folder', function () {
    $this->artisan('icm:sync')
        ->expectsOutputToContain('('.exampleFolderFileCount().' files)')
        ->assertExitCode(0);

    expect(exampleFolderFileCount())->toBe(11)
        ->and(array_column(app(ActiveConfig::class)->files(), 'path'))->toContain('rules/triage.md');
});

it('reads the triage questions with other first and only three sendable intents', function () {
    $questions = exampleTriageQuestions();

    expect(array_key_first($questions->intents))->toBe('other')
        ->and(array_keys($questions->intents))->toBe(['other', 'scheduling', 'intake', 'faq', 'price_question', 'complaint', 'emergency'])
        ->and($questions->sendableIntents)->toBe(['scheduling', 'intake', 'faq'])
        ->and(array_keys($questions->yesNo))->toBe(['price', 'promise', 'discount', 'complaint', 'life_safety', 'asks_for_person']);
});

it('reads Jev answers back with the served model', function () {
    Http::fake([JEV_URL => Http::response(jevAnswersPayload(), 200)]);
    config(['services.jev.key' => 'test-key']);

    $result = app(JevClassifier::class)->classify('How much for a new unit?', 'Mike will get back to you.', exampleTriageQuestions());

    expect($result)->toBeInstanceOf(ClassifierResult::class)
        ->and($result->model)->toBe('typesafe/jev-1.13-20260917')
        ->and($result->intent)->toBe('price_question')
        ->and($result->intentConfidence)->toBe(0.86)
        ->and($result->intentProbabilities['price_question'])->toBe(0.88)
        ->and($result->yesNo)->toBe([
            'price' => 0.02,
            'promise' => 0.03,
            'discount' => 0.01,
            'complaint' => 0.04,
            'life_safety' => 0.0,
            'asks_for_person' => 0.05,
        ]);
});

it('sends one request holding only the model, the two texts and the questions', function () {
    Http::fake([JEV_URL => Http::response(jevAnswersPayload(), 200)]);
    config(['services.jev.key' => 'test-key']);

    app(JevClassifier::class)->classify('How much for a new unit?', 'Mike will get back to you.', exampleTriageQuestions());

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return str_ends_with($request->url(), '/systemone')
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && array_keys($body) === ['model', 'state', 'questions']
            && $body['model'] === 'typesafe/jev-1.13'
            && $body['state'] === ['customer_message' => 'How much for a new unit?', 'draft_reply' => 'Mike will get back to you.'];
    });
});

it('asks one choice question with other first and six yes or no questions', function () {
    Http::fake([JEV_URL => Http::response(jevAnswersPayload(), 200)]);
    config(['services.jev.key' => 'test-key']);

    app(JevClassifier::class)->classify('My AC is out', 'Mike will call you.', exampleTriageQuestions());

    Http::assertSent(function (Request $request) {
        $questions = $request->data()['questions'];
        $yesNo = array_diff_key($questions, ['intent' => true]);

        return array_keys($questions)[0] === 'intent'
            && $questions['intent']['type'] === 'choice'
            && array_key_first($questions['intent']['criteria']) === 'other'
            && count($questions['intent']['criteria']) === 7
            && array_keys($yesNo) === ['price', 'promise', 'discount', 'complaint', 'life_safety', 'asks_for_person']
            && collect($yesNo)->every(fn (array $question) => $question['type'] === 'noul' && $question['instructions'] !== '');
    });
});

it('never sends a name, a phone number or a customer card to Jev', function () {
    Http::fake([JEV_URL => Http::response(jevAnswersPayload(), 200)]);
    config(['services.jev.key' => 'test-key']);

    app(JevClassifier::class)->classify('Is anyone free Tuesday?', 'Yes, we can come Tuesday.', exampleTriageQuestions());

    Http::assertSent(function (Request $request) {
        $sent = json_encode($request->data());

        return ! str_contains($sent, '+1202')
            && ! str_contains($sent, 'customer_id')
            && ! str_contains($sent, 'phone')
            && array_keys($request->data()['state']) === ['customer_message', 'draft_reply'];
    });
});

it('refuses a model that moves with new releases', function () {
    Http::fake([JEV_URL => Http::response(jevAnswersPayload(), 200)]);
    config(['services.jev.key' => 'test-key', 'services.jev.model' => '~typesafe/jev-latest']);

    expect(fn () => app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions()))
        ->toThrow(ClassifierFailed::class);

    Http::assertNothingSent();
});

it('pins the model from the environment setting', function () {
    expect(config('services.jev.model'))->toBe('typesafe/jev-1.13')
        ->and(config('services.jev.base_url'))->toBe('https://openrouter.ai/api/v1');
});
