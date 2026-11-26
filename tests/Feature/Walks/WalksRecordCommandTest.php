<?php

use App\Ai\AgentFile;
use App\Ai\Agents\FrontDeskAgent;
use App\Ai\BookingSectionWording;
use App\Ai\FrontDeskPrompt;
use App\Ai\RecordingFrontDeskAgent;
use App\Ai\RunFrontDeskAgent;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\Walks\WalkFingerprint;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\IcmVersion;
use App\Models\WalkRecording;
use App\SendRule\Classifier;
use App\SendRule\FakeClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;

require_once __DIR__.'/WalkHelpers.php';

/**
 * A walk where the customer asks the price of an AC repair and the owner expects the owner's own sentence for it.
 */
function recordPriceWalkText(): string
{
    return "---\ntype: walk_test\nname: Dana asks the price\npersona: A homeowner asking about cost\nchannel: sms\nscript:\n  - says: \"How much is an AC repair?\"\n    expect: [carries the owner's price sentence for ac-repair]\n---\nA walk.\n";
}

/**
 * The one reply the fake front desk model gives to the price walk: its own words and a flag that the customer asked the price.
 *
 * @return array<string, mixed>
 */
function recordPriceReply(): array
{
    return ['reply' => 'Happy to help with that.', 'label' => 'faq_answer', 'service_slug' => 'ac-repair', 'price_services' => ['ac-repair']];
}

it('records a walk and the next sync replays it (D-03, D-06)', function () {
    $live = activateExampleFolder();
    $walk = layWalk('tests/price.md', recordPriceWalkText());

    scriptFrontDeskReplies(recordPriceReply());
    fakeClassifier()
        ->queueReadings(['intent' => 'intake', 'confidence' => 0.97, 'yes_no' => ['price' => 0.02]])
        ->queueAnswers(['intent' => 'intake', 'confidence' => 0.93, 'yes_no' => ['promise' => 0.04]]);

    $this->artisan('walks:record', ['walk' => ['tests/price.md']])
        ->expectsOutputToContain('Recorded tests/price.md')
        ->assertExitCode(0);

    $row = WalkRecording::query()->sole();
    $turn = $row->payload['turns']['1'];

    expect($row->walk_path)->toBe('tests/price.md')
        ->and($row->variant)->toBe(WalkRecording::AS_BUILT)
        ->and($row->script_hash)->toBe(WalkFingerprint::scriptHash($walk))
        ->and($row->fingerprint)->toBe(WalkFingerprint::forActiveConfig())
        ->and($row->payload['recorded_with'])->toHaveKeys(['agent', 'jev'])
        ->and($turn['agent'])->toBe([walkReply(recordPriceReply())])
        ->and($turn['readings'])->toHaveCount(1)
        ->and($turn['readings'][0]['customer_message'])->toBe('How much is an AC repair?')
        ->and($turn['readings'][0]['result'])->toHaveKeys(['yes_no', 'intent_confidence'])
        ->and($turn['readings'][0]['result']['intent_confidence'])->toBe(0.97)
        ->and($turn['readings'][0]['result']['yes_no']['price'])->toBe(0.02)
        ->and($turn['readings'][0])->not->toHaveKeys(['sendable', 'reasons'])
        ->and($turn['words'][0]['draft_reply'])->not->toBe('')
        ->and($turn['words'][0]['result']['intent_confidence'])->toBe(0.93)
        ->and($turn['words'][0]['result']['yes_no']['promise'])->toBe(0.04)
        ->and($turn['words'][0]['result'])->not->toHaveKeys(['sendable', 'reasons']);

    $this->artisan('icm:sync')->assertExitCode(0);

    $active = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();

    expect($active->id)->not->toBe($live->id)
        ->and(WalkRecording::query()->count())->toBe(1);
});

it('hands out queued Jev scores in order before the fixed answer (D-03)', function () {
    $classifier = new FakeClassifier;
    $classifier->queueReadings(['intent' => 'scheduling', 'confidence' => 0.9], ['intent' => 'faq', 'confidence' => 0.88])
        ->queueAnswers(['intent' => 'intake', 'confidence' => 0.91, 'yes_no' => ['price' => 0.03]]);

    $questions = walkTriageQuestions();

    $first = $classifier->classify('one', '', $questions);
    $second = $classifier->classify('two', '', $questions);
    $third = $classifier->classify('three', '', $questions);
    $words = $classifier->classify('four', 'a reply', $questions);
    $wordsAfter = $classifier->classify('five', 'another reply', $questions);

    expect([$first->intent, $first->intentConfidence])->toBe(['scheduling', 0.9])
        ->and([$second->intent, $second->intentConfidence])->toBe(['faq', 0.88])
        ->and([$third->intent, $third->intentConfidence])->toBe(['faq', 0.95])
        ->and([$words->intent, $words->intentConfidence, $words->yesNo['price']])->toBe(['intake', 0.91, 0.03])
        ->and([$wordsAfter->intent, $wordsAfter->intentConfidence, $wordsAfter->yesNo['price']])->toBe(['faq', 0.95, 0.01])
        ->and($classifier->allCalls())->toHaveCount(5)
        ->and($classifier->calls())->toHaveCount(2)
        ->and($classifier->readingCalls())->toHaveCount(3);
});

