<?php

use App\Ai\AgentReply;
use App\Icm\Loader;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkFingerprint;
use App\Icm\Walks\WalkTape;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\WalkRecording;
use App\SendRule\ClassifierResult;
use App\SendRule\TriageQuestions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
| Helpers the walk tests share. Each walk test file loads this file itself with require_once, so any one of them runs
| alone (the BookingPageHelpers precedent). Nothing here syncs a folder, so a later gate plan does not change these tests.
*/

/**
 * A walk file's contents turned into the value the runner plays. The walk is written alone into a fresh temporary
 * folder and read with the Loader, the way the sync would read it, then the folder is deleted.
 */
function walkFileFrom(string $contents, string $path = 'tests/walk.md'): WalkFile
{
    $folder = sys_get_temp_dir().'/walk-file-'.Str::uuid();

    File::ensureDirectoryExists($folder.'/'.dirname($path));
    File::put($folder.'/'.$path, $contents);

    try {
        $loaded = collect(app(Loader::class)->load($folder)->files)->firstWhere('path', $path);
    } finally {
        File::deleteDirectory($folder);
    }

    return WalkFile::fromCompiled([...$loaded->frontMatter, 'body' => $loaded->body, 'path' => $loaded->path]);
}

/**
 * One front desk model reply as the tape stores it: AgentReply's own toArray() shape, with the same harmless defaults
 * scriptFrontDeskReplies() uses for any field a test leaves out.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function walkReply(array $overrides = []): array
{
    return AgentReply::fromStructured([
        'reply' => 'Thanks for texting. What can we help with?',
        'label' => 'faq_answer',
        'wants_person' => false,
        'service_slug' => null,
        'intake_answers' => [],
        'customer_name' => null,
        'price_services' => [],
        'asks_discount' => false,
        'touches_waiting' => null,
        'waiting_note' => null,
        'easy_reply' => null,
        'easy_label' => null,
        'possible_danger' => false,
        ...$overrides,
    ])->toArray();
}

/**
 * The example business's triage questions, read once from its rules/triage.md the way the sync reads it.
 */
function walkTriageQuestions(): TriageQuestions
{
    static $questions = null;

    if ($questions === null) {
        $loaded = collect(app(Loader::class)->load(base_path('examples/example-hvac'))->files)->firstWhere('path', 'rules/triage.md');
        $questions = TriageQuestions::fromConfig($loaded->frontMatter);
    }

    return $questions;
}

/**
 * Jev's raw scores for one call as the tape stores them: ClassifierResult::toArray() over the example triage's intents
 * and its six yes or no questions, every question at 0.01 unless overridden. These are scores, never a verdict.
 *
 * @param  array<string, float>  $yesNo
 * @return array{model: string, intent: string, intent_confidence: float, intent_probabilities: array<string, float>, yes_no: array<string, float>}
 */
function walkScores(string $intent = 'intake', float $confidence = 0.97, array $yesNo = []): array
{
    $questions = walkTriageQuestions();
    $others = array_diff(array_map('strval', array_keys($questions->intents)), [$intent]);
    $share = $others === [] ? 0.0 : (1 - $confidence) / count($others);
    $probabilities = [$intent => $confidence];

    foreach ($others as $other) {
        $probabilities[$other] = $share;
    }

    return (new ClassifierResult(
        model: 'recorded-jev',
        intent: $intent,
        intentConfidence: $confidence,
        intentProbabilities: $probabilities,
        yesNo: array_merge(array_map(fn () => 0.01, $questions->yesNo), $yesNo),
    ))->toArray();
}

/**
 * A tape from the turns a test lists, keyed by the customer turn's number in the walk's script (counting from 1).
 * Each turn is ['agent' => list of walkReply() arrays, 'readings' => list of walkScores() arrays for the reading of the
 * customer's text, 'words' => list of walkScores() arrays for the checks of the model's words]. A scores array is
 * wrapped the way the recording does it; an entry that already has a 'result' key is kept as it is.
 *
 * @param  array<int, array{agent?: list<array<string, mixed>>, readings?: list<array<string, mixed>>, words?: list<array<string, mixed>>}>  $turns
 */
