<?php

use App\Enums\IcmFileKind;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\Loader;
use App\Icm\WalkFileChecks;
use App\Icm\Walks\WalkCheckKind;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkStepKind;
use App\Models\IcmVersion;

require_once __DIR__.'/../Walks/WalkHelpers.php';

it('compiles every walk file into the candidate (WALK-01)', function () {
    activateExampleFolder();

    writeIcmFile('tests/first.md', minimalWalkFile());
    writeIcmFile('tests/second.md', str_replace('Hi, is anyone there?', 'Are you open today?', minimalWalkFile()));

    seedRecording(walkFileFrom(minimalWalkFile(), 'tests/first.md'), walkTape([1 => ['agent' => [walkReply()], 'readings' => [walkScores()], 'words' => [walkScores()]]]));
    seedRecording(walkFileFrom(str_replace('Hi, is anyone there?', 'Are you open today?', minimalWalkFile()), 'tests/second.md'), walkTape([1 => ['agent' => [walkReply()], 'readings' => [walkScores()], 'words' => [walkScores()]]]));

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

/**
 * A walk file that is valid as written: a start, a customer text, a booking at the earliest time offered, a wait, a
 * booking at a named time and an end check. A row of the refusal dataset changes it with exact text swaps.
 *
 * @param  array<string, string>  $swaps  Text to find and the text to put in its place.
 */
function walkFileDana(array $swaps = []): string
{
    $text = <<<'MD'
---
type: walk_test
name: Dana books a visit
persona: Dana, a homeowner whose air conditioner stopped
channel: sms
start: "2026-12-01 10:00"
script:
  - says: "My AC stopped working. Can someone come?"
    expect: [sent on its own]
  - books: the earliest time offered
    expect: [booked]
  - wait: "2 hours"
    expect: [owner alerted]
  - books: "2026-12-02 11:00"
expect: [records updated]
---
Dana texts in the morning and asks for a visit.
MD;

    foreach ($swaps as $search => $replace) {
        expect($text)->toContain((string) $search);

        $text = str_replace((string) $search, $replace, $text);
    }

    return $text;
}

/**
 * Each row: the walk file text and the one line the owner must see. The refusal test and the wording test both read this.
 *
 * @return array<string, array{string, string}>
 */
function walkFileRefusals(): array
{
    $saysList = "sent on its own, held for the owner, handed to the owner, labeled <label>, carries the owner's price sentence for <service slug>, carries the booking link, carries the visit line, owner alerted";
    $outcomeList = 'owner alerted, booked, reminder queued, card updated, records updated';
    $noStartTail = 'but the walk has no start. Name a start for the walk, or word the check as the earliest time offered.';

    return [
        'name missing' => [walkFileDana(["name: Dana books a visit\n" => '']), 'tests/dana.md: name is missing, and it is required'],
        'persona missing' => [walkFileDana(["persona: Dana, a homeowner whose air conditioner stopped\n" => '']), 'tests/dana.md: persona is missing, and it is required'],
        'channel voice' => [walkFileDana(['channel: sms' => 'channel: voice']), 'tests/dana.md: channel must be one of sms (you wrote voice)'],
        'start not a date' => [walkFileDana(['start: "2026-12-01 10:00"' => 'start: "tomorrow"']), 'tests/dana.md: start must be a date and time in quotes such as 2026-12-01 10:00 (you wrote tomorrow)'],
        'start not a real date' => [walkFileDana(['start: "2026-12-01 10:00"' => 'start: "2026-02-30 10:00"']), 'tests/dana.md: start must be a date and time in quotes such as 2026-12-01 10:00 (you wrote 2026-02-30 10:00)'],
        'script missing' => [walkFileDana(["script:\n  - says: \"My AC stopped working. Can someone come?\"\n    expect: [sent on its own]\n  - books: the earliest time offered\n    expect: [booked]\n  - wait: \"2 hours\"\n    expect: [owner alerted]\n  - books: \"2026-12-02 11:00\"\n" => '']), 'tests/dana.md: script is missing, and it is required'],
        'script without a says item' => [walkFileDana(["  - says: \"My AC stopped working. Can someone come?\"\n    expect: [sent on its own]\n" => "  - wait: \"1 hour\"\n"]), 'tests/dana.md: script must list at least one says item'],
        'script with 31 items' => [
            "---\ntype: walk_test\nname: Too long\npersona: A very patient customer\nchannel: sms\nscript:\n".str_repeat("  - says: \"Hello\"\n", 31)."expect: [owner alerted]\n---\nBody.",
            'tests/dana.md: script has 31 items; a walk can have at most 30',
        ],
        'item with says and wait' => [walkFileDana(["  - books: the earliest time offered\n    expect: [booked]\n" => "  - says: \"Hello\"\n    wait: \"1 hour\"\n"]), 'tests/dana.md: script item 2 must have exactly one of says, wait or books'],
        'item with none of says, wait or books' => [walkFileDana(["  - books: the earliest time offered\n    expect: [booked]\n" => "  - say: \"Hello\"\n"]), 'tests/dana.md: script item 2 must have exactly one of says, wait or books'],
        'says too long' => [walkFileDana(['"My AC stopped working. Can someone come?"' => '"'.str_repeat('a', 1601).'"']), 'tests/dana.md: script item 1 says is too long; a text can have at most 1600 characters'],
        'wait not a duration' => [walkFileDana(['wait: "2 hours"' => 'wait: "soon"']), 'tests/dana.md: script item 3 wait must be a number of minutes, hours or days such as 2 hours (you wrote soon)'],
        'wait over 14 days' => [walkFileDana(['wait: "2 hours"' => 'wait: "20 days"']), 'tests/dana.md: script item 3 wait must be 14 days or less (you wrote 20 days)'],
        'books not a time' => [walkFileDana(['books: "2026-12-02 11:00"' => 'books: whenever']), 'tests/dana.md: script item 4 books must be the earliest time offered or a date and time such as 2026-12-02 11:00 (you wrote whenever)'],
        'sends is not a check' => [walkFileDana(['expect: [sent on its own]' => 'expect: [sends]']), "tests/dana.md: script item 1 expect item 1 must be one of {$saysList} (you wrote sends)"],
        'booked after a customer text' => [walkFileDana(['expect: [sent on its own]' => 'expect: [booked]']), "tests/dana.md: script item 1 expect item 1 must be one of {$saysList} (you wrote booked)"],
        'a customer text check after a booking' => [walkFileDana(['expect: [booked]' => 'expect: [sent on its own]']), "tests/dana.md: script item 2 expect item 1 must be one of {$outcomeList} (you wrote sent on its own)"],
        'an end check not in the list' => [walkFileDana(['expect: [records updated]' => 'expect: [sent on its own]']), "tests/dana.md: expect item 1 must be one of {$outcomeList} (you wrote sent on its own)"],
        'an end check that is not a list' => [walkFileDana(['expect: [records updated]' => 'expect: records updated']), 'tests/dana.md: expect must be a list (you wrote records updated)'],
        'no check anywhere' => [walkFileDana(["    expect: [sent on its own]\n" => '', "    expect: [booked]\n" => '', "    expect: [owner alerted]\n" => '', "expect: [records updated]\n" => '']), 'tests/dana.md: the walk states no check; add one under expect or after a turn'],
        'a booking at a named time with no start' => [walkFileDana(["start: \"2026-12-01 10:00\"\n" => '']), "tests/dana.md: script item 4 books names a time (2026-12-02 11:00) {$noStartTail}"],
        'an end check naming a time with no start' => [walkFileDana(["start: \"2026-12-01 10:00\"\n" => '', "  - books: \"2026-12-02 11:00\"\n" => '', 'expect: [records updated]' => 'expect: [booked by 3pm]']), "tests/dana.md: expect names a time (3pm) {$noStartTail}"],
        'a check under an item naming a time with no start' => [walkFileDana(["start: \"2026-12-01 10:00\"\n" => '', "  - books: \"2026-12-02 11:00\"\n" => '', 'expect: [booked]' => 'expect: [booked on friday]']), "tests/dana.md: script item 2 expect names a time (friday) {$noStartTail}"],
    ];
}

it('refuses a broken walk file in plain words naming the file and the field', function (string $text, string $line) {
    writeIcmFile('tests/dana.md', $text);

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line]);
})->with(fn () => walkFileRefusals());

