<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Channels\FakeTextChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\FolderSync;
use App\Jobs\ProcessInbound;
use App\Models\IcmVersion;
use App\Models\Message;
use App\SendRule\Classifier;
use App\SendRule\FakeClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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
 * The scripted classifier every Feature test runs with: `answer()` sets what it says, `failWith()` makes it fail
 * like Jev down, and `calls()` lists the texts it was asked about.
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
 * The texts the fake channel sent that carry the agent's own words, leaving out the holding reply that goes out
 * beside a held draft (D-12) and the safety line, which is fixed in code (D-16). A test that says "nothing the agent
 * wrote reached the customer" reads this.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function textsSentForAgentReplies(): array
{
    $holdingBodies = Message::query()->whereIn('kind', [MessageKind::HoldingReply->value, MessageKind::LifeSafety->value])->pluck('body')->all();

    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => ! in_array($text['body'], $holdingBodies, true),
    ));
}

/**
 * The one message the front desk wrote, whether it was sent or kept as a draft. A holding reply sent beside a held
 * draft and a safety line are different messages and are left out.
 */
function theAgentMessage(): Message
{
    return Message::query()
        ->where('direction', MessageDirection::Out)
        ->where(fn ($query) => $query->whereNull('kind')->orWhereNotIn('kind', [MessageKind::HoldingReply->value, MessageKind::LifeSafety->value]))
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
 * folder changes this one number (plans 02-02, 02-03 and 02-06 do), not every test that counts.
 */
function exampleFolderFileCount(): int
{
    return 12;
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
