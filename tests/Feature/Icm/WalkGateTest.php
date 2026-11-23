<?php

use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\Walks\WalkRunner;
use App\Icm\Walks\WalkTape;
use App\Models\IcmVersion;
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
        ->and(gateNewestEntry())->toContain("Rejected\n- {$line}\n")
        ->and(gateNewestEntry())->toEndWith("- {$live->label()} stays live");
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
