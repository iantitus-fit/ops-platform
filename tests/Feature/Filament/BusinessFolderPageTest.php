<?php

use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Filament\Pages\BusinessFolder;
use App\Icm\FolderSync;
use App\Icm\SyncStatus;
use App\Models\IcmVersion;
use App\Models\OwnerAlert;
use App\Models\User;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

require_once __DIR__.'/../Walks/WalkHelpers.php';

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

/**
 * Make the live version look like one from Phase 1, when owner_alert in rules/sending.md was a
 * sentence the owner wrote instead of the channels, right_away and after_hours settings.
 */
function businessFolderPhaseOneVersion(): IcmVersion
{
    $version = activateExampleFolder();

    $compiled = $version->compiled;
    $compiled['files']['rules/sending.md']['front_matter']['owner_alert'] = 'Text the owner when a draft is waiting or a customer asks for a person';
    $version->update(['compiled' => $compiled]);

    app()->forgetScopedInstances();

    return $version;
}

it('opens the page and Sync Now while a Phase 1 version is live', function () {
    businessFolderPhaseOneVersion();

    $this->get('/admin/business-folder')->assertOk()->assertSee('Sync Now');
});

it('syncs forward from the dashboard while a Phase 1 version is live', function () {
    businessFolderPhaseOneVersion();
    breakFile('business/faq.md', fn (string $contents) => $contents."\nWe answer texts all day.\n");

    Livewire::test(BusinessFolder::class)
        ->callAction('syncNow')
        ->assertNotified();

    $newest = IcmVersion::query()->where('status', VersionStatus::Active->value)->latest('id')->firstOrFail();

    expect($newest->compiled['files']['rules/sending.md']['front_matter']['owner_alert'])->toBeArray();
});

it('warns about a missing phone while a Phase 1 version is live, and not once a phone is on file', function () {
    businessFolderPhaseOneVersion();

    Livewire::test(BusinessFolder::class)
        ->assertSee('Alerts are set to go by text, but no one has a phone number on their profile');

    User::factory()->withPhone()->create();

    Livewire::test(BusinessFolder::class)
        ->assertDontSee('no one has a phone number on their profile');
});

const BUSINESS_FOLDER_OFFER_PATH = 'business/offers/first-visit-promotion.md';

const BUSINESS_FOLDER_STALE_OFFER_WARNING = 'First visit promotion has run since 2026-10-01 with no end date. Add an end date, or check it is still meant to run.';

/**
 * The example offer with its end date taken out, so it runs until someone removes it.
 */
function businessFolderOfferWithoutEndDate(): void
{
    breakFile(BUSINESS_FOLDER_OFFER_PATH, fn (string $text) => str_replace("end_date: 2026-12-31\n", '', $text));
}

it('does not warn about an offer with no end date on day 90 (D-16)', function () {
    businessFolderOfferWithoutEndDate();
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-12-30 12:00', 'America/Chicago'));

    $page = Livewire::test(BusinessFolder::class)->assertDontSee('with no end date');

    expect($page->instance()->staleOfferWarnings())->toBe([]);
});

it('warns about an offer with no end date on day 91, in the business time zone (D-16)', function () {
    businessFolderOfferWithoutEndDate();
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-12-31 12:00', 'America/Chicago'));

    $page = Livewire::test(BusinessFolder::class)->assertSee(BUSINESS_FOLDER_STALE_OFFER_WARNING);

    expect($page->instance()->staleOfferWarnings())->toBe([BUSINESS_FOLDER_STALE_OFFER_WARNING]);
});

it('counts the day in the business time zone, not UTC (D-16)', function () {
    businessFolderOfferWithoutEndDate();
    activateExampleFolder();
    // 9 pm on day 90 in Chicago is already the 31st in UTC.
    $this->travelTo(Carbon::parse('2026-12-30 21:00', 'America/Chicago'));

    expect(Livewire::test(BusinessFolder::class)->instance()->staleOfferWarnings())->toBe([]);
});

it('does not warn about an offer that has an end date, however late (D-16)', function () {
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2027-06-01 12:00', 'America/Chicago'));

    $page = Livewire::test(BusinessFolder::class)->assertDontSee('with no end date');

    expect($page->instance()->staleOfferWarnings())->toBe([]);
});

it('does not warn about an offer that has not started yet (D-16)', function () {
    businessFolderOfferWithoutEndDate();
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-09-01 12:00', 'America/Chicago'));

    expect(Livewire::test(BusinessFolder::class)->instance()->staleOfferWarnings())->toBe([]);
});

