<?php

use App\Icm\ActiveConfig;
use App\Icm\Walks\ScratchWorld;
use App\Icm\Walks\WalkFingerprint;
use App\Icm\Walks\WalkRunner;
use App\Models\Appointment;
use App\Models\Contact;
use Carbon\CarbonImmutable;

require_once __DIR__.'/WalkHelpers.php';

it('is the same twice, and the same whoever the customer is (D-04)', function () {
    activateExampleFolder();
    $before = WalkFingerprint::forActiveConfig();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    Appointment::factory()->for($contact)->at(CarbonImmutable::now()->addDays(2))->create();
    Contact::factory()->create(['name' => 'Someone Else', 'phone_e164' => '+12025550124']);

    expect(WalkFingerprint::forActiveConfig())->toBe($before)
        ->and($before)->toMatch('/^[0-9a-f]{64}$/');
});

it('changes when the agent file body, a loaded file or the triage questions change (D-04)', function (string $file, string $find, string $replace) {
    activateExampleFolder();
    $before = WalkFingerprint::forActiveConfig();

    breakFile($file, fn (string $text): string => str_replace($find, $replace, $text));
    $this->artisan('icm:sync')->assertExitCode(0);
    app()->forgetInstance(ActiveConfig::class);

    expect(WalkFingerprint::forActiveConfig())->not->toBe($before);
})->with([
    'the agent file body' => ['agents/front-desk.md', 'It leaves promises and complaints to the owner.', 'It leaves promises and complaints to the owner, every time.'],
    'a loaded file, the faq' => ['business/faq.md', 'We serve Chicago, Evanston, Oak Park and Skokie.', 'We serve Chicago, Evanston, Oak Park, Skokie and Wilmette.'],
    "a loaded file's body, the hours" => ['business/hours.md', 'We are closed on Thanksgiving and Christmas Day.', 'We are closed on Thanksgiving, Christmas Day and New Year\'s Day.'],
    "a loaded file's front matter, the hours" => ['business/hours.md', 'booking_lead_minutes: 120', 'booking_lead_minutes: 180'],
    'the complaint question in the triage rules' => ['rules/triage.md', 'A customer describing a problem with their own equipment is not complaining about the business.', 'A customer describing a problem with their own equipment is never complaining about the business.'],
]);

it('does not change when a file the agent does not load changes (D-04)', function () {
    activateExampleFolder();
    $before = WalkFingerprint::forActiveConfig();

    breakFile('rooms/money/notes.md', fn (string $text): string => $text."\nA note added by the owner.\n");
    $this->artisan('icm:sync')->assertExitCode(0);
    app()->forgetInstance(ActiveConfig::class);

    expect(WalkFingerprint::forActiveConfig())->toBe($before);
});

it('returns the candidate fingerprint on the result, not the live one (D-04)', function () {
    $compiled = activateExampleFolder()->compiled;
    $live = WalkFingerprint::forActiveConfig();
    $path = $compiled['index']['agent']['sms'];
    $edited = $compiled;
    $edited['files'][$path]['body'] .= "\nIt greets every customer by name.\n";
    $tape = walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Thanks for texting. What is the AC doing?', 'label' => 'intake_question', 'service_slug' => 'ac-repair'])],
        'readings' => [walkScores()],
        'words' => [walkScores()],
    ]]);
    $inside = [];

    $same = app(WalkRunner::class)->replay(walkFileFrom(minimalWalkFile()), $compiled, clone $tape);
    $other = app(WalkRunner::class)->replay(walkFileFrom(minimalWalkFile()), $edited, clone $tape, function (ScratchWorld $world) use (&$inside): void {
        $inside['candidate'] = WalkFingerprint::forActiveConfig();
    });

    expect($same->fingerprint)->toBe($live)
        ->and($other->fingerprint)->not->toBe($live)
        ->and($other->fingerprint)->toBe($inside['candidate'])
        ->and(WalkFingerprint::forActiveConfig())->toBe($live);
});
