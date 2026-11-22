<?php

use App\Ai\AgentFile;
use App\Ai\FrontDeskPrompt;
use App\Ai\FrontDeskUnavailable;
use App\Ai\ReplayFrontDeskAgent;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\Walks\ScratchWorld;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkOutcome;
use App\Icm\Walks\WalkRunner;
use App\Icm\Walks\WalkTape;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\IcmVersion;
use App\Models\Message;
use App\SendRule\Classifier;
use App\SendRule\ClassifierFailed;
use App\SendRule\ReplayClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/WalkHelpers.php';

/**
 * The files under the owner's records folder, relative, so a test can tell whether a walk left one behind.
 *
 * @return list<string>
 */
function walkRecordFiles(): array
{
    return collect(File::allFiles(icmPath('records')))->map(fn ($file) => $file->getRelativePathname())->sort()->values()->all();
}

it('replays one customer turn in a world of its own (D-01)', function () {
    $compiled = activateExampleFolder()->compiled;
    $walk = walkFileFrom(minimalWalkFile());
    $tape = walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Thanks for texting. What is the AC doing?', 'label' => 'intake_question', 'service_slug' => 'ac-repair'])],
        'readings' => [walkScores()],
        'words' => [walkScores()],
    ]]);

    $inside = [];

    $result = app(WalkRunner::class)->replay($walk, $compiled, $tape, function (ScratchWorld $world) use (&$inside): void {
        $sent = Message::query()->where('direction', MessageDirection::Out)->get();

        $inside = [
            'versions' => IcmVersion::query()->where('status', VersionStatus::Active->value)->count(),
            'allVersions' => IcmVersion::query()->count(),
            'contacts' => Contact::query()->where('phone_e164', WalkRunner::PERSONA_PHONE)->count(),
            'allContacts' => Contact::query()->count(),
            'sentStatuses' => $sent->pluck('status')->all(),
            'sentBody' => (string) $sent->first()?->body,
            'texts' => $world->textChannel()->sent(),
        ];
    });

    expect($result->outcome)->toBe(WalkOutcome::Passed)
        ->and($inside['versions'])->toBe(1)
        ->and($inside['allVersions'])->toBe(1)
        ->and($inside['contacts'])->toBe(1)
        ->and($inside['allContacts'])->toBe(1)
        ->and($inside['sentStatuses'])->toBe([MessageStatus::Sent])
        ->and($inside['sentBody'])->toContain('Thanks for texting. What is the AC doing?')
        ->and($inside['texts'])->toHaveCount(1)
        ->and($inside['texts'][0]['to'])->toBe(WalkRunner::PERSONA_PHONE);
});

it('leaves the live app exactly as it found it after one turn (D-01)', function () {
    $compiled = activateExampleFolder()->compiled;
    $walk = walkFileFrom(minimalWalkFile());
    $tape = walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Thanks for texting. What is the AC doing?', 'label' => 'intake_question', 'service_slug' => 'ac-repair'])],
        'readings' => [walkScores()],
        'words' => [walkScores()],
    ]]);

    Carbon::setTestNow('2026-12-01 09:00:00');

    $counts = liveRowCounts();
    $connection = config('database.default');
    $testNow = Carbon::getTestNow();
    $classifier = app(Classifier::class);
    $path = config('icm.path');
    $records = walkRecordFiles();

    app(WalkRunner::class)->replay($walk, $compiled, $tape);

    expect(liveRowCounts())->toBe($counts)
        ->and(textsSentToCustomers())->toBe([])
        ->and(config('database.default'))->toBe($connection)
        ->and(Carbon::getTestNow())->toEqual($testNow)
        ->and(app(Classifier::class))->toBe($classifier)
        ->and(fakeClassifier())->toBe($classifier)
        ->and(config('icm.path'))->toBe($path)
        ->and(walkRecordFiles())->toBe($records)
        ->and(Http::preventingStrayRequests())->toBeTrue();
});

it('replays raw scores, never verdicts (D-02)', function () {
    $stored = walkScores('intake', 0.97, ['complaint' => 0.10]);
    $tape = walkTape([1 => ['readings' => [$stored]]]);
    $tape->startTurn(1);

    $result = (new ReplayClassifier($tape))->classify('My AC stopped', '', walkTriageQuestions());

    expect($result->toArray())->toBe($stored);
});

/**
 * A walk that names a Tuesday morning start, so every case runs while the shop is open, with the customer texting once
 * for each text given.
 *
 * @param  list<string>  $texts
 */
function replayWalk(array $texts): WalkFile
{
    $items = implode("\n", array_map(fn (string $text): string => "  - says: \"{$text}\"", $texts));

    return walkFileFrom("---\ntype: walk_test\nname: A recorded walk\npersona: A customer who texts\nchannel: sms\nstart: \"2026-12-01 10:00\"\nscript:\n{$items}\nexpect: [sent on its own]\n---\nA recorded walk.\n");
}

/**
 * One recorded turn that asks an intake question, with Jev's scores for the reading and the words check.
 *
 * @param  array<string, float>  $yesNo
 * @return array{agent: list<array<string, mixed>>, readings: list<array<string, mixed>>, words: list<array<string, mixed>>}
 */
