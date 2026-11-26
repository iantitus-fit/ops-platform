<?php

use App\Enums\AlertReason;
use App\Enums\AppointmentState;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\FolderSync;
use App\Icm\Walks\ScratchWorld;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkFingerprint;
use App\Icm\Walks\WalkOutcome;
use App\Icm\Walks\WalkRunner;
use App\Models\Appointment;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\IcmVersion;
use App\Models\OwnerAlert;
use App\Records\CustomerCard;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

require_once __DIR__.'/WalkHelpers.php';

/**
 * The example business's own Dana file as the runner plays it: the walk and the candidate's compiled copy, read from the
 * folder on disk the way a sync reads it.
 *
 * @return array{0: WalkFile, 1: array<string, mixed>}
 */
function danaWalkAndCandidate(): array
{
    $candidate = app(FolderSync::class)->candidateForWalks();

    expect($candidate->errors)->toBe([]);

    $file = (array) $candidate->compiled['files']['tests/dana.md'];

    return [
        WalkFile::fromCompiled([...(array) $file['front_matter'], 'body' => (string) $file['body'], 'path' => 'tests/dana.md']),
        $candidate->compiled,
    ];
}

it('Dana goes from her first text to a booked visit (WALK-03 as a feature test, D-08)', function () {
    activateExampleFolder();
    layExampleWalks('dana');
    danaFakes();

    [$walk, $compiled] = danaWalkAndCandidate();
    $files = new Filesystem;
    $seen = [];

    // The checks below read the walk's own world while it is open, so every row and file is the walk's, never a live one.
    $result = app(WalkRunner::class)->record($walk, $compiled, function (ScratchWorld $world) use (&$seen, $files): void {
        $persona = Contact::query()->where('phone_e164', WalkRunner::PERSONA_PHONE)->sole();
        $card = $world->recordsPath().'/records/customers/'.app(CustomerCard::class)->filename($persona);
        $today = $world->recordsPath().'/records/today.md';

        $seen = [
            'appointments' => Appointment::query()->get()->map(fn (Appointment $visit): array => [
                'contact' => $visit->contact_id,
                'service' => $visit->service_slug,
                'starts' => $visit->starts_at->clone()->setTimezone('America/Chicago')->format('Y-m-d H:i'),
                'state' => $visit->state,
            ])->all(),
            'persona' => $persona->id,
            'alerts' => OwnerAlert::query()->get()->map(fn (OwnerAlert $alert): AlertReason => $alert->reason)->all(),
            'card' => $files->exists($card) ? $files->get($card) : null,
            'today' => $files->exists($today) ? $files->get($today) : null,
            'reminders' => BookingSend::query()->where('lead_minutes', '>', 0)->whereNull('sent_at')->pluck('lead_minutes')->all(),
        ];
    });

    expect($seen['appointments'])->toBe([
        ['contact' => $seen['persona'], 'service' => 'ac-repair', 'starts' => '2026-12-02 11:00', 'state' => AppointmentState::Booked],
    ])
        ->and($seen['alerts'])->toBe([AlertReason::AppointmentBooked])
        ->and($seen['card'])->toContain('AC repair')->toContain('Wednesday, December 2, 2026')
        ->and($seen['reminders'])->toBe([1440])
        ->and($seen['today'])->toContain('11:00')->toContain('AC repair')
        ->and($result->outcome)->toBe(WalkOutcome::Passed);
});

it('leaves walk files out of every test copy of the example folder (T1)', function () {
    expect(base_path('examples/example-hvac/tests/dana.md'))->toBeFile()
        ->and(array_map(fn (SplFileInfo $file): string => $file->getFilename(), File::files(icmPath('tests'))))->toBe(['README.md']);

    $version = activateExampleFolder();

    expect($version)->toBeInstanceOf(IcmVersion::class)
        ->and($version->file_count)->toBe(exampleFolderFileCount())
        ->and(array_map(fn (SplFileInfo $file): string => $file->getFilename(), File::files(icmPath('tests'))))->toBe(['README.md']);
});

