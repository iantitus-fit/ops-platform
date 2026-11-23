<?php

use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\FolderSync;
use App\Icm\SyncResult;
use App\Icm\Walks\WalkRunner;
use App\Icm\Walks\WalkTape;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;

require_once __DIR__.'/../Walks/WalkHelpers.php';

/**
 * A walk where the customer asks the price of an AC repair and the owner expects the owner's own sentence for it.
 */
function gatePriceWalkText(): string
{
    return "---\ntype: walk_test\nname: Dana asks the price\npersona: A homeowner asking about cost\nchannel: sms\nscript:\n  - says: \"How much is an AC repair?\"\n    expect: [carries the owner's price sentence for ac-repair]\n---\nA walk.\n";
}

/**
 * The one recorded reply for the price walk: the model's own words and a flag that the customer asked the price.
 */
function gatePriceTape(): WalkTape
{
    return walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Happy to help with that.', 'label' => 'faq_answer', 'service_slug' => 'ac-repair', 'price_services' => ['ac-repair']])],
        'readings' => [walkScores()],
        'words' => [walkScores()],
    ]]);
}

/**
 * A walk where the customer texts three times, is sent the booking link and books Wednesday, December 2 at 11:00.
 */
function gateBookingWalkText(): string
{
    return <<<'MD'
---
type: walk_test
name: Dana books a visit
persona: A homeowner whose air conditioner stopped
channel: sms
start: "2026-12-01 10:00"
script:
  - says: "My AC is blowing warm air"
    expect: [sent on its own]
  - says: "Not cooling"
    expect: [sent on its own]
  - says: "Over 10 years"
    expect: [sent on its own, labeled scheduling, carries the booking link]
  - books: "2026-12-02 11:00"
expect: [booked]
---
Dana texts three times and books the time offered.
MD;
}

/**
 * Every entry of the temporary folder's changelog.md, newest first, each as its heading and its bullets.
 *
 * @return list<string>
 */
function gateChangelogEntries(): array
{
    $text = File::get(icmPath('changelog.md'));
    $entries = preg_split('/^(?=## )/m', $text) ?: [];

    return array_values(array_filter(array_map(rtrim(...), $entries), fn (string $entry) => str_starts_with($entry, '## ')));
}

/**
 * The newest changelog entry without its time, so a test can read it as "Rejected\n- line\n- line".
 */
function gateNewestEntry(): string
{
    return preg_replace('/^## \S+ \S+ · /', '', gateChangelogEntries()[0]);
}

it('activates a candidate whose walks pass (criterion 3)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape());

    $this->artisan('icm:sync')->assertExitCode(0);

    $active = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();

    expect($active->id)->not->toBe($live->id)
        ->and($live->fresh()->status)->toBe(VersionStatus::Superseded)
        ->and(IcmVersion::query()->count())->toBe(2)
        ->and(gateNewestEntry())->toStartWith("Live as {$active->label()} (".(exampleFolderFileCount() + 1).' files)');
});

it('refuses a price the folder no longer has and keeps the live version live (criterion 2)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape());

    breakFile('business/services/ac-repair.md', fn (string $text) => preg_replace(
        ['/^price_policy: .*$/m', '/^price_cents: .*\n/m', '/^price_sentences:\n  - sentence: .*\n/m'],
        ['price_policy: none', '', ''],
        $text,
    ));

    $this->artisan('icm:sync')->assertExitCode(1);

    $line = "tests/price.md, turn 1: the reply did not carry the price sentence for AC repair. {$live->label()} stays live.";

    expect(app(ActiveConfig::class)->version()->id)->toBe($live->id)
        ->and(rejectedErrors())->toBe([$line])
        ->and(IcmVersion::query()->where('status', VersionStatus::Active->value)->sole()->id)->toBe($live->id)
        ->and(gateNewestEntry())->toBe("Rejected\n- {$line}\n- tests/price.md is stale: its recording is older than the front desk prompt or the triage questions. Run `walks:record tests/price.md` to refresh it.\n- {$live->label()} stays live");
});

it('refuses a time the candidate no longer offers (criterion 2)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/booking.md', gateBookingWalkText()), bookingTape());

    breakFile('business/hours.md', fn (string $text) => str_replace('wed: {open: "08:00", close: "17:00"}', 'wed: closed', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["tests/booking.md, turn 4: Wednesday, December 2 at 11:00 AM was not offered. {$live->label()} stays live."])
        ->and(app(ActiveConfig::class)->version()->id)->toBe($live->id);
});