/**
 * A walk where the customer texts three times about a warm air conditioner, is sent the booking link and books Wednesday,
 * December 2 at 11:00. Its third turn is the offer, which reads the Booking section of the prompt.
 */
function recordBookingWalkText(): string
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
 * The model's three replies to the booking walk: two intake questions, then the offer.
 *
 * @return list<array<string, mixed>>
 */
function recordBookingReplies(): array
{
    return [
        ['reply' => 'Thanks for texting. What is the system doing?', 'label' => 'intake_question', 'service_slug' => 'ac-repair'],
        ['reply' => 'Thanks. About how old is the system?', 'label' => 'intake_question', 'service_slug' => 'ac-repair'],
        ['reply' => 'We can get a technician out this week.', 'label' => 'scheduling', 'service_slug' => 'ac-repair'],
    ];
}

/**
 * Run walks:record and return its exit code and the lines it printed, with no empty line.
 *
 * @param  array<string, mixed>  $arguments
 * @return array{0: int, 1: list<string>}
 */
function recordRun(array $arguments = []): array
{
    $output = new BufferedOutput;
    $exit = Artisan::call('walks:record', $arguments, $output);

    return [$exit, array_values(array_filter(explode("\n", $output->fetch()), fn (string $line): bool => $line !== ''))];
}

/**
 * A hash of every file under the temporary business folder, by path, so a test can tell whether a run wrote one.
 *
 * @return array<string, string>
 */
function recordFolderHashes(): array
{
    return collect(File::allFiles(icmPath(), true))->mapWithKeys(fn ($file): array => [$file->getRelativePathname() => md5_file($file->getRealPath())])->sortKeys()->all();
}

/**
 * Write a wording file into the temporary folder's parent and return its path.
 *
 * @param  array<string, mixed>|string  $contents
 */
function recordWordingFile(array|string $contents): string
{
    $path = sys_get_temp_dir().'/wording-'.Str::uuid().'.json';

    File::put($path, is_string($contents) ? $contents : json_encode($contents, JSON_THROW_ON_ERROR));

    return $path;
}

it('sends nothing and writes nothing live (chokepoint rule)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());

    Carbon::setTestNow('2026-11-05 12:00:00');
    scriptFrontDeskReplies(recordPriceReply());

    $classifier = app(Classifier::class);
    $counts = liveRowCounts();
    $versions = IcmVersion::query()->count();
    $files = recordFolderHashes();

    [$exit] = recordRun(['walk' => ['tests/price.md']]);

    expect($exit)->toBe(0)
        ->and(liveRowCounts())->toBe($counts)
        ->and(IcmVersion::query()->count())->toBe($versions)
        ->and(WalkRecording::query()->count())->toBe(1)
        ->and(textsSentToCustomers())->toBe([])
        ->and(recordFolderHashes())->toBe($files)
        ->and(Carbon::getTestNow()?->toIso8601String())->toBe('2026-11-05T12:00:00+00:00')
        ->and(app(Classifier::class))->toBe($classifier)
        ->and(app(RunFrontDeskAgent::class))->not->toBeInstanceOf(RecordingFrontDeskAgent::class)
        ->and(app()->bound(BookingSectionWording::class))->toBeFalse();
});

it('records a walk again in place (D-06)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());

    Carbon::setTestNow('2026-11-01 10:00:00');
    scriptFrontDeskReplies([...recordPriceReply(), 'reply' => 'First recording words.']);
    recordRun(['walk' => ['tests/price.md']]);

    $first = WalkRecording::query()->sole();

    Carbon::setTestNow('2026-11-02 10:00:00');
    scriptFrontDeskReplies([...recordPriceReply(), 'reply' => 'Second recording words.']);
    [$exit] = recordRun(['walk' => ['tests/price.md']]);

    $second = WalkRecording::query()->sole();

    expect($exit)->toBe(0)
        ->and($second->id)->toBe($first->id)
        ->and($first->payload['turns']['1']['agent'][0]['reply'])->toBe('First recording words.')
        ->and($second->payload['turns']['1']['agent'][0]['reply'])->toBe('Second recording words.')
        ->and($second->recorded_at->greaterThan($first->recorded_at))->toBeTrue()
        ->and(IcmVersion::query()->count())->toBe(1);
});

