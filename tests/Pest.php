<?php

use App\Enums\SyncOutcome;
use App\Icm\FolderSync;
use App\Models\IcmVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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
        $folder = sys_get_temp_dir().'/icm-'.Str::uuid();

        File::copyDirectory(base_path('examples/example-hvac'), $folder);
        File::delete($folder.'/changelog.md');
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
