<?php

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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
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