function walkTape(array $turns): WalkTape
{
    $payload = ['version' => 1, 'turns' => []];

    foreach ($turns as $turn => $entries) {
        $payload['turns'][(string) $turn] = [
            'agent' => array_values($entries['agent'] ?? []),
            'readings' => array_map(
                fn (array $entry): array => isset($entry['result']) ? $entry : ['customer_message' => '', 'result' => $entry],
                array_values($entries['readings'] ?? []),
            ),
            'words' => array_map(
                fn (array $entry): array => isset($entry['result']) ? $entry : ['customer_message' => '', 'draft_reply' => '', 'result' => $entry],
                array_values($entries['words'] ?? []),
            ),
        ];
    }

    return WalkTape::fromPayload($payload);
}

/**
 * How many rows the live connection holds in each table a walk might touch. A walk that never reads or writes a live
 * row leaves every count as it was.
 *
 * @return array<string, int>
 */
function liveRowCounts(): array
{
    $counts = [];

    foreach ([Message::class, Contact::class, Conversation::class, OwnerAlert::class, Activity::class, Appointment::class, BookingLink::class, BookingSend::class] as $model) {
        $counts[$model] = $model::query()->count();
    }

    return $counts;
}

/**
 * A walk where the customer texts three times about a warm air conditioner and is sent a booking link, then books. The
 * checks given are the walk's end checks. A named start is written as given; null leaves the walk with no start.
 *
 * @param  string  $books  "2026-12-02 11:00", or "the earliest time offered".
 * @param  list<string>  $endChecks  Check phrases for the end of the walk.
 * @param  string|null  $wait  A wait such as "1 day", played after the booking.
 */
function bookingWalk(string $books, array $endChecks, ?string $start = '2026-12-01 10:00', ?string $wait = null): WalkFile
{
    $startLine = $start === null ? '' : "start: \"{$start}\"\n";
    $booksLine = str_contains($books, 'earliest') ? $books : "\"{$books}\"";
    $waitLine = $wait === null ? '' : "\n  - wait: \"{$wait}\"";
    $endLine = $endChecks === [] ? '' : 'expect: ['.implode(', ', $endChecks)."]\n";

    return walkFileFrom(<<<MD
---
type: walk_test
name: Dana books a visit
persona: A homeowner whose air conditioner stopped
channel: sms
{$startLine}script:
  - says: "My AC is blowing warm air"
    expect: [sent on its own]
  - says: "Not cooling"
    expect: [sent on its own]
  - says: "Over 10 years"
    expect: [sent on its own, labeled scheduling, carries the booking link]
  - books: {$booksLine}{$waitLine}
{$endLine}---
Dana texts three times and books the time offered.
MD);
}

/**
 * The recording for bookingWalk(): the front desk asks the two AC repair intake questions, then offers a visit with the
 * booking link. Every reading and every words check is clean.
 */
function bookingTape(): WalkTape
{
    $turn = fn (string $words, string $label): array => [
        'agent' => [walkReply(['reply' => $words, 'label' => $label, 'service_slug' => 'ac-repair'])],
        'readings' => [walkScores()],
        'words' => [walkScores()],
    ];

    return walkTape([
        1 => $turn('Thanks for texting. What is the system doing?', 'intake_question'),
        2 => $turn('Thanks. About how old is the system?', 'intake_question'),
        3 => $turn('We can get a technician out this week.', 'scheduling'),
    ]);
}

/**
 * A recording stored the way the gate reads it: the walk's path and script hash, the tape's payload and a fingerprint.
 * The fingerprint defaults to the live version's, so a test that activated the example folder first seeds a fresh
 * recording; a test with nothing live, or one that wants a stale recording, passes a fingerprint.
 */
function seedRecording(WalkFile $walk, WalkTape $tape, ?string $fingerprint = null, string $variant = WalkRecording::AS_BUILT): WalkRecording
{
    return WalkRecording::create([
        'walk_path' => $walk->path,
        'script_hash' => WalkFingerprint::scriptHash($walk),
        'variant' => $variant,
        'fingerprint' => $fingerprint ?? WalkFingerprint::forActiveConfig(),
        'payload' => $tape->toPayload(),
        'recorded_at' => now(),
    ]);
}

