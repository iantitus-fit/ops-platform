<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Icm\FolderSync;
use App\Models\IcmVersion;
use App\SendRule\Classifier;
use App\SendRule\FakeClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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
         * No test may reach a real model provider, Jev or Twilio: a request nobody faked throws.
         */
        Http::preventStrayRequests();

        /**
         * Every test gets a scripted classifier, so none can call Jev. fakeClassifier() returns it.
         */
        $this->app->instance(Classifier::class, new FakeClassifier);

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
    return 11;
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
