<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ProjectActor;
use App\Jobs\ProcessInbound;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

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
