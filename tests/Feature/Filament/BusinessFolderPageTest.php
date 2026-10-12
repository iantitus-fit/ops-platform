<?php

use App\Enums\SyncOutcome;
use App\Filament\Pages\BusinessFolder;
use App\Icm\FolderSync;
use App\Icm\SyncStatus;
use App\Models\IcmVersion;
use App\Models\OwnerAlert;
use App\Models\User;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('opens the business folder page for a signed-in owner', function () {
    $this->get('/admin/business-folder')->assertOk()->assertSee('Business Folder');
});

it('says no version is live yet, then goes live from Sync Now', function () {
    $page = Livewire::test(BusinessFolder::class)
        ->assertSee('No version is live yet. Click Sync Now.')
        ->callAction('syncNow');

    $label = IcmVersion::sole()->label();
    $files = exampleFolderFileCount();

    $page->assertNotified("Live as {$label} ({$files} files)")
        ->assertDontSee('No version is live yet. Click Sync Now.')
        ->assertSee($label);
});

it('shows the live version, its file count, its Chicago go-live time and its files', function () {
    $this->freezeTime();
    $version = activateExampleFolder();

    $liveSince = now()->setTimezone('America/Chicago')->format('M j, g:i a');

    Livewire::test(BusinessFolder::class)
        ->assertSee($version->label())
        ->assertSee(exampleFolderFileCount().' files')
        ->assertSee("Live since {$liveSince}")
        ->assertSee('business/services/ac-repair.md')
        ->assertSee('service')
        ->assertSee('Not a git repository');
});

it('reports an unchanged folder as already active and as no changes', function () {
    $version = activateExampleFolder();

    Livewire::test(BusinessFolder::class)
        ->callAction('syncNow')
        ->assertNotified("Already active: {$version->label()}, no changes")
        ->assertSee('No changes');

    expect(IcmVersion::count())->toBe(1);
});

it('rejects a broken file, keeps v1 live and lists the error under the last sync', function () {
    $version = activateExampleFolder();
    breakFile('business/services/ac-repair.md', fn (string $contents) => str_replace('price_policy: starting_at', 'price_policy: maybe', $contents));

    Livewire::test(BusinessFolder::class)
        ->callAction('syncNow')
        ->assertNotified("Rejected. {$version->label()} stays live.")
        ->assertSee($version->label())
        ->assertSee('Rejected')
        ->assertSee('business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed (you wrote maybe)');

    expect(File::get(icmPath('changelog.md')))->toContain('Rejected');
});

it('escapes what the owner wrote in the notification and on the page', function () {
    activateExampleFolder();
    breakFile('business/services/ac-repair.md', fn (string $contents) => str_replace('price_policy: starting_at', 'price_policy: <i>sneaky</i>', $contents));

    $page = Livewire::test(BusinessFolder::class)->callAction('syncNow');

    $notifications = new Notifications;
    $notifications->mount();
    $body = $notifications->notifications->map(fn (Notification $notification) => $notification->getBody())->implode(' ');

    expect($body)->toContain('&lt;i&gt;sneaky&lt;/i&gt;')
        ->not->toContain('<i>sneaky</i>');

    $page->assertDontSeeHtml('<i>sneaky</i>');
});

it('shows a rejected attempt as the last sync result when it ties with the live version last check', function () {
    $this->freezeTime();
    activateExampleFolder();
    breakFile('business/services/ac-repair.md', fn (string $contents) => str_replace('price_policy: starting_at', 'price_policy: maybe', $contents));
    app(FolderSync::class)->run();

    $latest = app(SyncStatus::class)->latest();

    expect($latest['outcome'])->toBe(SyncOutcome::Rejected)
        ->and($latest['errors'])->toBe(['business/services/ac-repair.md: price_policy must be one of none, starting_at, fixed (you wrote maybe)']);
});

it('shows the later of the live version last check and the newest rejected attempt', function () {
    $this->freezeTime();
    activateExampleFolder();
    breakFile('business/services/ac-repair.md', fn (string $contents) => str_replace('price_policy: starting_at', 'price_policy: maybe', $contents));
    app(FolderSync::class)->run();

    $this->travel(5)->minutes();
    breakFile('business/services/ac-repair.md', fn (string $contents) => str_replace('price_policy: maybe', 'price_policy: starting_at', $contents));
    app(FolderSync::class)->run();

    expect(app(SyncStatus::class)->latest()['outcome'])->toBe(SyncOutcome::Unchanged);
});