it('runs no walk when the candidate has none (edge: empty)', function () {
    $live = activateExampleFolder();

    $this->mock(WalkRunner::class)->shouldNotReceive('replay');

    breakFile('business/faq.md', fn (string $text) => $text."\nWe also answer questions about filters.\n");

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(IcmVersion::query()->where('status', VersionStatus::Active->value)->sole()->id)->not->toBe($live->id)
        ->and(IcmVersion::query()->count())->toBe(2);
});

/**
 * A walk where the customer texts twice and each reply should go out on its own. Two customer turns, so a recording can
 * hold the first and miss part of the second.
 */
function gateTwoTurnWalkText(): string
{
    return "---\ntype: walk_test\nname: Two texts\npersona: A customer who texts twice\nchannel: sms\nscript:\n  - says: \"My AC is blowing warm air\"\n    expect: [sent on its own]\n  - says: \"Not cooling\"\n    expect: [sent on its own]\n---\nA walk.\n";
}

/**
 * The one-text walk of the plan's own helpers with a different expectation, written under the path given.
 */
function gateHeldWalkText(): string
{
    return str_replace('expect: [sent on its own]', 'expect: [held for the owner]', minimalWalkFile());
}

/**
 * A recording for gateTwoTurnWalkText() in which the second turn lacks what the $without list names: 'agent' (no model
 * reply) or 'readings' (no reading of the customer's text).
 */
function gateTwoTurnTape(string $without): WalkTape
{
    $turn = fn (string $words): array => [
        'agent' => [walkReply(['reply' => $words, 'label' => 'intake_question', 'service_slug' => 'ac-repair'])],
        'readings' => [walkScores()],
        'words' => [walkScores()],
    ];

    $second = $turn('Thanks. About how old is the system?');
    $second[$without] = [];

    return walkTape([1 => $turn('Thanks for texting. What is the system doing?'), 2 => $second]);
}

/**
 * A fingerprint no candidate has, so the recording made with it is stale.
 */
function gateStaleFingerprint(): string
{
    return str_repeat('0', 64);
}

/**
 * Every line a sync's result, its stored rejected version and changelog.md can show an owner (not the changelog's own).
 *
 * @return list<string>
 */
function gateOwnerFacingLines(SyncResult $result): array
{
    return [...$result->errors, ...(IcmVersion::query()->where('status', VersionStatus::Rejected->value)->get()->flatMap(fn (IcmVersion $version) => $version->errors ?? [])->all())];
}

it('refuses a new walk with no recording and tells only the changelog the command (D-05)', function () {
    $live = activateExampleFolder();

    layWalk('tests/price.md', gatePriceWalkText());

    $result = app(FolderSync::class)->run();
    $owner = "tests/price.md: this walk has no recording yet. Recording it is Ian's step. {$live->label()} stays live.";

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and($result->errors)->toBe([$owner])
        ->and(rejectedErrors())->toBe([$owner])
        ->and(gateNewestEntry())->toBe("Rejected\n- tests/price.md: no recording. Run `walks:record tests/price.md`. {$live->label()} stays live.\n- {$live->label()} stays live")
        ->and(IcmVersion::query()->where('status', VersionStatus::Active->value)->sole()->id)->toBe($live->id)
        ->and(IcmVersion::query()->count())->toBe(2)
        ->and(implode("\n", gateOwnerFacingLines($result)))->not->toContain('walks:record')->not->toContain('stale');
});

it('refuses a walk whose customer turn changed since it was recorded (D-05)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape());

    writeIcmFile('tests/price.md', str_replace('How much is an AC repair?', 'What does an AC repair cost?', gatePriceWalkText()));

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and($result->errors)->toBe(["tests/price.md: this walk has no recording yet. Recording it is Ian's step. {$live->label()} stays live."]);
});

it('keeps a walk recorded when only its checks, persona or name change (D-05)', function () {
    activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape());

    writeIcmFile('tests/price.md', str_replace(['A homeowner asking about cost', 'Dana asks the price'], ['A person asking what repairs cost', 'Price of a repair'], gatePriceWalkText()));

    expect(app(FolderSync::class)->run()->outcome)->toBe(SyncOutcome::Activated);
});

it('refuses a walk the recording no longer answers, fresh or stale alike (D-27)', function (?string $fingerprint) {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/two.md', gateTwoTurnWalkText()), gateTwoTurnTape('agent'), $fingerprint);

    $result = app(FolderSync::class)->run();
    $ian = "tests/two.md: the recording no longer answers this walk (turn 2 needed a model reply it does not hold). Run `walks:record tests/two.md`. {$live->label()} stays live.";

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and($result->errors)->toBe(["tests/two.md: this walk needs a new recording. Recording it is Ian's step. {$live->label()} stays live."])
        ->and(rejectedErrors())->toBe($result->errors)
        ->and(gateNewestEntry())->toStartWith("Rejected\n- {$ian}\n")
        ->and(implode("\n", gateOwnerFacingLines($result)))->not->toContain('walks:record')->not->toContain('stale');
})->with([
    'a fresh recording' => [null],
    'a stale recording' => [gateStaleFingerprint()],
]);

