<?php

use App\Actions\OpenProject;
use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\MessageDirection;
use App\Enums\ProjectActor;
use App\Filament\Widgets\NewLeads;
use App\Jobs\ProcessInbound;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\User;
use App\Rooms\Leads\OpenProjectForNewLead;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Livewire\Livewire;

/**
 * The card file the records writer made for one contact, whatever its name.
 */
function openOnNewLeadCard(Contact $contact): string
{
    $files = File::glob(icmPath("records/customers/*-{$contact->id}.md"));

    expect($files)->toHaveCount(1);

    return File::get($files[0]);
}

it('opens a project when a text comes from a new number', function () {
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-10-29 14:02', 'America/Chicago'));

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    $contact = Contact::sole();
    $project = Project::sole();
    $activity = Activity::query()->where('type', ActivityType::ProjectOpened->value)->sole();
    $card = openOnNewLeadCard($contact);

    expect($contact->status)->toBe(ContactStatus::Lead)
        ->and($project->contact_id)->toBe($contact->id)
        ->and($project->opened_by)->toBe(ProjectActor::Lead)
        ->and($project->opened_by_user_id)->toBeNull()
        ->and($project->closed_at)->toBeNull()
        ->and($project->conversation_id)->toBe(Message::sole()->conversation_id)
        ->and($activity->summary)->toBe('Opened by a new lead')
        ->and($card)->toContain("## Jobs\n\n- Job 1: open, opened 2026-10-29 14:02 by a new lead")
        ->and($card)->toContain('· Job 1 opened');

    Queue::assertPushed(ProcessInbound::class, 1);
});

it('opens a project when a web form matches no one', function () {
    activateExampleFolder();

    $this->postJson(route('leads.store'), [
        'name' => 'Dana Whitfield',
        'phone' => '+12025550199',
        'email' => 'dana@example.com',
        'source' => 'angi',
        'message' => 'Need a quote',
    ])->assertOk();

    $contact = Contact::sole();
    $project = Project::sole();

    expect($project->contact_id)->toBe($contact->id)
        ->and($project->conversation_id)->toBe(Message::sole()->conversation_id)
        ->and(openOnNewLeadCard($contact))->toContain('## Jobs');
});

it('opens nothing for a text from a known number', function () {
    activateExampleFolder();
    Contact::factory()->create(['phone_e164' => '+12025550123']);

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    expect(Project::count())->toBe(0);
});

it('opens nothing for a form that matches by phone written another way', function () {
    activateExampleFolder();
    Contact::factory()->create(['phone_e164' => '+12025550123']);

    $this->postJson(route('leads.store'), [
        'name' => 'Dana Whitfield',
        'phone' => '(202) 555-0123',
        'email' => 'someone-new@example.com',
        'source' => 'angi',
        'message' => 'Need a quote',
    ])->assertOk();

    expect(Contact::count())->toBe(1)
        ->and(Project::count())->toBe(0);
});

it('opens nothing for a form that matches by email in another letter case', function () {
    activateExampleFolder();
    Contact::factory()->create(['email' => 'dana@example.com']);

    $this->postJson(route('leads.store'), [
        'name' => 'Dana Whitfield',
        'phone' => '+12025550177',
        'email' => 'DANA@Example.com',
        'source' => 'angi',
        'message' => 'Need a quote',
    ])->assertOk();

    expect(Contact::count())->toBe(1)
        ->and(Project::count())->toBe(0);
});

it('opens nothing for a contact made any other way', function () {
    activateExampleFolder();

    $contact = Contact::factory()->create();
    Conversation::factory()->create(['contact_id' => $contact->id]);

    expect(Project::count())->toBe(0);
});

it('opens nothing for a first text that is STOP', function () {
    activateExampleFolder();

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['Body' => 'STOP', 'OptOutType' => 'STOP']))->assertOk();

    expect(Contact::sole()->isOptedOut())->toBeTrue()
        ->and(Project::count())->toBe(0);
});

it('opens a project for a first START or HELP', function (string $body) {
    activateExampleFolder();

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['Body' => $body]))->assertOk();

    expect(Project::sole()->contact_id)->toBe(Contact::sole()->id);
})->with(['START', 'HELP']);

it('opens a project for a picture-only first text', function () {
    activateExampleFolder();

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['Body' => '', 'NumMedia' => '1', 'MediaUrl0' => 'https://api.twilio.test/media/ME1', 'MediaContentType0' => 'image/jpeg']))->assertOk();

    expect(Project::sole()->contact_id)->toBe(Contact::sole()->id);
});

it('keeps the new lead a lead and in New leads, and texts and alerts no one', function () {
    activateExampleFolder();
    $this->actingAs(User::factory()->create());

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    $contact = Contact::sole();

    expect(Project::count())->toBe(1)
        ->and($contact->status)->toBe(ContactStatus::Lead)
        ->and(OwnerAlert::count())->toBe(0)
        ->and(Message::query()->where('direction', MessageDirection::Out->value)->exists())->toBeFalse();

    Livewire::test(NewLeads::class)->assertCanSeeTableRecords([$contact]);
});

/**
 * The folder with the leads room left out, as a business that does not use it would have it. Nothing is synced yet.
 */
