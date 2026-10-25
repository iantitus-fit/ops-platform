<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Channels\FakeTextChannel;
use App\Enums\IcmFileKind;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\FolderSync;
use App\Icm\Loader;
use App\Jobs\ProcessInbound;
use App\Models\IcmVersion;
use App\Models\Message;
use App\Rooms\RoomRegistry;
use App\SendRule\Classifier;
use App\SendRule\FakeClassifier;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Rooms\PretendRoom\PretendRoomServiceProvider;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        /**
         * No test reads the text driver or a Twilio or Jev key from .env or the shell: a test that needs one sets it
         * itself with config([...]). This runs after the app has booted, so it also beats a cached config.
         */
        config([
            'channels.text.driver' => 'fake',
            'services.twilio.account_sid' => null,
            'services.twilio.auth_token' => null,
            'services.twilio.messaging_service_sid' => null,
            'services.twilio.webhook_url' => null,
            'services.twilio.status_callback_url' => null,
            'services.jev.key' => null,
            'services.jev.model' => 'typesafe/jev-1.13',
            'services.jev.base_url' => 'https://openrouter.ai/api/v1',
        ]);

        /**
         * No test may reach a real model provider, Jev or Twilio: a request nobody faked throws.
         */
        Http::preventStrayRequests();

        /**
         * Every test gets a scripted classifier, so none can call Jev. fakeClassifier() returns it.
         */
        $this->app->instance(Classifier::class, new FakeClassifier);

        /**
         * No test may prompt a real front desk model: an unscripted prompt throws. scriptFrontDeskReplies() scripts one.
         */
        FrontDeskAgent::fake()->preventStrayPrompts();

        /**
         * Only ProcessInbound is held back, so no text is answered until a test runs the job with runQueuedFrontDeskJobs().
         * Every other job still runs on the sync queue, so the earlier tests keep their timelines.
         */
        Queue::fake([ProcessInbound::class]);

        $folder = sys_get_temp_dir().'/icm-'.Str::uuid();

        File::copyDirectory(base_path('examples/example-hvac'), $folder);
        File::delete($folder.'/changelog.md');
        resetRecordsToTrackedFiles($folder.'/records');
        config(['icm.path' => $folder]);
    })
    ->afterEach(function () {
        File::deleteDirectory(config('icm.path'));

        /**
         * The morph map is static, so it would outlive this test's app and carry a room's nicknames into later
         * tests. Every test boots a fresh app whose AppServiceProvider enforces the app's own nicknames again.
         */
        Relation::morphMap([], false);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The scripted classifier every Feature test runs with: `answer()` sets what it says to a words check,
 * `answerReading()` what it says when reading the customer's text alone, `failWith()` makes every call fail like Jev
 * down (`failReadingWith()` only the readings), and `calls()` lists the words checks, `readingCalls()` the readings
 * and `allCalls()` both.
 */
function fakeClassifier(): FakeClassifier
{
    return app(Classifier::class);
}

/**
 * Script what the front desk model answers, one structured reply per prompt in order. Any field left out
 * gets a harmless default, so a test names only what it cares about. An unscripted prompt still throws.
 *
 * @param  array<string, mixed>  ...$replies
 */
function scriptFrontDeskReplies(array ...$replies): void
{
    $defaults = [
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
    ];

    FrontDeskAgent::fake(array_map(fn (array $reply) => [...$defaults, ...$reply], $replies))->preventStrayPrompts();
}

/**
 * Run each ProcessInbound job the app queued since the test started, once, the way the queue worker would after
 * the debounce. A job that already ran is skipped, so calling this again only runs what was queued since.
 */
function runQueuedFrontDeskJobs(): void
{
    $alreadyRun = app()->bound('front-desk-jobs-run') ? app('front-desk-jobs-run') : [];

    foreach (Queue::pushed(ProcessInbound::class) as $job) {
        if (in_array($job, $alreadyRun, true)) {
            continue;
        }

        $alreadyRun[] = $job;
        app()->instance('front-desk-jobs-run', $alreadyRun);

        app()->call([$job, 'handle']);
    }
}

/**
 * The live example folder, and a text from Dana arriving through the fake webhook.
 *
 * @param  array<string, string>  $payload
 */
function frontDeskCustomerTexts(string $body = 'My AC is blowing warm air', array $payload = []): void
{
    activateExampleFolder();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([...['Body' => $body], ...$payload]))->assertOk();
}

/**
 * The texts the fake channel was asked to send.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function textsSentToCustomers(): array
{
    return app(FakeTextChannel::class)->sent();
}

/**
 * The reply the front desk would send for a script: disclosure first, a blank line, the words, then the sign-off.
 */
function composedFirstReply(string $words): string
{
    $voice = app(ActiveConfig::class)->voice();

    return $voice['ai_disclosure']."\n\n".$words."\n".$voice['sign_off'];
}

/**
 * Whether the message is a fixed line or a text code put together without the model's own words: a holding reply, the
 * safety line, or a code-assembled kind whose meta.parts does not list model_words. The meta is read in PHP, not with
 * a JSON path in SQL, because the suite runs on SQLite and PostgreSQL.
 */
function carriesNoModelWords(Message $message): bool
{
    if (in_array($message->kind, [MessageKind::HoldingReply, MessageKind::LifeSafety], true)) {
        return true;
    }

    return in_array($message->kind, MessageKind::assembledByCode(), true)
        && ! in_array('model_words', $message->meta['parts'] ?? [], true);
}

/**
 * The texts the fake channel sent that carry the agent's own words, leaving out the holding reply that goes out
 * beside a held draft (D-12), the safety line, which is fixed in code (D-16), and the texts code assembled from owner
 * sentences and fixed lines without any model words inside (D-30). A test that says "nothing the agent wrote reached
 * the customer" reads this.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function textsSentForAgentReplies(): array
{
    $fixedBodies = Message::query()
        ->whereNotNull('kind')
        ->get()
        ->filter(fn (Message $message): bool => carriesNoModelWords($message))
        ->pluck('body')
        ->all();

    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => ! in_array($text['body'], $fixedBodies, true),
    ));
}

/**
 * The one message the front desk wrote, whether it was sent or kept as a draft. A holding reply sent beside a held
 * draft, a safety line and a text code assembled without model words are different messages and are left out.
 */
function theAgentMessage(): Message
{
    return Message::query()
        ->where('direction', MessageDirection::Out)
        ->get()
        ->reject(fn (Message $message): bool => carriesNoModelWords($message))
        ->sole();
}

/**
 * Strip a copied records/ folder back to the two files git tracks there. Cards and summaries
 * that a local run wrote into examples/example-hvac/records/ would otherwise leak into tests.
 */
function resetRecordsToTrackedFiles(string $records): void
{
    $trackedFiles = ['CLAUDE.md', 'README.md'];

    foreach (File::directories($records) as $directory) {
        File::deleteDirectory($directory);
    }

    foreach (File::files($records, true) as $file) {
        if (! in_array($file->getFilename(), $trackedFiles, true)) {
            File::delete($file->getPathname());
        }
    }
}

/**
 * How many typed files examples/example-hvac holds. A plan that adds a typed file to the example
 * folder changes this one number (plans 02-02, 02-03, 02-06, 02.1-06 and 02.1-07 do), not every test that counts.
 */
function exampleFolderFileCount(): int
{
    return 16;
}

/**
 * The room keys examples/example-hvac ships, sorted, read the way the sync reads the folder. The example folder gains
 * rooms in Phase 5, and this lets a test name exactly what the example ships without a second number to keep in step;
 * exampleFolderFileCount() stays the one number a landing room changes (04 D-16).
 *
 * @return list<string>
 */
function exampleRoomKeys(): array
{
    $keys = collect(app(Loader::class)->load(base_path('examples/example-hvac'))->files)
        ->filter(fn ($file): bool => $file->kind === IcmFileKind::Typed && $file->type === 'room' && preg_match(Loader::ROOM_CONTRACT, $file->path) === 1)
        ->map(fn ($file): string => preg_replace(Loader::ROOM_CONTRACT, '$1', $file->path))
        ->all();

    sort($keys);

    return array_values($keys);
}

/**
 * Room keys in the order the sync lists them: sorted as plain strings.
 *
 * @param  list<string>  $keys
 * @return list<string>
 */
function sortedRoomKeys(array $keys): array
{
    sort($keys, SORT_STRING);

    return $keys;
}

/**
 * The paths of the connector files examples/example-hvac ships under connectors/, sorted. The example folder gains a
 * connector file in Phase 5, and this lets a test name exactly what the example ships without a second number to keep
 * in step (04 D-16).
 *
 * @return list<string>
 */
function exampleConnectorPaths(): array
{
    $paths = collect(app(Loader::class)->load(base_path('examples/example-hvac'))->files)
        ->filter(fn ($file): bool => $file->kind === IcmFileKind::Typed && $file->type === 'connector' && str_starts_with($file->path, 'connectors/'))
        ->map(fn ($file): string => $file->path)
        ->all();

    sort($paths);

    return array_values($paths);
}

/**
 * Absolute path inside this test's temporary copy of the example business folder.
 */
function icmPath(string $relative = ''): string
{
    return rtrim(config('icm.path').'/'.ltrim($relative, '/'), '/');
}

/**
 * Run the real sync against the temporary folder and return the version it made live.
 */
function activateExampleFolder(): IcmVersion
{
    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Activated);

    return $result->version;
}