it('says a Jev reading when the recording holds no reading of the customer text (D-27)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/two.md', gateTwoTurnWalkText()), gateTwoTurnTape('readings'));

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and(gateNewestEntry())->toContain("tests/two.md: the recording no longer answers this walk (turn 2 needed a Jev reading it does not hold). Run `walks:record tests/two.md`. {$live->label()} stays live.");
});

it('lets a stale walk go live and tells only the changelog (D-04, rulings 1, 4 and 5)', function () {
    activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape(), gateStaleFingerprint());

    $result = app(FolderSync::class)->run();
    $version = $result->version->fresh();

    expect($result->outcome)->toBe(SyncOutcome::Activated)
        ->and($result->errors)->toBe([])
        ->and($version->errors)->toBeNull()
        ->and(json_encode($version->notices))->not->toContain('stale')->not->toContain('walks:record')
        ->and($result->message)->not->toContain('stale')
        ->and(gateNewestEntry())->toStartWith("Live as {$version->label()} (")
        ->and(gateNewestEntry())->toContain('- tests/price.md is stale: its recording is older than the front desk prompt or the triage questions. It still ran and passed its checks. Run `walks:record tests/price.md` to refresh it.');
});

it('carries a stale note on a rejected entry whose walks ran (ruling 5)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/a-says.md', minimalWalkFile()), walkTape([1 => ['agent' => [walkReply()], 'readings' => [walkScores()], 'words' => [walkScores()]]]), gateStaleFingerprint());
    seedRecording(layWalk('tests/b-held.md', str_replace('Hi, is anyone there?', 'Are you open today?', gateHeldWalkText())), walkTape([1 => ['agent' => [walkReply()], 'readings' => [walkScores()], 'words' => [walkScores()]]]));

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and($result->errors)->toBe(["tests/b-held.md, turn 1: the reply was sent on its own, not held for the owner. {$live->label()} stays live."])
        ->and(gateNewestEntry())->toBe("Rejected\n- tests/b-held.md, turn 1: the reply was sent on its own, not held for the owner. {$live->label()} stays live.\n- tests/a-says.md is stale: its recording is older than the front desk prompt or the triage questions. It still ran and passed its checks. Run `walks:record tests/a-says.md` to refresh it.\n- {$live->label()} stays live");
});

it('names a stale walk that was refused without saying it passed (ruling 5)', function () {
    activateExampleFolder();

    seedRecording(layWalk('tests/b-held.md', gateHeldWalkText()), walkTape([1 => ['agent' => [walkReply()], 'readings' => [walkScores()], 'words' => [walkScores()]]]), gateStaleFingerprint());

    app(FolderSync::class)->run();

    expect(gateNewestEntry())->toContain("- tests/b-held.md is stale: its recording is older than the front desk prompt or the triage questions. Run `walks:record tests/b-held.md` to refresh it.\n")
        ->and(gateNewestEntry())->not->toContain('passed its checks');
});

it('adds no walk line and no stale note when nothing changed (ruling 5, ruling 7)', function () {
    activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape(), gateStaleFingerprint());

    $first = app(FolderSync::class)->run();

    expect(gateNewestEntry())->toContain('is stale');

    $this->mock(WalkRunner::class)->shouldNotReceive('replay');

    $second = app(FolderSync::class)->run();

    expect($second->outcome)->toBe(SyncOutcome::Unchanged)
        ->and(gateNewestEntry())->toBe("No changes\n- Already active: {$first->version->label()}");
});

it('adds no walk line and no stale note when validation fails (D-11, ruling 5)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape(), gateStaleFingerprint());

    $this->mock(WalkRunner::class)->shouldNotReceive('replay');

    breakFile('business/services/ac-repair.md', fn (string $text) => str_replace("name: AC repair\n", '', $text));

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0])->toStartWith('business/services/ac-repair.md:')
        ->and(gateNewestEntry())->toBe("Rejected\n- {$result->errors[0]}\n- {$live->label()} stays live");
});

