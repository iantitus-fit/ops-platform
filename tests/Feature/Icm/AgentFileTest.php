<?php

use App\Ai\AgentFile;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

it('hands out the example front desk agent from the live version', function () {
    activateExampleFolder();

    $agent = app(ActiveConfig::class)->agent('sms');

    expect($agent)->not->toBeNull()
        ->and($agent['name'])->toBe('Front desk')
        ->and($agent['channel'])->toBe('sms')
        ->and($agent['provider'])->toBe('anthropic')
        ->and($agent['model'])->toBe('claude-sonnet-5-5')
        ->and($agent['escalate_to'])->toBe('owner')
        ->and($agent['body'])->not->toBe('')
        ->and(app(ActiveConfig::class)->agent('email'))->toBeNull();
});

it('reads the provider chain as the primary entry followed by each fallback entry', function () {
    activateExampleFolder();

    $agent = AgentFile::forChannel(app(ActiveConfig::class), 'sms');

    expect($agent->providerChain())->toBe([
        ['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5'],
        ['provider' => 'openai', 'model' => 'gpt-6.1-sol'],
    ]);
});

it('indexes the agent file by channel in the compiled version', function () {
    $version = activateExampleFolder();

    expect($version->compiled['index']['agent'])->toBe(['sms' => 'agents/front-desk.md']);
});

it('counts the agent file as part of the live version and skips nothing', function () {
    $this->artisan('icm:sync')
        ->expectsOutputToContain('('.exampleFolderFileCount().' files)')
        ->doesntExpectOutputToContain('Not supported yet')
        ->assertExitCode(0);

    $paths = array_column(app(ActiveConfig::class)->files(), 'path');

    expect($paths)->toContain('agents/front-desk.md');
});

it('rejects an agent file with no model and keeps nothing live', function () {
    breakFile('agents/front-desk.md', fn (string $text) => str_replace("model: claude-sonnet-5-5\n", '', $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain('agents/front-desk.md: model is missing, and it is required')
        ->assertExitCode(1);

    expect(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(0);
});

it('goes live with a one entry chain when the fallback list is empty', function () {
    breakFile('agents/front-desk.md', fn (string $text) => (string) preg_replace('/fallback:\n(  .*\n)+/', "fallback: []\n", $text));

    activateExampleFolder();

    $agent = AgentFile::forChannel(app(ActiveConfig::class), 'sms');

    expect($agent->providerChain())->toBe([['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5']]);
});

const AGENT_LOADS_BLOCK = "loads:\n  - business/profile.md\n  - business/hours.md\n  - business/faq.md\n  - business/services/\n  - voice/how-we-talk.md\n  - rules/sending.md\n";

/*
| Each row: text to find in agents/front-desk.md, text to put in its place, what the owner must see, and
| whether that is the whole line ('line') or only how it starts, followed by the usable providers ('start').
*/
dataset('broken agent files', [
    'provider nobody knows' => ['provider: anthropic', 'provider: nope', 'agents/front-desk.md: provider nope is not a provider this app can use; use one of ', 'start'],
    'provider written with a capital' => ['provider: anthropic', 'provider: Anthropic', 'agents/front-desk.md: provider Anthropic is not a provider this app can use; use one of ', 'start'],
    'provider that cannot write text' => ['provider: anthropic', 'provider: typesafe', 'agents/front-desk.md: provider typesafe cannot write replies; use one of ', 'start'],
    'fallback provider nobody knows' => ['provider: openai', 'provider: nope', 'agents/front-desk.md: fallback item 1: provider nope is not a provider this app can use; use one of ', 'start'],
    'fallback entry without a model' => ["    model: gpt-6.1-sol\n", '', 'agents/front-desk.md: fallback item 1: model is missing, and it is required', 'line'],
    'fallback entry equal to the primary' => ["provider: openai\n    model: gpt-6.1-sol", "provider: anthropic\n    model: claude-sonnet-5-5", 'agents/front-desk.md: fallback item 1 repeats anthropic claude-sonnet-5-5; each entry must be different', 'line'],
    'channel other than sms' => ['channel: sms', 'channel: email', 'agents/front-desk.md: channel must be one of sms (you wrote email)', 'line'],
    'escalate_to other than owner' => ['escalate_to: owner', 'escalate_to: office', 'agents/front-desk.md: escalate_to must be one of owner (you wrote office)', 'line'],
    'loads list left empty' => [AGENT_LOADS_BLOCK, "loads: []\n", 'agents/front-desk.md: loads must have at least 1 item(s)', 'line'],
    'loads entry that matches no file' => ['  - business/faq.md', '  - business/faqs.md', 'agents/front-desk.md: loads item 3: business/faqs.md matches no file in the folder', 'line'],
    'loads entry under records' => ["  - rules/sending.md\n", "  - rules/sending.md\n  - records/\n", 'agents/front-desk.md: loads item 7: records/ is never loaded; the customer card is added on its own', 'line'],
    'loads folder that holds no typed file' => ['  - business/services/', '  - agents/', 'agents/front-desk.md: loads item 4: agents/ matches no file in the folder', 'line'],
]);

it('rejects a broken agent file in plain words and keeps the previous version live', function (string $search, string $replace, string $expected, string $kind) {
    $previous = activateExampleFolder();

    expect(File::get(icmPath('agents/front-desk.md')))->toContain($search);

    breakFile('agents/front-desk.md', fn (string $text) => str_replace($search, $replace, $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain($expected)
        ->assertExitCode(1);

    $lines = rejectedErrors();

    if ($kind === 'line') {
        expect($lines)->toBe([$expected]);
    } else {
        $line = collect($lines)->first(fn (string $line) => str_starts_with($line, $expected));
        $usable = substr((string) $line, strlen($expected));

        expect($line)->not->toBeNull()
            ->and($usable)->toContain('anthropic')->toContain('openai')->not->toContain('typesafe');
    }

    expect(IcmVersion::where('status', VersionStatus::Active->value)->sole()->is($previous))->toBeTrue()
        ->and(File::get(icmPath('changelog.md')))->toContain($expected);
})->with('broken agent files');

it('rejects a second agent file on the same channel', function () {
    writeIcmFile('agents/zz-night.md', File::get(icmPath('agents/front-desk.md')));

    $line = 'agents/zz-night.md: channel sms already has an agent in agents/front-desk.md; the folder can have one agent per channel';

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line]);
});

it('goes live with no agent file, and says the front desk is off', function () {
    File::delete(icmPath('agents/front-desk.md'));

    activateExampleFolder();

    expect(AgentFile::forChannel(app(ActiveConfig::class), 'sms'))->toBeNull();
});