it('warns about no offers and breaks nothing while no version is live (D-16)', function () {
    $page = Livewire::test(BusinessFolder::class)->assertOk();

    expect($page->instance()->staleOfferWarnings())->toBe([]);
});

it('escapes an offer name in the forgotten-offer warning (T-02.1-23)', function () {
    breakFile(BUSINESS_FOLDER_OFFER_PATH, fn (string $text) => str_replace(["end_date: 2026-12-31\n", 'name: First visit promotion'], ['', 'name: "<b>loud</b> deal"'], $text));
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-12-31 12:00', 'America/Chicago'));

    Livewire::test(BusinessFolder::class)
        ->assertSee('&lt;b&gt;loud&lt;/b&gt; deal has run since', false)
        ->assertDontSeeHtml('<b>loud</b>');
});

it('still lets the folder go live while the forgotten-offer warning shows (D-16)', function () {
    businessFolderOfferWithoutEndDate();
    $this->travelTo(Carbon::parse('2026-12-31 12:00', 'America/Chicago'));

    $page = Livewire::test(BusinessFolder::class)->callAction('syncNow');

    $label = IcmVersion::sole()->label();

    $page->assertNotified("Live as {$label} (".exampleFolderFileCount().' files)')
        ->assertSee(BUSINESS_FOLDER_STALE_OFFER_WARNING);
});

it('lists an unrecorded walk under the last sync with no command (D-05, D-11)', function () {
    $version = activateExampleFolder();
    layWalk('tests/price.md', pricingWalkText());

    $page = Livewire::test(BusinessFolder::class)->callAction('syncNow');

    $notifications = new Notifications;
    $notifications->mount();
    $sent = $notifications->notifications->sole();

    expect($sent->getTitle())->toBe("Rejected. {$version->label()} stays live.")
        ->and($sent->getStatus())->toBe('danger')
        ->and($sent->getBody())->toContain('this walk has no recording yet')
        ->not->toContain('walks:record');

    $page->assertSee("tests/price.md: this walk has no recording yet. Recording it is Ian's step. {$version->label()} stays live.")
        ->assertDontSee('walks:record');

    expect(IcmVersion::query()->where('status', VersionStatus::Active->value)->sole()->id)->toBe($version->id);
});

it('escapes a walk line on the page and in the notification (T-10-39)', function () {
    activateExampleFolder();
    seedRecording(layWalk('tests/price.md', pricingWalkText()), pricingWalkTape());
    breakFile('business/services/ac-repair.md', fn (string $text) => preg_replace(
        ['/^name: AC repair$/m', '/^price_policy: .*$/m', '/^price_cents: .*\n/m', '/^price_sentences:\n  - sentence: .*\n/m'],
        ['name: AC <i>sneaky</i> repair', 'price_policy: none', '', ''],
        $text,
    ));

    $page = Livewire::test(BusinessFolder::class)->callAction('syncNow');

    $notifications = new Notifications;
    $notifications->mount();
    $body = $notifications->notifications->map(fn (Notification $notification) => $notification->getBody())->implode(' ');

    expect($body)->toContain('the reply did not carry the price sentence for AC &lt;i&gt;sneaky&lt;/i&gt; repair')
        ->not->toContain('<i>sneaky</i>');

    $page->assertSee('the reply did not carry the price sentence for AC &lt;i&gt;sneaky&lt;/i&gt; repair', false)
        ->assertDontSeeHtml('<i>sneaky</i>');
});

it('shows a stale walk exactly as a passing sync (D-04, rulings 1 and 4)', function () {
    activateExampleFolder();
    seedRecording(layWalk('tests/price.md', pricingWalkText()), pricingWalkTape(), str_repeat('0', 64));

    $page = Livewire::test(BusinessFolder::class)->callAction('syncNow');

    $live = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();

    $notifications = new Notifications;
    $notifications->mount();
    $sent = $notifications->notifications->sole();

    expect($live->label())->toBe('v2')
        ->and($sent->getTitle())->toBe('Live as v2 ('.(exampleFolderFileCount() + 1).' files)')
        ->and($sent->getStatus())->toBe('success')
        ->and($sent->getBody())->toBeNull();

    $page->assertSee('Live')
        ->assertDontSee('stale')
        ->assertDontSee('walks:record')
        ->assertDontSee('Rejected');
});