it('says Nothing is live yet on a first sync (ruling 3)', function () {
    layWalk('tests/a-new.md', minimalWalkFile());
    seedRecording(layWalk('tests/b-held.md', str_replace('Hi, is anyone there?', 'Are you open today?', gateHeldWalkText())), walkTape([1 => ['agent' => [walkReply()], 'readings' => [walkScores()], 'words' => [walkScores()]]]), gateStaleFingerprint());

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and($result->message)->toBe('Rejected. Nothing is live yet.')
        ->and($result->errors)->toBe([
            "tests/a-new.md: this walk has no recording yet. Recording it is Ian's step. Nothing is live yet.",
            'tests/b-held.md, turn 1: the reply was sent on its own, not held for the owner. Nothing is live yet.',
        ])
        ->and(gateNewestEntry())->toContain("- tests/a-new.md: no recording. Run `walks:record tests/a-new.md`. Nothing is live yet.\n")
        ->and(gateNewestEntry())->toEndWith("\n- Nothing is live yet");
});

it('fails closed when a walk stops with an error (A3)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape());

    Exceptions::fake();
    $this->mock(WalkRunner::class)->shouldReceive('replay')->once()->andThrow(new RuntimeException('disk full'));

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and($result->errors)->toBe(["tests/price.md: this walk stopped with an error before it finished. {$live->label()} stays live."])
        ->and(gateNewestEntry())->toContain("- tests/price.md: the walk stopped with an error (disk full). {$live->label()} stays live.\n")
        ->and(IcmVersion::query()->where('status', VersionStatus::Active->value)->sole()->id)->toBe($live->id);

    Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'disk full');
});

it('lists walk lines in path order on every sync (edge: ordering, adjacency)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/b-held.md', gateHeldWalkText()), walkTape([1 => ['agent' => [walkReply()], 'readings' => [walkScores()], 'words' => [walkScores()]]]));
    layWalk('tests/a-new.md', str_replace('Hi, is anyone there?', 'Are you open today?', minimalWalkFile()));

    $expected = [
        "tests/a-new.md: this walk has no recording yet. Recording it is Ian's step. {$live->label()} stays live.",
        "tests/b-held.md, turn 1: the reply was sent on its own, not held for the owner. {$live->label()} stays live.",
    ];

    expect(app(FolderSync::class)->run()->errors)->toBe($expected)
        ->and(app(FolderSync::class)->run()->errors)->toBe($expected);
});

it('reads the live version after a refusal (Pitfall 6)', function () {
    IcmVersion::factory()->count(2)->create();
    $live = activateExampleFolder();

    expect(app(ActiveConfig::class)->version()->id)->toBe($live->id);

    seedRecording(layWalk('tests/b-held.md', gateHeldWalkText()), walkTape([1 => ['agent' => [walkReply()], 'readings' => [walkScores()], 'words' => [walkScores()]]]));

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and($result->errors)->toBe(["tests/b-held.md, turn 1: the reply was sent on its own, not held for the owner. {$live->label()} stays live."])
        ->and(app(ActiveConfig::class)->version()->id)->toBe($live->id)
        ->and(app(ActiveConfig::class)->version()->fingerprint)->toBe($live->fingerprint)
        ->and(app(ActiveConfig::class)->filesOfType('walk_test'))->toBe([]);
});

it('reads only the as_built recording of a walk (D-06)', function () {
    $live = activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape(), null, 'measured');

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Rejected)
        ->and($result->errors)->toBe(["tests/price.md: this walk has no recording yet. Recording it is Ian's step. {$live->label()} stays live."]);
});

it('keeps the walk lines on the result when changelog.md cannot be written', function () {
    $live = activateExampleFolder();

    layWalk('tests/price.md', gatePriceWalkText());
    File::delete(icmPath('changelog.md'));
    File::makeDirectory(icmPath('changelog.md'));

    $refused = app(FolderSync::class)->run();

    expect($refused->outcome)->toBe(SyncOutcome::Rejected)
        ->and($refused->errors)->toBe(["tests/price.md: this walk has no recording yet. Recording it is Ian's step. {$live->label()} stays live."])
        ->and($refused->changelogErrors)->toBe(["tests/price.md: no recording. Run `walks:record tests/price.md`. {$live->label()} stays live."])
        ->and(collect($refused->notices)->contains(fn (string $notice) => str_starts_with($notice, 'changelog.md could not be updated')))->toBeTrue();
});

it('keeps the stale notes on the result when changelog.md cannot be written', function () {
    activateExampleFolder();

    seedRecording(layWalk('tests/price.md', gatePriceWalkText()), gatePriceTape(), gateStaleFingerprint());
    File::delete(icmPath('changelog.md'));
    File::makeDirectory(icmPath('changelog.md'));

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Activated)
        ->and($result->staleNotes)->toBe(['tests/price.md is stale: its recording is older than the front desk prompt or the triage questions. It still ran and passed its checks. Run `walks:record tests/price.md` to refresh it.']);
});