/**
 * Rewrite one file of the temporary folder by passing its contents through $mutate.
 *
 * @param  callable(string): string  $mutate
 */
function breakFile(string $relative, callable $mutate): void
{
    File::put(icmPath($relative), $mutate(File::get(icmPath($relative))));
}

/**
 * Write a file into the temporary folder, creating any missing directories.
 */
function writeIcmFile(string $relative, string $contents): void
{
    File::ensureDirectoryExists(dirname(icmPath($relative)));
    File::put(icmPath($relative), $contents);
}

/**
 * Copy a folder from tests/Fixtures over this test's temporary business folder: the whole fixture when no paths are
 * given, otherwise only the named paths, relative to the fixture. Nothing is ever written under examples/.
 */
function layFixtureFolder(string $fixture, string ...$paths): void
{
    $source = base_path('tests/Fixtures/'.$fixture);

    if ($paths === []) {
        File::copyDirectory($source, icmPath());

        return;
    }

    foreach ($paths as $path) {
        File::ensureDirectoryExists(dirname(icmPath($path)));
        File::copy($source.'/'.$path, icmPath($path));
    }
}

/**
 * Lay the pretend room's folder, or only the named paths of it, into this test's temporary business folder.
 */
function layPretendRoomFolder(string ...$paths): void
{
    layFixtureFolder('pretend-room', ...$paths);
}

