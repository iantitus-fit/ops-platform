<?php

use App\Icm\ActiveConfig;
use App\SendRule\Classifier;
use App\SendRule\ClassifierFailed;
use App\SendRule\ClassifierResult;
use App\SendRule\FakeClassifier;
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

/*
| Every HTTP status Jev or OpenRouter can answer with when something is wrong (RESEARCH, "Question 3").
*/
dataset('jev error statuses', [401, 429, 500, 524, 529]);

/*
| A 2xx answer that cannot be trusted. Each row is the whole body as text, a `whole_body` to send as it is, or
| the answers to change in a valid body (a null value removes that answer).
*/
dataset('unusable jev answers', [
    'a body that is not JSON' => ['<html>Bad gateway</html>'],
    'a body with no answers' => [['whole_body' => ['model' => 'typesafe/jev-1.13-20260917']]],
    'no price answer' => [['price' => null]],
    'no intent answer' => [['intent' => null]],
    'a choice that was not one of the options' => [['intent' => ['type' => 'choice', 'choice' => 'banana', 'probabilities' => [], 'confidence' => 0.9]]],
    'an intent answer with no confidence' => [['intent' => ['type' => 'choice', 'choice' => 'faq', 'probabilities' => []]]],
    'an intent confidence above 1' => [['intent' => ['type' => 'choice', 'choice' => 'faq', 'probabilities' => [], 'confidence' => 1.5]]],
    'an intent confidence given as text' => [['intent' => ['type' => 'choice', 'choice' => 'faq', 'probabilities' => [], 'confidence' => '0.9']]],
    'a noul value of 1.2' => [['price' => ['type' => 'noul', 'noul' => 1.2]]],
    'a negative noul value' => [['price' => ['type' => 'noul', 'noul' => -0.1]]],
    'a noul value given as text' => [['price' => ['type' => 'noul', 'noul' => '0.1']]],
    'a noul answer with no noul value' => [['price' => ['type' => 'noul']]],
    'an answer of the wrong type' => [['price' => ['type' => 'choice', 'noul' => 0.02]]],
]);

/**
 * Turn a dataset row into the body Http::fake answers with.
 *
 * @param  array<string, mixed>|string  $row
 * @return array<string, mixed>|string
 */
function jevBodyFor(array|string $row): array|string
{
    if (is_string($row)) {
        return $row;
    }

    if (isset($row['whole_body'])) {
        return $row['whole_body'];
    }

    $payload = jevAnswersPayload();

    foreach ($row as $key => $answer) {
        if ($answer === null) {
            unset($payload['answers'][$key]);
        } else {
            $payload['answers'][$key] = $answer;
        }
    }

    return $payload;
}

it('holds the reply on an HTTP error from Jev', function (int $status) {
    Http::fake([JEV_URL => Http::response(['error' => ['code' => $status, 'message' => 'nope']], $status)]);
    config(['services.jev.key' => 'test-key']);

    expect(fn () => app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions()))
        ->toThrow(ClassifierFailed::class);

    Http::assertSentCount(1);
})->with('jev error statuses');

it('holds the reply when the connection to Jev fails', function () {
    Http::fake([JEV_URL => Http::failedConnection()]);
    config(['services.jev.key' => 'test-key']);

    expect(fn () => app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions()))
        ->toThrow(ClassifierFailed::class);
});

it('does not put the key in the failure it raises', function () {
    Http::fake([JEV_URL => Http::response('nope', 401)]);
    config(['services.jev.key' => 'sk-secret-value']);

    try {
        app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions());
    } catch (ClassifierFailed $exception) {
        expect($exception->getMessage())->not->toContain('sk-secret-value')
            ->and($exception->getMessage())->toContain('401');

        return;
    }

    $this->fail('Expected ClassifierFailed.');
});

it('holds the reply on a 200 that cannot be trusted', function (array|string $row) {
    Http::fake([JEV_URL => Http::response(jevBodyFor($row), 200)]);
    config(['services.jev.key' => 'test-key']);

    expect(fn () => app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions()))
        ->toThrow(ClassifierFailed::class);
})->with('unusable jev answers');

it('names the question that was missing from the answers', function () {
    Http::fake([JEV_URL => Http::response(jevBodyFor(['price' => null]), 200)]);
    config(['services.jev.key' => 'test-key']);

    expect(fn () => app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions()))
        ->toThrow(ClassifierFailed::class, 'price');
});

