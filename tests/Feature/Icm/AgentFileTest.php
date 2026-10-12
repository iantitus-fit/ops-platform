<?php

use App\Ai\AgentFile;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;

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
