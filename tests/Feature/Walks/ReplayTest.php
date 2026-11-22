<?php

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\VersionStatus;
use App\Icm\Walks\ScratchWorld;
use App\Icm\Walks\WalkOutcome;
use App\Icm\Walks\WalkRunner;
use App\Models\Contact;
use App\Models\IcmVersion;
use App\Models\Message;
use App\SendRule\Classifier;
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