/**
 * Switch the pretend room on for this test only. It is registered the way Laravel registers a provider at start-up,
 * its pending migration runs inside this test's transaction (RefreshDatabase migrated before the room existed, and the
 * transaction removes the table afterwards), and its widgets are added to the admin panel, which was built before the
 * test could register the room. Its morph map is static, so the Feature afterEach in this file resets it. It is never listed in bootstrap/providers.php (D-20).
 */
function enablePretendRoom(): void
{
    app()->register(PretendRoomServiceProvider::class);

    test()->artisan('migrate')->assertExitCode(0);

    app(RoomRegistry::class)->addWidgetsTo(Filament::getPanel('admin'));
}

/**
 * A Twilio-shaped form body for the fake text webhook. Give each text its own MessageSid.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function fakeTextPayload(array $overrides = []): array
{
    return array_merge([
        'MessageSid' => 'SM'.str_repeat('c', 32),
        'From' => '+12025550123',
        'To' => '+13125550147',
        'Body' => 'My AC is blowing warm air',
        'NumMedia' => '0',
    ], $overrides);
}

/**
 * The lines the sync rejected the folder with, read back from the stored rejected version.
 *
 * @return list<string>
 */
function rejectedErrors(): array
{
    $rejected = IcmVersion::query()->where('status', VersionStatus::Rejected->value)->latest('id')->firstOrFail();

    return $rejected->errors;
}
