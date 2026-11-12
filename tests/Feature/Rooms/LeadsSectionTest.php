<?php

use App\Actions\OpenProject;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Filament\Resources\Contacts\RelationManagers\ProjectsRelationManager;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Records\CustomerCard;
use App\Rooms\RoomSection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * What the leads section drew, from "<div data-room-section="leads">" to the end of the dashboard's section wrapper.
 */
function leadsSectionHtml(string $html): string
{
    expect(preg_match('/<div data-room-section="leads">(.*?)<\/div>\s*<\/div>/s', $html, $section))->toBe(1);

    return $section[1];
}

/**
 * A fresh open project for a new contact, opened by hand by the given user, at the given moment.
 */
function leadsSectionProject(?User $user = null, string $name = 'Dana Whitfield', ?string $openedAt = null): Project
{
    $contact = Contact::factory()->create(['name' => $name]);

    return Project::factory()
        ->for($contact)
        ->openedByUser($user ?? test()->owner)
        ->create(['opened_at' => $openedAt === null ? now() : Carbon::parse($openedAt, 'America/Chicago')]);
}

/**
 * D-16: a project a new lead opened says which lead source the contact came from, by the name in lead-sources.md; a key
 * the file does not list shows as written, and no key at all shows as unknown.
 */
it('names the lead source of a project a new lead opened', function (?string $key, string $expected) {
    activateExampleFolder();

    $contact = Contact::factory()->fromSource('angi', 4500)->create(['name' => 'Dana Whitfield', 'lead_source_key' => $key]);
    Project::factory()->for($contact)->openedByLead()->create();

    $this->get('/admin')->assertOk()->assertSee($expected);
})->with([
    'a listed source' => ['angi', 'source Angi'],
    'a source the file does not list' => ['yelp', 'source yelp'],
    'no source' => [null, 'source unknown'],
]);

/**
 * D-16: a project opened by hand names the person, never the contact's first source, and a deleted user reads as a
 * removed user.
 */
it('names who opened a project by hand', function () {
    activateExampleFolder();

    $contact = Contact::factory()->fromSource('angi', 4500)->create(['name' => 'Dana Whitfield']);
    app(OpenProject::class)->byHand($contact, $this->owner);

    $this->get('/admin')->assertOk()
        ->assertSee('opened by Ian Owner')
        ->assertDontSee('source Angi');

    $this->owner->delete();
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertOk()
        ->assertSee('opened by a removed user')
        ->assertDontSee('opened by Ian Owner');
});

it('lists only open projects, newest first', function () {
    activateExampleFolder();

    leadsSectionProject(name: 'Alder Older', openedAt: '2026-10-28 09:00');
    leadsSectionProject(name: 'Birch Newer', openedAt: '2026-10-29 09:00');
    Project::factory()->for(Contact::factory()->create(['name' => 'Cedar Closed']))->closed()->create();

    $section = leadsSectionHtml($this->get('/admin')->assertOk()->getContent());

    expect($section)->toContain('Birch Newer')->toContain('Alder Older')->not->toContain('Cedar Closed')
        ->and(strpos($section, 'Birch Newer'))->toBeLessThan(strpos($section, 'Alder Older'));
});

it('lists both open projects of one contact', function () {
    activateExampleFolder();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    app(OpenProject::class)->byHand($contact, $this->owner);
    app(OpenProject::class)->byHand($contact, $this->owner);

    $section = leadsSectionHtml($this->get('/admin')->assertOk()->getContent());

    expect(substr_count($section, 'data-leads-row'))->toBe(2)
        ->and($section)->toContain('Job 1')->toContain('Job 2');
});

it('says when there is no open project', function () {
    activateExampleFolder();

    $this->get('/admin')->assertOk()->assertSee('No open jobs.');
});

/**
 * The cap: at most 20 rows, and past 20 one line giving the total. 19 and 20 draw every row and no line; 21 draws 20 and
 * the line.
 */
it('shows the newest 20 and says how many there are', function (int $open) {
    activateExampleFolder();

    Project::factory()->count($open)->create();

    $response = $this->get('/admin')->assertOk();

    expect(substr_count($response->getContent(), 'data-leads-row'))->toBe(min($open, 20));

    if ($open > 20) {
        $response->assertSee("Showing the newest 20 of {$open} open jobs.");
    } else {
        $response->assertDontSee('Showing the newest');
    }
})->with([19, 20, 21]);

it('uses the business\'s word', function () {
    breakFile('business/profile.md', fn (string $contents): string => str_replace('project_word: Job', 'project_word: Matter', $contents));
    activateExampleFolder();

    $this->get('/admin')->assertOk()->assertSee('No open matters.');

    app(OpenProject::class)->byHand(Contact::factory()->create(), $this->owner);

    $this->get('/admin')->assertOk()->assertSee('Matter 1')->assertDontSee('No open matters.');
});

/**
 * D-15, D-10: turning the room off hides the section and only the section. The project stays on the contact's screen and
 * on the card.
 */
it('hides when the leads room is off, and the project stays elsewhere', function () {
    activateExampleFolder();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    app(OpenProject::class)->byHand($contact, $this->owner);

    $this->get('/admin')->assertOk()->assertSee('data-room-section="leads"', false);

    File::deleteDirectory(icmPath('rooms/leads'));
    activateExampleFolder();

    $this->get('/admin')->assertOk()->assertDontSee('data-room-section="leads"', false);

    Livewire::test(ProjectsRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => ViewContact::class])
        ->assertSee('Job 1');

    expect(app(CustomerCard::class)->render($contact->fresh()))->toContain('## Jobs');
});

/**
 * 05 D-32: a section that cannot load shows one line, reports the error and leaves the rest of the dashboard standing.
 * The table is renamed, not dropped: appointments.project_id points at it, and Postgres refuses to drop it.
 */
it('shows one line when the section cannot load', function () {
    activateExampleFolder();

    Message::factory()
        ->for(Conversation::factory()->for(Contact::factory()->create(['name' => 'Dana Whitfield'])))
        ->draft()
        ->create();

    Exceptions::fake();
    Schema::rename('projects', 'projects_unavailable');

    $response = $this->get('/admin')->assertOk();

    expect(leadsSectionHtml($response->getContent()))->toContain(RoomSection::FAILED_LINE);

    $response->assertSee('Drafts waiting');
});

/**
 * Rows load their contact and opening user up front, so drawing 10 rows costs the same queries as drawing 3. One request
 * first warms the caches that are not the section's.
 */
it('runs the same number of queries for 3 and for 10 rows', function () {
    activateExampleFolder();

    foreach (range(1, 3) as $number) {
        leadsSectionProject(User::factory()->create(), "Customer {$number}");
    }

    $this->get('/admin')->assertOk();

    DB::enableQueryLog();
    $this->get('/admin')->assertOk();
    $withThree = count(DB::getQueryLog());
    DB::flushQueryLog();

    foreach (range(4, 10) as $number) {
        leadsSectionProject(User::factory()->create(), "Customer {$number}");
    }

    $this->get('/admin')->assertOk();
    DB::flushQueryLog();

    $response = $this->get('/admin')->assertOk();
    $withTen = count(DB::getQueryLog());

    expect(substr_count($response->getContent(), 'data-leads-row'))->toBe(10)
        ->and($withTen)->toBe($withThree);
});
