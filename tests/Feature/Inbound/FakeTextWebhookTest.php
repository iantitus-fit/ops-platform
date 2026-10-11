<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\CustomerRecordChanged;
use App\Jobs\ProcessInbound;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
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

it('puts a second text from the same number in the same open conversation', function () {
    $this->post(route('webhooks.text.fake'), twilioText(['MessageSid' => 'SM1']))->assertOk();
    $this->post(route('webhooks.text.fake'), twilioText(['MessageSid' => 'SM2', 'Body' => 'Still warm']))->assertOk();

    expect(Contact::count())->toBe(1)
        ->and(Conversation::count())->toBe(1)
        ->and(Message::count())->toBe(2)
        ->and(Activity::where('type', ActivityType::TextReceived)->count())->toBe(2);
});

it('starts a new open conversation when the last one was closed and keeps the closed one', function () {
    $this->post(route('webhooks.text.fake'), twilioText(['MessageSid' => 'SM1']))->assertOk();

    $first = Conversation::sole();
    $first->update(['status' => ConversationStatus::Closed, 'closed_at' => now()]);

    $this->post(route('webhooks.text.fake'), twilioText(['MessageSid' => 'SM2', 'Body' => 'Back again']))->assertOk();

    $second = Contact::sole()->openConversation;

    expect(Conversation::count())->toBe(2)
        ->and($second->id)->not->toBe($first->id)
        ->and($first->fresh()->status)->toBe(ConversationStatus::Closed)
        ->and($first->messages)->toHaveCount(1)
        ->and($second->messages->sole()->body)->toBe('Back again');
});

it('refuses a second open conversation for one contact at the database', function () {
    $contact = Contact::factory()->create();
    Conversation::factory()->for($contact)->create();

    expect(fn () => Conversation::factory()->for($contact)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('records a retried webhook once and queues nothing the second time', function () {
    Queue::fake();
    Event::fake([CustomerRecordChanged::class]);

    $this->post(route('webhooks.text.fake'), twilioText())->assertOk();
    $this->post(route('webhooks.text.fake'), twilioText())->assertOk();

    expect(Message::count())->toBe(1)
        ->and(Activity::count())->toBe(1);

    Queue::assertPushed(ProcessInbound::class, 1);
    Event::assertDispatchedTimes(CustomerRecordChanged::class, 1);
});

it('announces that the customer record changed once a new text is recorded', function () {
    Queue::fake();
    Event::fake([CustomerRecordChanged::class]);

    $this->post(route('webhooks.text.fake'), twilioText())->assertOk();

    Event::assertDispatched(
        CustomerRecordChanged::class,
        fn (CustomerRecordChanged $event) => $event->contactId === Contact::sole()->id,
    );
});

it('stores a 1,600 character body in full', function () {
    $body = str_repeat('x', 1600);

    $this->post(route('webhooks.text.fake'), twilioText(['Body' => $body]))->assertOk();

    expect(mb_strlen(Message::sole()->body))->toBe(1600)
        ->and(Message::sole()->body)->toBe($body);
});

it('records a picture-only text with an empty body and the media count', function () {
    $this->post(route('webhooks.text.fake'), twilioText(['Body' => '', 'NumMedia' => '1']))->assertOk();

    $message = Message::sole();

    expect($message->body)->toBe('')
        ->and($message->meta['num_media'])->toBe(1);
});

it('rejects a text with no From and records nothing', function () {
    $payload = twilioText();
    unset($payload['From']);

    $this->post(route('webhooks.text.fake'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['From']);

    expect(Contact::count())->toBe(0)
        ->and(Message::count())->toBe(0);
});

it('rejects a From that is not a readable phone number and records nothing', function () {
    $this->post(route('webhooks.text.fake'), twilioText(['From' => '12345']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['From']);

    expect(Contact::count())->toBe(0)
        ->and(Message::count())->toBe(0);
});

it('stores emoji and accented text unchanged', function () {
    $body = 'Hola, ¿pueden venir hoy? 🥵';

    $this->post(route('webhooks.text.fake'), twilioText(['Body' => $body]))->assertOk();

    expect(Message::sole()->body)->toBe($body);
});

it('resolves different ways of writing one number to a single contact', function () {
    foreach (['+12025550123', '(202) 555-0123', '202.555.0123'] as $index => $from) {
        $this->post(route('webhooks.text.fake'), twilioText(['MessageSid' => 'SM'.$index, 'From' => $from]))->assertOk();
    }

    expect(Contact::sole()->phone_e164)->toBe('+12025550123')
        ->and(Message::count())->toBe(3);
});

it('keeps arrival order for texts recorded in the same second', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 19:32:00', 'UTC'));

    $this->post(route('webhooks.text.fake'), twilioText(['MessageSid' => 'SM1', 'Body' => 'first']))->assertOk();
    $this->post(route('webhooks.text.fake'), twilioText(['MessageSid' => 'SM2', 'Body' => 'second']))->assertOk();

    $conversation = Conversation::sole();

    expect($conversation->messages->pluck('body')->all())->toBe(['first', 'second'])
        ->and($conversation->messages->pluck('id')->all())->toBe(Message::orderBy('id')->pluck('id')->all());

    Carbon::setTestNow();
});