function openOnNewLeadWithoutTheRoom(): void
{
    File::deleteDirectory(icmPath('rooms/leads'));
}

/**
 * A text from a number the business has not seen, with the open held back so a test can run it by hand.
 */
function openOnNewLeadHeldJob(): OpenProjectForNewLead
{
    Queue::fake([ProcessInbound::class, OpenProjectForNewLead::class]);

    test()->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    return Queue::pushed(OpenProjectForNewLead::class)->first();
}

it('queues nothing while the leads room is off', function () {
    openOnNewLeadWithoutTheRoom();
    activateExampleFolder();
    Queue::fake([ProcessInbound::class, OpenProjectForNewLead::class]);

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    Queue::assertNotPushed(OpenProjectForNewLead::class);
    expect(Project::count())->toBe(0);
});

it('queues one open carrying the new contact while the leads room is on', function () {
    activateExampleFolder();
    Queue::fake([ProcessInbound::class, OpenProjectForNewLead::class]);

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    Queue::assertPushed(OpenProjectForNewLead::class, 1);
    expect(Queue::pushed(OpenProjectForNewLead::class)->first()->contactId)->toBe(Contact::sole()->id);
});

it('opens nothing when the room is off by the time the job runs', function () {
    activateExampleFolder();
    $job = openOnNewLeadHeldJob();

    openOnNewLeadWithoutTheRoom();
    activateExampleFolder();

    app()->call([$job, 'handle']);

    expect(Project::count())->toBe(0);
});

it('backfills nothing when the room is turned on later', function () {
    openOnNewLeadWithoutTheRoom();
    activateExampleFolder();

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();
    $earlier = Contact::sole();

    File::copyDirectory(base_path('examples/example-hvac/rooms/leads'), icmPath('rooms/leads'));
    activateExampleFolder();

    expect(Project::count())->toBe(0);

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('d', 32), 'From' => '+12025550188']))->assertOk();

    $later = Contact::query()->where('phone_e164', '+12025550188')->sole();

    expect($earlier->projects()->count())->toBe(0)
        ->and(Project::sole()->contact_id)->toBe($later->id);
});

it('does nothing for a contact who already has a project', function () {
    activateExampleFolder();
    $job = openOnNewLeadHeldJob();
    $contact = Contact::sole();

    app(OpenProject::class)->byHand($contact, User::factory()->create());

    app()->call([$job, 'handle']);

    expect(Project::sole()->opened_by)->toBe(ProjectActor::User);
});

it('opens a project for a lead whose first text was real and who texted STOP before the job ran', function () {
    activateExampleFolder();
    $job = openOnNewLeadHeldJob();

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('e', 32), 'Body' => 'STOP', 'OptOutType' => 'STOP']))->assertOk();

    app()->call([$job, 'handle']);

    $contact = Contact::sole();

    expect(Project::sole()->contact_id)->toBe($contact->id)
        ->and($contact->isOptedOut())->toBeTrue();
});

it('leaves one project and one timeline entry when the job is retried', function () {
    activateExampleFolder();
    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();
    $contact = Contact::sole();

    app()->call([new OpenProjectForNewLead($contact->id), 'handle']);

    expect(Project::count())->toBe(1)
        ->and(Activity::query()->where('type', ActivityType::ProjectOpened->value)->count())->toBe(1);
});

it('loses a race without an error and leaves one project', function () {
    activateExampleFolder();
    $job = openOnNewLeadHeldJob();
    $contact = Contact::sole();
    $raced = false;

    DB::listen(function (QueryExecuted $query) use (&$raced, $contact): void {
        if ($raced || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'from "projects"')) {
            return;
        }

        $raced = true;

        DB::table('projects')->insert([
            'contact_id' => $contact->id,
            'opened_by' => ProjectActor::Lead->value,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    app()->call([$job, 'handle']);

    expect($raced)->toBeTrue()
        ->and(Project::count())->toBe(1)
        ->and(Activity::query()->where('type', ActivityType::ProjectOpened->value)->count())->toBe(0);
});

it('never breaks the inbound when the open cannot be queued', function () {
    activateExampleFolder();
    Exceptions::fake();

    $queue = new class(app(), [ProcessInbound::class], Queue::getFacadeRoot()) extends QueueFake
    {
        public function push($job, $data = '', $queue = null)
        {
            if ($job instanceof OpenProjectForNewLead) {
                throw new RuntimeException('queue down');
            }

            return parent::push($job, $data, $queue);
        }
    };

    Queue::swap($queue);

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    $queue->assertPushed(ProcessInbound::class);
    expect(Message::count())->toBe(1)
        ->and(Project::count())->toBe(0);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'queue down');
});

it('never breaks the inbound when the open throws', function () {
    activateExampleFolder();
    Exceptions::fake();

    $this->app->bind(OpenProject::class, fn () => new class extends OpenProject
    {
        public function forNewLead(Contact $contact, Activity $arrival): ?Project
        {
            throw new RuntimeException('open failed');
        }
    });

    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    Queue::assertPushed(ProcessInbound::class, 1);
    expect(Message::count())->toBe(1)
        ->and(Project::count())->toBe(0);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'open failed');
});

it('retries a failed open', function () {
    $job = new OpenProjectForNewLead(1);

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([5, 30, 120, 300]);
});
