<?php

use App\Enums\IcmFileKind;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\Loader;
use App\Icm\Walks\WalkCheckKind;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkStepKind;
use App\Models\IcmVersion;

it('compiles every walk file into the candidate (WALK-01)', function () {
    activateExampleFolder();

    writeIcmFile('tests/first.md', minimalWalkFile());
    writeIcmFile('tests/second.md', str_replace('Hi, is anyone there?', 'Are you open today?', minimalWalkFile()));

    $this->artisan('icm:sync')->assertExitCode(0);

    $version = IcmVersion::where('status', VersionStatus::Active->value)->latest('id')->firstOrFail();

    expect($version->compiled['index']['walk_test'])->toBe(['tests/first.md', 'tests/second.md'])
        ->and(collect(app(ActiveConfig::class)->filesOfType('walk_test'))->pluck('path')->all())->toBe(['tests/first.md', 'tests/second.md'])
        ->and(collect($version->notices)->filter(fn (string $notice) => str_contains($notice, 'tests/'))->all())->toBe([]);
});

it('reads a compiled walk file as a WalkFile', function () {
    writeIcmFile('tests/dana.md', <<<'MD'
---
type: walk_test
name: Dana books a visit
persona: Dana, a homeowner whose air conditioner stopped
channel: sms
start: "2026-12-01 10:00"
script:
  - says: "My AC stopped working. Can someone come?"
    expect: [labeled scheduling, sent on its own]
  - books: "2026-12-02 11:00"
    expect: [booked]
  - wait: "2 hours"
expect: [owner alerted, reminder queued]
---
Dana texts in the morning and asks for a visit.
MD);

    $loaded = collect(app(Loader::class)->load(icmPath())->files)->firstWhere('path', 'tests/dana.md');
    $compiled = [...$loaded->frontMatter, 'body' => $loaded->body, 'path' => $loaded->path];

    $walk = WalkFile::fromCompiled($compiled);

    expect($loaded->kind)->toBe(IcmFileKind::Typed)
        ->and($walk->path)->toBe('tests/dana.md')
        ->and($walk->name)->toBe('Dana books a visit')
        ->and($walk->persona)->toBe('Dana, a homeowner whose air conditioner stopped')
        ->and($walk->channel)->toBe('sms')
        ->and($walk->start)->toBe('2026-12-01 10:00')
        ->and(array_map(fn ($step) => $step->turn, $walk->steps))->toBe([1, 2, 3])
        ->and(array_map(fn ($step) => $step->kind, $walk->steps))->toBe([WalkStepKind::Says, WalkStepKind::Books, WalkStepKind::Wait])
        ->and($walk->steps[0]->says)->toBe('My AC stopped working. Can someone come?')
        ->and(array_map(fn ($check) => $check->kind, $walk->steps[0]->checks))->toBe([WalkCheckKind::Labeled, WalkCheckKind::SentOnItsOwn])
        ->and($walk->steps[1]->booksAt)->toBe('2026-12-02 11:00')
        ->and(array_map(fn ($check) => $check->kind, $walk->steps[1]->checks))->toBe([WalkCheckKind::Booked])
        ->and($walk->steps[2]->waitMinutes)->toBe(120)
        ->and(array_map(fn ($check) => $check->kind, $walk->endChecks))->toBe([WalkCheckKind::OwnerAlerted, WalkCheckKind::ReminderQueued])
        ->and($walk->customerTurns())->toBe(['My AC stopped working. Can someone come?']);
});

it('leaves a folder with no walk files as today (edge: empty)', function () {
    $version = activateExampleFolder();

    expect($version->compiled['index']['walk_test'])->toBe([]);
});