it('records every walk in path order with no argument, with nothing live yet (D-03, D-26)', function () {
    layWalk('tests/price.md', recordPriceWalkText());
    layWalk('tests/hello.md', minimalWalkFile());

    scriptFrontDeskReplies([], recordPriceReply());

    [$exit, $lines] = recordRun();

    expect($exit)->toBe(0)
        ->and($lines)->toBe([
            'Recorded tests/hello.md: 1 model reply, 1 Jev reading, 1 Jev reply check. Its checks passed.',
            'Recorded tests/price.md: 1 model reply, 1 Jev reading, 1 Jev reply check. Its checks passed.',
        ])
        ->and(WalkRecording::query()->orderBy('walk_path')->pluck('walk_path')->all())->toBe(['tests/hello.md', 'tests/price.md'])
        ->and(IcmVersion::query()->count())->toBe(0);
});

it('records only the walks it is given (D-03)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());
    layWalk('tests/hello.md', minimalWalkFile());

    scriptFrontDeskReplies(recordPriceReply());

    [$exit, $lines] = recordRun(['walk' => ['tests/price.md']]);

    expect($exit)->toBe(0)
        ->and($lines)->toHaveCount(1)
        ->and(WalkRecording::query()->pluck('walk_path')->all())->toBe(['tests/price.md']);
});

it('keeps a recording whose checks did not pass and says so (D-17)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', str_replace("carries the owner's price sentence for ac-repair", 'carries the owner\'s price sentence for furnace-repair', recordPriceWalkText()));

    scriptFrontDeskReplies(recordPriceReply());

    [$exit, $lines] = recordRun(['walk' => ['tests/price.md']]);

    expect($exit)->toBe(0)
        ->and($lines)->toHaveCount(1)
        ->and($lines[0])->toStartWith('Recorded tests/price.md: 1 model reply, 1 Jev reading, 1 Jev reply check. Its checks did not pass: ')
        ->and($lines[0])->toEndWith('.')->not->toEndWith('..')
        ->and(WalkRecording::query()->count())->toBe(1);
});

it('refuses a folder that fails its checks and records nothing (D-03)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());

    breakFile('business/services/ac-repair.md', fn (string $text) => preg_replace('/^price_policy: .*$/m', 'price_policy: sometimes', $text));

    scriptFrontDeskReplies(recordPriceReply());

    [$exit, $lines] = recordRun();

    expect($exit)->toBe(1)
        ->and(count($lines))->toBeGreaterThan(1)
        ->and($lines[0])->toContain('ac-repair')
        ->and(end($lines))->toBe('The folder does not pass its checks, so nothing was recorded.')
        ->and(WalkRecording::query()->count())->toBe(0);
});

it('refuses a walk that is not in the folder (D-03)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());

    [$exit, $lines] = recordRun(['walk' => ['tests/price.md', 'tests/nope.md']]);

    expect($exit)->toBe(1)
        ->and($lines)->toBe(['tests/nope.md is not a walk test in the folder.'])
        ->and(WalkRecording::query()->count())->toBe(0);
});

it('keeps no recording when Jev did not answer (D-03)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());

    scriptFrontDeskReplies(recordPriceReply());
    fakeClassifier()->failWith('down');

    [$exit, $lines] = recordRun(['walk' => ['tests/price.md']]);

    expect($exit)->toBe(1)
        ->and($lines)->toBe(['tests/price.md, turn 1: Jev did not answer (down). Nothing was recorded for this walk.'])
        ->and(WalkRecording::query()->count())->toBe(0);
});

it('keeps no recording when the model did not answer, and records the walks after it (D-03)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());
    layWalk('tests/hello.md', minimalWalkFile());

    scriptFrontDeskReplies([]);

    [$exit, $lines] = recordRun();

    expect($exit)->toBe(1)
        ->and($lines)->toHaveCount(2)
        ->and($lines[0])->toStartWith('Recorded tests/hello.md: ')
        ->and($lines[1])->toStartWith('tests/price.md, turn 1: the model did not answer (')
        ->and($lines[1])->toEndWith('). Nothing was recorded for this walk.')
        ->and(WalkRecording::query()->pluck('walk_path')->all())->toBe(['tests/hello.md']);
});