it('keeps exclamation points and em dashes out of every refusal', function () {
    foreach (walkFileRefusals() as $name => [, $line]) {
        expect($line)->not->toContain('!')
            ->and($line)->not->toContain("\u{2014}");
    }
});

it('gives no error for a books item that names a time when the walk has a start', function () {
    writeIcmFile('tests/dana.md', walkFileDana());

    $files = app(Loader::class)->load(icmPath())->files;

    expect((new WalkFileChecks)->errors($files))->toBe([]);
});

it('does not read a service slug that looks like a day as a time', function () {
    writeIcmFile('tests/dana.md', walkFileDana([
        "start: \"2026-12-01 10:00\"\n" => '',
        "  - books: \"2026-12-02 11:00\"\n" => '',
        'expect: [sent on its own]' => "expect: [carries the owner's price sentence for sat-repair]",
    ]));

    $files = app(Loader::class)->load(icmPath())->files;

    expect((new WalkFileChecks)->errors($files))->toBe([]);
});

it('refuses a folder with more than 50 walk files', function () {
    foreach (range(1, 51) as $number) {
        writeIcmFile(sprintf('tests/walk-%02d.md', $number), minimalWalkFile());
    }

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['tests/walk-51.md: the folder holds 51 walk tests and a sync runs at most 50; remove one']);
});

it('echoes an owner value on one line', function () {
    writeIcmFile('tests/dana.md', walkFileDana(['channel: sms' => 'channel: "voice\nSTATUS: fine"']));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['tests/dana.md: channel must be one of sms (you wrote voice STATUS: fine)']);
});
