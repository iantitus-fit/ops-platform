<?php

use App\Enums\AlertReason;
use App\Enums\AppointmentState;
use App\Icm\FolderSync;
use App\Icm\Walks\ScratchWorld;
use App\Icm\Walks\WalkFile;
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
