<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Jobs\ProcessInbound;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;

/**
 * A Twilio-shaped form body for the fake text webhook.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function twilioText(array $overrides = []): array
{
    return array_merge([
        'MessageSid' => 'SM'.str_repeat('a', 32),
        'From' => '+12025550123',
        'To' => '+13125550147',
        'Body' => 'My AC is blowing warm air',
        'NumMedia' => '0',
    ], $overrides);
}

it('records a text from a new number as a contact, conversation, message and timeline entry', function () {
    Queue::fake();

    $response = $this->post(route('webhooks.text.fake'), twilioText());

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/xml')
        ->and($response->getContent())->toContain('<Response></Response>');

    $contact = Contact::sole();
    expect($contact->phone_e164)->toBe('+12025550123')
        ->and($contact->status)->toBe(ContactStatus::Lead)
        ->and($contact->lead_source_key)->toBe('text');

    $conversation = Conversation::sole();
    expect($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->status)->toBe(ConversationStatus::Open);

    $message = Message::sole();
    expect($message->conversation_id)->toBe($conversation->id)
        ->and($message->channel)->toBe(MessageChannel::Sms)
        ->and($message->direction)->toBe(MessageDirection::In)
        ->and($message->author)->toBe(MessageAuthor::Customer)
        ->and($message->status)->toBe(MessageStatus::Received)
        ->and($message->provider)->toBe('fake')
        ->and($message->provider_message_id)->toBe('SM'.str_repeat('a', 32))
        ->and($message->body)->toBe('My AC is blowing warm air');

    $activity = Activity::sole();
    expect($activity->contact_id)->toBe($contact->id)
        ->and($activity->type)->toBe(ActivityType::TextReceived)
        ->and($activity->subject->is($message))->toBeTrue();

    Queue::assertPushed(ProcessInbound::class, 1);
    Queue::assertPushed(ProcessInbound::class, fn (ProcessInbound $job) => $job->messageId === $message->id);
});