/**
 * Write a walk file into the temporary folder and return it as the value the runner plays, so a test can seed its
 * recording from the same text it laid.
 */
function layWalk(string $path, string $contents): WalkFile
{
    writeIcmFile($path, $contents);

    return walkFileFrom($contents, $path);
}

/**
 * A walk where the customer asks the price of an AC repair and the owner expects the owner's own price sentence for it.
 */
function pricingWalkText(): string
{
    return "---\ntype: walk_test\nname: Dana asks the price\npersona: A homeowner asking about cost\nchannel: sms\nscript:\n  - says: \"How much is an AC repair?\"\n    expect: [carries the owner's price sentence for ac-repair]\n---\nA walk.\n";
}

/**
 * The one recorded reply for pricingWalkText(): the model's own words and a flag that the customer asked the price.
 */
function pricingWalkTape(): WalkTape
{
    return walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Happy to help with that.', 'label' => 'faq_answer', 'service_slug' => 'ac-repair', 'price_services' => ['ac-repair']])],
        'readings' => [walkScores()],
        'words' => [walkScores()],
    ]]);
}

/**
 * Lay the example business's own walk files into this test's folder, one per name given. The beforeEach folder copy leaves
 * the example's walk files out (T1), so a test that wants one lays it in on purpose, from the file the example ships.
 */
function layExampleWalks(string ...$names): void
{
    foreach ($names as $name) {
        $source = base_path("examples/example-hvac/tests/{$name}.md");

        expect($source)->toBeFile();

        writeIcmFile("tests/{$name}.md", File::get($source));
    }
}

/**
 * The suite's own fakes, scripted for "Dana end to end": the front desk model's three replies (the symptom question, the
 * system age question and the offer with the booking link) and Jev's raw scores for the three turns. Turn 1's reading
 * gives complaint a small score, above 0.005 and under the shipped bar (config/gate.php), so a test that lowers the bar
 * trips on a real recorded score; every other score is clean under the shipped bars.
 */
function danaFakes(): void
{
    scriptFrontDeskReplies(
        [
            'reply' => 'Sorry to hear that. What is the system doing?',
            'label' => 'intake_question',
            'service_slug' => 'ac-repair',
            'customer_name' => 'Dana Whitfield',
        ],
        [
            'reply' => 'Thanks. About how old is the system?',
            'label' => 'intake_question',
            'service_slug' => 'ac-repair',
            'intake_answers' => [['question_key' => 'symptom', 'option' => 'Not cooling', 'raw_text' => 'Not cooling']],
        ],
        [
            'reply' => 'We can get a technician out this week.',
            'label' => 'scheduling',
            'service_slug' => 'ac-repair',
            'intake_answers' => [['question_key' => 'system_age', 'option' => 'Over 10 years', 'raw_text' => 'Over 10 years']],
        ],
    );

    fakeClassifier()
        ->queueReadings(
            ['intent' => 'intake', 'confidence' => 0.97, 'yes_no' => ['complaint' => 0.04]],
            ['intent' => 'intake', 'confidence' => 0.97],
            ['intent' => 'intake', 'confidence' => 0.97],
        )
        ->queueAnswers(
            ['intent' => 'intake', 'confidence' => 0.93],
            ['intent' => 'intake', 'confidence' => 0.93],
            ['intent' => 'intake', 'confidence' => 0.93],
        );
}

/**
 * A committed recording fixture (tests/Fixtures/walks/<name>.json, the form walks:record --export writes) stored the way
 * the gate reads it. A fixture whose fingerprint is null is a placeholder, so it takes the live version's fingerprint and
 * is fresh; a fixture with one keeps it, so a stale one stays stale.
 */
function seedRecordingFromFixture(string $name): WalkRecording
{
    $fixture = json_decode(File::get(base_path("tests/Fixtures/walks/{$name}.json")), true, 512, JSON_THROW_ON_ERROR);

    return WalkRecording::create([
        'walk_path' => $fixture['walk_path'],
        'script_hash' => $fixture['script_hash'],
        'variant' => $fixture['variant'],
        'fingerprint' => $fixture['fingerprint'] ?? WalkFingerprint::forActiveConfig(),
        'payload' => $fixture['payload'],
        'recorded_at' => $fixture['recorded_at'],
    ]);
}