it('sends nothing when the key is blank', function () {
    Http::fake([JEV_URL => Http::response(jevAnswersPayload(), 200)]);
    config(['services.jev.key' => '']);

    expect(fn () => app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions()))
        ->toThrow(ClassifierFailed::class);

    Http::assertNothingSent();
});

it('sends nothing when the model is jev-latest', function () {
    Http::fake([JEV_URL => Http::response(jevAnswersPayload(), 200)]);
    config(['services.jev.key' => 'test-key', 'services.jev.model' => 'typesafe/jev-latest']);

    expect(fn () => app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions()))
        ->toThrow(ClassifierFailed::class);

    Http::assertNothingSent();
});

it('accepts answers that leave out the type when their shape is right', function () {
    $payload = jevAnswersPayload();

    foreach ($payload['answers'] as $key => $answer) {
        unset($payload['answers'][$key]['type']);
    }

    Http::fake([JEV_URL => Http::response($payload, 200)]);
    config(['services.jev.key' => 'test-key']);

    $result = app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions());

    expect($result->intent)->toBe('price_question')
        ->and($result->yesNo['price'])->toBe(0.02);
});

it('accepts a noul value of exactly 0 and exactly 1', function () {
    $payload = jevAnswersPayload();
    $payload['answers']['price']['noul'] = 0;
    $payload['answers']['promise']['noul'] = 1;
    Http::fake([JEV_URL => Http::response($payload, 200)]);
    config(['services.jev.key' => 'test-key']);

    $result = app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions());

    expect($result->yesNo['price'])->toBe(0.0)
        ->and($result->yesNo['promise'])->toBe(1.0);
});

it('still sends other first when the owner lists it last', function () {
    $other = "  - key: other\n    means: \"Anything else\"\n    sendable: false\n";
    $lastIntent = "    means: \"Describes something urgent or dangerous\"\n    sendable: false\n";

    breakFile('rules/triage.md', fn (string $text) => str_replace($lastIntent, $lastIntent.$other, str_replace($other, '', $text)));
    Http::fake([JEV_URL => Http::response(jevAnswersPayload(), 200)]);
    config(['services.jev.key' => 'test-key']);

    app(JevClassifier::class)->classify('Hi', 'Hello', exampleTriageQuestions());

    Http::assertSent(fn (Request $request) => array_key_first($request->data()['questions']['intent']['criteria']) === 'other'
        && array_key_last($request->data()['questions']['intent']['criteria']) === 'emergency');
});

it('gives every Feature test a scripted classifier that can be sent', function () {
    $classifier = app(Classifier::class);

    expect($classifier)->toBeInstanceOf(FakeClassifier::class)
        ->and(fakeClassifier())->toBe($classifier);

    $result = $classifier->classify('Is anyone free Tuesday?', 'Yes, we can come Tuesday.', exampleTriageQuestions());

    expect($result->model)->toBe('fake-jev')
        ->and($result->intent)->toBe('faq')
        ->and($result->intentConfidence)->toBe(0.95)
        ->and($result->yesNo)->toBe([
            'price' => 0.01,
            'promise' => 0.01,
            'discount' => 0.01,
            'complaint' => 0.01,
            'life_safety' => 0.01,
            'asks_for_person' => 0.01,
        ]);
});

it('lets a test script the fake answer and read back what it was asked', function () {
    fakeClassifier()->answer('price_question', 0.7, ['price' => 0.9]);

    $result = app(Classifier::class)->classify('How much?', 'About $90.', exampleTriageQuestions());

    expect($result->intent)->toBe('price_question')
        ->and($result->intentConfidence)->toBe(0.7)
        ->and($result->yesNo['price'])->toBe(0.9)
        ->and($result->yesNo['promise'])->toBe(0.01)
        ->and(fakeClassifier()->calls())->toBe([['customer_message' => 'How much?', 'draft_reply' => 'About $90.']]);
});

it('lets a test make the fake fail like Jev would', function () {
    fakeClassifier()->failWith('down');

    expect(fn () => app(Classifier::class)->classify('Hi', 'Hello', exampleTriageQuestions()))
        ->toThrow(ClassifierFailed::class, 'down');

    expect(fakeClassifier()->calls())->toHaveCount(1);
});

it('uses the real classifier outside tests', function () {
    $this->app->forgetInstance(Classifier::class);

    expect(app(Classifier::class))->toBeInstanceOf(JevClassifier::class);
});