it('records a Booking wording variant as data (D-18)', function () {
    activateExampleFolder();
    layWalk('tests/booking.md', recordBookingWalkText());

    $services = 'ac-repair, furnace-repair and furnace-tune-up';
    $shipped = str_replace('{services}', $services, FrontDeskPrompt::BOOKING_ONLINE);

    scriptFrontDeskReplies(...recordBookingReplies());
    expect(recordRun()[0])->toBe(0);

    scriptFrontDeskReplies(...recordBookingReplies());
    [$exit, $lines] = recordRun(['--variant' => 'tests/Fixtures/booking-wording/test-variant.json']);

    // The fake keeps the prompts of both runs, so the six instructions are read together: three as built, then three with the wording.
    $instructions = [];
    FrontDeskAgent::assertPrompted(function ($prompt) use (&$instructions): bool {
        $instructions[] = (string) $prompt->agent->instructions();

        return count($instructions) === 6;
    });

    [$asBuilt, $variant] = [array_slice($instructions, 0, 3), array_slice($instructions, 3, 3)];

    $rows = WalkRecording::query()->orderBy('variant')->get()->keyBy('variant');

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    Conversation::factory()->for($contact)->create();
    $outside = app(FrontDeskPrompt::class)->instructions(AgentFile::forChannel(app(ActiveConfig::class), 'sms'), $contact);

    expect($exit)->toBe(0)
        ->and($lines)->toHaveCount(1)
        ->and(collect($asBuilt)->every(fn (string $text): bool => str_contains($text, $shipped) && ! str_contains($text, 'TEST WORDING')))->toBeTrue()
        ->and(collect($variant)->every(fn (string $text): bool => str_contains($text, "TEST WORDING ONLINE. These services book online: {$services}.") && ! str_contains($text, $shipped)))->toBeTrue()
        ->and($rows->keys()->all())->toBe(['as_built', 'test-variant'])
        ->and($rows['test-variant']->fingerprint)->not->toBe($rows['as_built']->fingerprint)
        ->and($rows['test-variant']->script_hash)->toBe($rows['as_built']->script_hash)
        ->and(app()->bound(BookingSectionWording::class))->toBeFalse()
        ->and($outside)->toContain($shipped)->not->toContain('TEST WORDING')
        ->and(FrontDeskPrompt::BOOKING_ONLINE)->toContain('The business books these services online: {services}.');
});

it('never lets the gate read a variant recording (D-18)', function () {
    activateExampleFolder();
    layWalk('tests/booking.md', recordBookingWalkText());

    scriptFrontDeskReplies(...recordBookingReplies());
    expect(recordRun(['--variant' => 'tests/Fixtures/booking-wording/test-variant.json'])[0])->toBe(0);

    $live = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(["tests/booking.md: this walk has no recording yet. Recording it is Ian's step. {$live->label()} stays live."]);
});

it('refuses a wording file that cannot be used and records nothing (D-18)', function (array|string $contents, string $expected) {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());

    scriptFrontDeskReplies(recordPriceReply());

    $file = recordWordingFile($contents);

    try {
        [$exit, $lines] = recordRun(['--variant' => $file]);
    } finally {
        File::delete($file);
    }

    expect($exit)->toBe(1)
        ->and($lines)->toBe([$expected])
        ->and(WalkRecording::query()->count())->toBe(0);
})->with([
    'no {services} in booking_online' => [
        ['name' => 'short', 'booking_online' => 'The business books online.', 'booking_visit_booked' => 'A visit is booked.'],
        'booking_online must hold {services} exactly once, where the list of services goes.',
    ],
    '{services} twice in booking_online' => [
        ['name' => 'short', 'booking_online' => '{services} and {services}.', 'booking_visit_booked' => 'A visit is booked.'],
        'booking_online must hold {services} exactly once, where the list of services goes.',
    ],
    'booking_online not text' => [
        ['name' => 'short', 'booking_online' => 5, 'booking_visit_booked' => 'A visit is booked.'],
        'booking_online must be text.',
    ],
    'booking_visit_booked missing' => [
        ['name' => 'short', 'booking_online' => 'Online: {services}.'],
        'booking_visit_booked must be text.',
    ],
    'the name as_built' => [
        ['name' => 'as_built', 'booking_online' => 'Online: {services}.', 'booking_visit_booked' => 'A visit is booked.'],
        'The wording name cannot be as_built, because the sync replays that recording. Use another name.',
    ],
    'a name with capitals' => [
        ['name' => 'Short One', 'booking_online' => 'Online: {services}.', 'booking_visit_booked' => 'A visit is booked.'],
        'The wording name must use only lowercase letters, digits and dashes, such as shorter-text.',
    ],
    'not JSON' => [
        '{ not json',
        'The wording file could not be read as JSON.',
    ],
]);