function replayTurn(string $words = 'Thanks for texting. What is the AC doing?', array $yesNo = []): array
{
    return [
        'agent' => [walkReply(['reply' => $words, 'label' => 'intake_question', 'service_slug' => 'ac-repair'])],
        'readings' => [walkScores('intake', 0.97, $yesNo)],
        'words' => [walkScores('intake', 0.97, $yesNo)],
    ];
}

it('applies the current bars to recorded scores (D-02)', function () {
    $compiled = activateExampleFolder()->compiled;
    $payload = walkTape([1 => replayTurn(yesNo: ['complaint' => 0.10])])->toPayload();
    $sentAgentReplies = [];

    foreach (['shipped bars' => 0.15, 'tightened bar' => 0.05] as $label => $bar) {
        config(['gate.classifier.complaint_max_yes' => $bar]);

        app(WalkRunner::class)->replay(replayWalk(['My AC stopped working']), $compiled, WalkTape::fromPayload($payload), function () use (&$sentAgentReplies, $label): void {
            $sentAgentReplies[$label] = Message::query()->where('kind', MessageKind::AgentReply)->where('status', MessageStatus::Sent)->count();
        });
    }

    expect($sentAgentReplies)->toBe(['shipped bars' => 1, 'tightened bar' => 0]);
});

it('refuses as needing a new recording when a model reply is missing (D-27)', function () {
    $compiled = activateExampleFolder()->compiled;
    $tape = walkTape([
        1 => replayTurn('Thanks for texting. What is the AC doing?'),
        2 => ['readings' => [walkScores()], 'words' => [walkScores()]],
    ]);
    $inside = [];

    $result = app(WalkRunner::class)->replay(replayWalk(['My AC stopped working', 'It is blowing warm air']), $compiled, $tape, function () use (&$inside): void {
        $inside = Message::query()->where('direction', MessageDirection::Out)->pluck('body')->all();
    });

    expect($result->outcome)->toBe(WalkOutcome::NeedsRecording)
        ->and($result->exhausted)->toBe(['turn' => 2, 'needed' => 'model reply'])
        ->and(collect($inside)->filter(fn (string $body) => str_contains($body, 'What is the AC doing?')))->toHaveCount(1);
});

it('refuses as needing a new recording when a Jev reading is missing (D-27)', function () {
    $compiled = activateExampleFolder()->compiled;
    $tape = walkTape([
        1 => ['agent' => [walkReply(['reply' => 'Words recorded for turn one.', 'label' => 'intake_question'])]],
        2 => replayTurn('Words recorded for turn two.'),
    ]);
    $inside = [];
    $agentRepliesSent = null;

    $result = app(WalkRunner::class)->replay(replayWalk(['My AC stopped working', 'It is blowing warm air']), $compiled, $tape, function () use (&$inside, &$agentRepliesSent): void {
        $inside = Message::query()->where('direction', MessageDirection::Out)->pluck('body')->all();
        $agentRepliesSent = Message::query()->where('kind', MessageKind::AgentReply)->where('status', MessageStatus::Sent)->count();
    });

    expect($result->outcome)->toBe(WalkOutcome::NeedsRecording)
        ->and($result->exhausted)->toBe(['turn' => 1, 'needed' => 'Jev reading'])
        ->and(collect($inside)->filter(fn (string $body) => str_contains($body, 'turn two')))->toHaveCount(0)
        ->and($agentRepliesSent)->toBe(0);
});

it('refuses to guess a model reply the tape does not hold, and never reuses another turn (D-27)', function () {
    activateExampleFolder();
    $tape = walkTape([1 => ['agent' => [walkReply(['reply' => 'Words recorded for turn one.'])]]]);
    $model = new ReplayFrontDeskAgent(app(FrontDeskPrompt::class), $tape);
    $agent = AgentFile::forChannel(app(ActiveConfig::class), 'sms');
    $ask = fn () => $model->handle($agent, new Conversation, collect());
    $refusal = 'the recording holds no model reply for this turn';

    $tape->startTurn(1);
    $first = $ask();

    expect($first->reply->reply)->toBe('Words recorded for turn one.')
        ->and($first->provider)->toBe('recorded')
        ->and($ask)->toThrow(FrontDeskUnavailable::class, $refusal)
        ->and($tape->exhausted())->toBe(['turn' => 1, 'needed' => 'model reply']);

    $tape->startTurn(2);

    expect($ask)->toThrow(FrontDeskUnavailable::class, $refusal);
});

it('refuses to guess a Jev reading the tape does not hold, and never reuses another turn (D-27)', function () {
    $tape = walkTape([1 => ['readings' => [walkScores('faq', 0.9)], 'words' => [walkScores('intake', 0.97)]]]);
    $jev = new ReplayClassifier($tape);
    $questions = walkTriageQuestions();
    $reading = fn () => $jev->classify('Hi', '', $questions);
    $wordsCheck = fn () => $jev->classify('Hi', 'Hello there', $questions);
    $refusal = 'the recording holds no Jev reading for this turn';

    $tape->startTurn(1);

    expect($reading()->intent)->toBe('faq')
        ->and($wordsCheck()->intent)->toBe('intake')
        ->and($reading)->toThrow(ClassifierFailed::class, $refusal)
        ->and($tape->exhausted())->toBe(['turn' => 1, 'needed' => 'Jev reading']);

    $tape->startTurn(2);

    expect($reading)->toThrow(ClassifierFailed::class, $refusal)
        ->and($wordsCheck)->toThrow(ClassifierFailed::class, $refusal);
});