it('has no last sync result before any sync', function () {
    expect(app(SyncStatus::class)->latest())->toBeNull()
        ->and(app(SyncStatus::class)->active())->toBeNull();
});

it('tells the owner a sync is already running and changes nothing', function () {
    $lock = Cache::lock('icm:sync', 120);
    expect($lock->get())->toBeTrue();

    Livewire::test(BusinessFolder::class)
        ->callAction('syncNow')
        ->assertNotified(Notification::make()->title('A sync is already running. Try again in a moment.')->warning());

    expect(IcmVersion::count())->toBe(0);

    $lock->release();
});

it('shows a skipped later-sprint file as not supported yet', function () {
    writeIcmFile('automations/follow-up.md', "---\ntype: automation\nname: Follow-up\n---\nChecks in after a quote.");

    Livewire::test(BusinessFolder::class)
        ->callAction('syncNow')
        ->assertSee('Not supported yet: automations/follow-up.md');
});

it('names the live front desk and the models it falls back to', function () {
    activateExampleFolder();

    Livewire::test(BusinessFolder::class)
        ->assertSee('Front desk: Front desk answers texts with anthropic claude-sonnet-5-5, then openai gpt-6.1-sol.');
});

it('says the front desk is off when no agent file answers texts', function () {
    File::delete(icmPath('agents/front-desk.md'));
    activateExampleFolder();

    Livewire::test(BusinessFolder::class)
        ->assertSee('Front desk: off. No agent file answers texts, so customer texts wait for you here.');
});

it('lists what always waits for the owner: the checked phrases and the never_say list', function () {
    activateExampleFolder();

    Livewire::test(BusinessFolder::class)
        ->assertSee('Always held for you')
        ->assertSee('Price words')
        ->assertSee('Discount phrases')
        ->assertSee('discount')
        ->assertSee('Promise phrases')
        ->assertSee('guarantee')
        ->assertSee('Never say')
        ->assertSee('cheapest');
});

it('says nothing always waits yet while no version is live', function () {
    Livewire::test(BusinessFolder::class)->assertDontSee('Always held for you');
});

it('escapes a never_say phrase the owner wrote when it is listed as always held', function () {
    breakFile('voice/how-we-talk.md', fn (string $text) => str_replace('never_say: [guaranteed, cheapest, no problem]', 'never_say: [guaranteed, "<b>loud</b>"]', $text));
    activateExampleFolder();

    $page = Livewire::test(BusinessFolder::class);

    expect($page->instance()->alwaysHeld()['Never say'])->toBe(['guaranteed', '<b>loud</b>']);

    $page->assertSee('&lt;b&gt;loud&lt;/b&gt;', false)->assertDontSeeHtml('<b>loud</b>');
});

it('warns when alerts go by text but no one has a phone number on their profile', function () {
    activateExampleFolder();

    Livewire::test(BusinessFolder::class)
        ->assertSee('Alerts are set to go by text, but no one has a phone number on their profile. Add yours on your profile page so held drafts reach you.');
});

it('does not warn about alerts once someone has a phone number', function () {
    activateExampleFolder();
    User::factory()->withPhone()->create();

    Livewire::test(BusinessFolder::class)
        ->assertDontSee('no one has a phone number on their profile');
});

it('shows the last alert that was not sent with its reason', function () {
    activateExampleFolder();
    OwnerAlert::factory()->create(['failed_at' => now()->subHour(), 'failure' => 'Twilio refused the number']);
    OwnerAlert::factory()->create(['failed_at' => now(), 'failure' => 'No phone on file']);

    Livewire::test(BusinessFolder::class)
        ->assertSee('The last alert was not sent: No phone on file')
        ->assertDontSee('Twilio refused the number');
});

it('shows no failed alert line when every alert went out', function () {
    activateExampleFolder();
    OwnerAlert::factory()->create(['sent_at' => now()]);

    Livewire::test(BusinessFolder::class)->assertDontSee('The last alert was not sent');
});

it('escapes the reason an alert failed', function () {
    activateExampleFolder();
    OwnerAlert::factory()->create(['failed_at' => now(), 'failure' => '<b>loud</b>']);

    Livewire::test(BusinessFolder::class)->assertDontSeeHtml('<b>loud</b>');
});