it('keeps Dana fictional and plain (D-12)', function () {
    $text = File::get(base_path('examples/example-hvac/tests/dana.md'));

    expect($text)->toContain('name: Dana end to end')
        ->toContain('persona: Dana Whitfield')
        ->toContain('start: "2026-12-01 10:00"')
        ->not->toContain('!')
        ->not->toContain('—')
        ->not->toContain('555')
        ->not->toMatch('/\d{3}[\s.-]\d{4}/');
});

/**
 * The newest changelog entry of this test's folder without its time, so a test can read it as "Live as v2 (30 files)" or
 * "Rejected\n- line".
 */
function danaNewestChangelogEntry(): string
{
    $entries = preg_split('/^(?=## )/m', File::get(icmPath('changelog.md'))) ?: [];
    $entries = array_values(array_filter(array_map(rtrim(...), $entries), fn (string $entry): bool => str_starts_with($entry, '## ')));

    return preg_replace('/^## \S+ \S+ · /', '', $entries[0]);
}

/**
 * Dana's committed recording fixture as decoded JSON.
 *
 * @return array<string, mixed>
 */
function danaFixture(): array
{
    return json_decode(File::get(base_path('tests/Fixtures/walks/dana.json')), true, 512, JSON_THROW_ON_ERROR);
}

it('Dana passes at the gate after walks:record (WALK-03 as a walk test)', function () {
    $live = activateExampleFolder();
    layExampleWalks('dana');
    danaFakes();

    $this->artisan('walks:record', ['walk' => ['tests/dana.md']])->assertExitCode(0);
    $this->artisan('icm:sync')->assertExitCode(0);

    $active = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();
    $entry = danaNewestChangelogEntry();

    expect($active->id)->not->toBe($live->id)
        ->and($entry)->toStartWith("Live as {$active->label()} (".(exampleFolderFileCount() + 1).' files)')
        ->and($entry)->not->toContain('is stale');
});

it("replays Dana's committed fixture and goes live (Ian's D-02 ruling)", function () {
    $live = activateExampleFolder();
    layExampleWalks('dana');
    seedRecordingFromFixture('dana');

    $this->artisan('icm:sync')->assertExitCode(0);

    $active = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();

    expect($active->id)->not->toBe($live->id)
        ->and(danaNewestChangelogEntry())->toStartWith("Live as {$active->label()} (".(exampleFolderFileCount() + 1).' files)');
});

it("matches the fixture to Dana's customer turns, so a changed turn fails the suite (D-05 in CI)", function () {
    $walk = walkFileFrom(File::get(base_path('examples/example-hvac/tests/dana.md')), 'tests/dana.md');
    $fixture = danaFixture();

    expect($fixture['walk_path'])->toBe('tests/dana.md')
        ->and($fixture['script_hash'])->toBe(WalkFingerprint::scriptHash($walk))
        ->and($fixture['source'])->toBeIn(['placeholder', 'live']);
});

it("applies today's bars to the fixture (Ian's D-02 ruling)", function () {
    $live = activateExampleFolder();
    layExampleWalks('dana');
    seedRecordingFromFixture('dana');

    config(['gate.classifier.complaint_max_yes' => 0.02]);

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["tests/dana.md, turn 1: the reply was held for the owner, not sent on its own. {$live->label()} stays live."])
        ->and(app(ActiveConfig::class)->version()->id)->toBe($live->id);
});

it('refuses Dana when the folder has no Wednesday opening and keeps the live version live (criterion 2)', function () {
    $live = activateExampleFolder();
    layExampleWalks('dana');
    seedRecordingFromFixture('dana');

    breakFile('business/hours.md', fn (string $text) => str_replace('wed: {open: "08:00", close: "17:00"}', 'wed: closed', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["tests/dana.md, turn 4: Wednesday, December 2 at 11:00 AM was not offered. {$live->label()} stays live."])
        ->and(app(ActiveConfig::class)->version()->id)->toBe($live->id);
});
