<?php

use App\Enums\VersionStatus;
use App\Icm\FolderSync;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkOutcome;
use App\Icm\Walks\WalkResult;
use App\Icm\Walks\WalkRunner;
use App\Icm\Walks\WalkTape;
use App\Icm\Walks\WalkTurnObservation;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

require_once __DIR__.'/WalkHelpers.php';

/**
 * Replay one shipped walk from its committed fixture against the folder this test copied and laid the walk into, the way
 * the gate does, and return what the runner saw. The candidate is read from the folder on disk, so it is the walk's own
 * file that is played.
 */
function exampleWalksReplay(string $name): WalkResult
{
    $candidate = app(FolderSync::class)->candidateForWalks();

    expect($candidate->errors)->toBe([]);

    $file = (array) $candidate->compiled['files']["tests/{$name}.md"];
    $walk = WalkFile::fromCompiled([...(array) $file['front_matter'], 'body' => (string) $file['body'], 'path' => "tests/{$name}.md"]);
    $fixture = json_decode(File::get(base_path("tests/Fixtures/walks/{$name}.json")), true, 512, JSON_THROW_ON_ERROR);

    return app(WalkRunner::class)->replay($walk, $candidate->compiled, WalkTape::fromPayload($fixture['payload']));
}

/**
 * What the runner saw at one step of a replayed walk, counting steps from 1 as the walk file does.
 */
function exampleWalksTurn(WalkResult $result, int $turn): WalkTurnObservation
{
    $seen = collect($result->turns)->firstWhere('turn', $turn);

    expect($seen)->toBeInstanceOf(WalkTurnObservation::class);

    return $seen;
}

/**
 * The newest changelog entry of this test's folder without its time, so a test can read it as "Live as v2 (38 files)".
 */
function exampleWalksNewestChangelogEntry(): string
{
    $entries = preg_split('/^(?=## )/m', File::get(icmPath('changelog.md'))) ?: [];
    $entries = array_values(array_filter(array_map(rtrim(...), $entries), fn (string $entry): bool => str_starts_with($entry, '## ')));

    return preg_replace('/^## \S+ \S+ · /', '', $entries[0]);
}

it('ships the D-12 walk set', function () {
    $shipped = array_map(fn (SplFileInfo $file): string => $file->getFilename(), File::files(base_path('examples/example-hvac/tests')));
    $expected = ['README.md', ...array_map(fn (string $name): string => "{$name}.md", exampleWalkNames())];

    sort($shipped);
    sort($expected);

    expect($shipped)->toBe($expected)
        ->and(exampleWalkNames())->toHaveCount(9);
});

it('every shipped walk passes the gate from its fixture (D-12)', function () {
    $live = activateExampleFolder();
    layExampleWalks(...exampleWalkNames());

    foreach (exampleWalkNames() as $name) {
        seedRecordingFromFixture($name);
    }

    $this->artisan('icm:sync')->assertExitCode(0);

    $active = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();
    $entry = exampleWalksNewestChangelogEntry();

    expect(IcmVersion::query()->where('status', VersionStatus::Rejected->value)->count())->toBe(0)
        ->and($active->id)->not->toBe($live->id)
        ->and($entry)->toStartWith("Live as {$active->label()} (".(exampleFolderFileCount() + count(exampleWalkNames())).' files)')
        ->and($entry)->not->toContain('is stale');
});

it('the booked customer\'s time question sends on its own with the visit line (D-19)', function () {
    activateExampleFolder();
    layExampleWalks('booked-time-question');

    $result = exampleWalksReplay('booked-time-question');
    $asked = exampleWalksTurn($result, 5);

    expect($asked->sentOnItsOwn)->toBeTrue()
        ->and($asked->label)->toBe('scheduling')
        ->and($asked->parts)->toContain('visit_line')
        ->and($asked->parts)->not->toContain('booking_link')
        ->and($result->outcome)->toBe(WalkOutcome::Passed);
});

it('both link requests send on their own with the booking link (D-19)', function (int $turn) {
    activateExampleFolder();
    layExampleWalks('link-again');

    $result = exampleWalksReplay('link-again');
    $asked = exampleWalksTurn($result, $turn);

    expect($asked->sentOnItsOwn)->toBeTrue()
        ->and($asked->label)->toBe('scheduling')
        ->and($asked->parts)->toContain('booking_link')
        ->and($result->outcome)->toBe(WalkOutcome::Passed);
})->with([
    'the lost-link text' => [4],
    'the link request' => [5],
]);

it('the risky walks hold the reply for the owner (D-12)', function (string $name) {
    activateExampleFolder();
    layExampleWalks($name);

    $result = exampleWalksReplay($name);
    $asked = exampleWalksTurn($result, 1);

    expect($asked->heldForOwner)->toBeTrue()
        ->and($asked->sentOnItsOwn)->toBeFalse()
        ->and($result->outcome)->toBe(WalkOutcome::Passed);
})->with(['price-question', 'discount-question', 'promise-question', 'complaint']);

it('the price walk also sends the owner\'s own price sentence (D-12)', function () {
    activateExampleFolder();
    layExampleWalks('price-question');

    $result = exampleWalksReplay('price-question');

    expect(exampleWalksTurn($result, 1)->sentText)->toContain('$')
        ->and($result->outcome)->toBe(WalkOutcome::Passed);
});

it('the complaint walk alerts the owner (D-12)', function () {
    activateExampleFolder();
    layExampleWalks('complaint');

    $result = exampleWalksReplay('complaint');

    expect(exampleWalksTurn($result, 1)->ownerAlerted)->toBeTrue()
        ->and($result->outcome)->toBe(WalkOutcome::Passed);
});

it('the person walk hands the conversation to the owner and alerts them (D-12)', function () {
    activateExampleFolder();
    layExampleWalks('asks-for-person');

    $result = exampleWalksReplay('asks-for-person');
    $asked = exampleWalksTurn($result, 1);

    expect($asked->handedToOwner)->toBeTrue()
        ->and($asked->heldForOwner)->toBeFalse()
        ->and($asked->ownerAlerted)->toBeTrue()
        ->and($result->outcome)->toBe(WalkOutcome::Passed);
});