it('refuses a wording file that is not there (D-18)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());

    [$exit, $lines] = recordRun(['--variant' => 'tests/Fixtures/booking-wording/missing.json']);

    expect($exit)->toBe(1)
        ->and($lines)->toBe(['The wording file tests/Fixtures/booking-wording/missing.json was not found.'])
        ->and(WalkRecording::query()->count())->toBe(0);
});

it('exports each recording as JSON (Ian\'s D-02 ruling)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());
    layWalk('tests/booking.md', recordBookingWalkText());

    $folder = sys_get_temp_dir().'/walk-export-'.Str::uuid().'/nested';

    try {
        scriptFrontDeskReplies(recordPriceReply());
        [$exit] = recordRun(['walk' => ['tests/price.md'], '--export' => $folder]);

        $row = WalkRecording::query()->sole();
        $exported = json_decode(File::get($folder.'/price.json'), true, 512, JSON_THROW_ON_ERROR);

        scriptFrontDeskReplies(...recordBookingReplies());
        recordRun(['walk' => ['tests/booking.md'], '--variant' => 'tests/Fixtures/booking-wording/test-variant.json', '--export' => $folder]);

        $files = collect(File::files($folder))->map(fn ($file) => $file->getFilename())->sort()->values()->all();
    } finally {
        File::deleteDirectory(dirname($folder));
    }

    expect($exit)->toBe(0)
        ->and(array_keys($exported))->toBe(['walk_path', 'script_hash', 'variant', 'fingerprint', 'recorded_at', 'source', 'payload'])
        ->and($exported['walk_path'])->toBe('tests/price.md')
        ->and($exported['script_hash'])->toBe($row->script_hash)
        ->and($exported['variant'])->toBe(WalkRecording::AS_BUILT)
        ->and($exported['fingerprint'])->toBe($row->fingerprint)
        ->and($exported['recorded_at'])->toBe($row->recorded_at->toIso8601String())
        ->and($exported['source'])->toBe('live')
        ->and($exported['payload'])->toBe($row->payload)
        ->and($files)->toBe(['booking.test-variant.json', 'price.json']);
});

it('writes no export file for a walk it did not record (D-02)', function () {
    activateExampleFolder();
    layWalk('tests/price.md', recordPriceWalkText());

    scriptFrontDeskReplies(recordPriceReply());
    fakeClassifier()->failWith('down');

    $folder = sys_get_temp_dir().'/walk-export-'.Str::uuid();

    try {
        [$exit] = recordRun(['walk' => ['tests/price.md'], '--export' => $folder]);

        $written = File::isDirectory($folder) ? File::files($folder) : [];
    } finally {
        File::deleteDirectory($folder);
    }

    expect($exit)->toBe(1)
        ->and($written)->toBe([]);
});

it('names each count apart, in the plural past one (Ian, 2026-11-26)', function () {
    activateExampleFolder();
    layWalk('tests/booking.md', recordBookingWalkText());

    scriptFrontDeskReplies(...recordBookingReplies());

    [$exit, $lines] = recordRun(['walk' => ['tests/booking.md']]);

    $turns = WalkRecording::query()->sole()->payload['turns'];

    expect($exit)->toBe(0)
        ->and(array_sum(array_map(fn (array $turn): int => count($turn['agent']), $turns)))->toBe(3)
        ->and(array_sum(array_map(fn (array $turn): int => count($turn['readings']), $turns)))->toBe(3)
        ->and(array_sum(array_map(fn (array $turn): int => count($turn['words']), $turns)))->toBe(3)
        ->and($lines)->toHaveCount(1)
        ->and($lines[0])->toStartWith('Recorded tests/booking.md: 3 model replies, 3 Jev readings, 3 Jev reply checks. Its checks ');
});

it('still prints a count of 0 (Ian, 2026-11-26)', function () {
    activateExampleFolder();
    layWalk('tests/person.md', "---\ntype: walk_test\nname: Dana asks for a person\npersona: A homeowner who wants to talk to someone\nchannel: sms\nscript:\n  - says: \"Can I talk to a real person please?\"\n    expect: [handed to the owner]\n---\nA walk.\n");

    [$exit, $lines] = recordRun(['walk' => ['tests/person.md']]);

    expect($exit)->toBe(0)
        ->and($lines)->toHaveCount(1)
        ->and($lines[0])->toStartWith('Recorded tests/person.md: 0 model replies, 1 Jev reading, 0 Jev reply checks. Its checks ');
});
