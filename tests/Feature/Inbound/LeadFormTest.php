<?php

use App\Enums\ActivityType;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;

/**
 * The fields a website lead form posts to POST /leads.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function leadFormFields(array $overrides = []): array
{
    return array_merge([
        'name' => 'Dana Whitfield',
        'phone' => '(202) 555-0123',
        'email' => 'dana@example.com',
        'message' => 'Need AC looked at this week',
        'source' => 'angi',
    ], $overrides);
}

it('lands an Angi form from someone who already texted on the same contact with Angi\'s cost', function () {
    activateExampleFolder();

    $this->post(route('webhooks.text.fake'), [
        'MessageSid' => 'SM'.str_repeat('b', 32),
        'From' => '+12025550123',
        'To' => '+13125550147',
        'Body' => 'Hi, my AC stopped',
        'NumMedia' => '0',
    ])->assertOk();

    $this->postJson(route('leads.store'), leadFormFields())
        ->assertOk()
        ->assertExactJson(['received' => true]);

    $contact = Contact::sole();
    $conversation = Conversation::sole();
    expect($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->messages)->toHaveCount(2);

    $message = $conversation->messages->last();
    expect($message->channel)->toBe(MessageChannel::Web)
        ->and($message->direction)->toBe(MessageDirection::In)
        ->and($message->author)->toBe(MessageAuthor::Customer)
        ->and($message->status)->toBe(MessageStatus::Received)
        ->and($message->body)->toBe('Need AC looked at this week')
        ->and($message->meta['form']['source'])->toBe('angi');

    $activity = Activity::query()->where('type', ActivityType::FormReceived)->sole();
    expect($activity->contact_id)->toBe($contact->id)
        ->and($activity->lead_source_key)->toBe('angi')
        ->and($activity->cost_cents)->toBe(4500);
});

it('creates a contact with the source and cost for a form from a new person', function () {
    activateExampleFolder();

    $this->postJson(route('leads.store'), leadFormFields(['phone' => '312-555-0162', 'email' => 'new@example.com']))
        ->assertOk();

    $contact = Contact::sole();
    expect($contact->phone_e164)->toBe('+13125550162')
        ->and($contact->lead_source_key)->toBe('angi')
        ->and($contact->acquisition_cost_cents)->toBe(4500);
});
