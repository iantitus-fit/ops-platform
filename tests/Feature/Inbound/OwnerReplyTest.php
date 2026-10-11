<?php

use App\Actions\SendOwnerReply;
use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\CustomerRecordChanged;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Livewire\ConversationThread;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * An open conversation with one question from the customer, and the owner signed in.
 */
function openConversationWithQuestion(): Conversation
{
    test()->actingAs(User::factory()->create());

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'Is Tuesday OK?']);

    return $conversation;
}

it('sends the owner reply through the text channel and stores it as sent', function () {
    $conversation = openConversationWithQuestion();

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', 'Tuesday at 9 works')
        ->call('sendReply')
        ->assertHasNoErrors();

    $reply = Message::query()->where('direction', MessageDirection::Out)->sole();

    expect($reply->author)->toBe(MessageAuthor::Owner)
        ->and($reply->channel)->toBe(MessageChannel::Sms)
        ->and($reply->status)->toBe(MessageStatus::Sent)
        ->and($reply->provider)->toBe('fake')
        ->and($reply->provider_message_id)->toStartWith('fake-')
        ->and($reply->body)->toBe('Tuesday at 9 works')
        ->and($reply->conversation_id)->toBe($conversation->id);

    $activity = $conversation->contact->activities()->where('type', ActivityType::ReplySent)->sole();

    expect($activity->subject_id)->toBe($reply->id)
        ->and(app(FakeTextChannel::class)->sent())->toHaveCount(1)
        ->and(app(FakeTextChannel::class)->sent()[0])->toMatchArray([
            'to' => '+12025550123',
            'body' => 'Tuesday at 9 works',
            'provider_message_id' => $reply->provider_message_id,
        ]);
});

it('announces that the customer record changed when the owner replies', function () {
    $conversation = openConversationWithQuestion();
    Event::fake([CustomerRecordChanged::class]);

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', 'Tuesday at 9 works')
        ->call('sendReply');

    Event::assertDispatched(
        CustomerRecordChanged::class,
        fn (CustomerRecordChanged $event) => $event->contactId === $conversation->contact_id,
    );
});

it('loads the conversation view for a signed-in owner and shows the thread', function () {
    $conversation = openConversationWithQuestion();

    $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))
        ->assertOk()
        ->assertSee('Is Tuesday OK?');
});

it('shows a disabled reply box and the email when the contact has no phone', function () {
    $conversation = openConversationWithQuestion();
    $conversation->contact->update(['phone_e164' => null, 'email' => 'dana@example.com']);

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSee('No phone number on file. Reply by email: dana@example.com');

    expect($thread->html())->toMatch('/<textarea[^>]*\sdisabled/');

    $thread->set('body', 'Tuesday at 9 works')
        ->call('sendReply')
        ->assertHasErrors(['body']);

    expect(Message::query()->where('direction', MessageDirection::Out)->exists())->toBeFalse()
        ->and(app(FakeTextChannel::class)->sent())->toBeEmpty();
});

it('says so when the contact has neither a phone nor an email', function () {
    $conversation = openConversationWithQuestion();
    $conversation->contact->update(['phone_e164' => null, 'email' => null]);

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSee('No phone number or email on file.');

    expect(fn () => app(SendOwnerReply::class)->handle($conversation, 'Hello'))
        ->toThrow(ValidationException::class, 'No phone number or email on file.');
});

it('refuses an empty or whitespace-only reply and sends nothing', function (string $body) {
    $conversation = openConversationWithQuestion();

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', $body)
        ->call('sendReply')
        ->assertHasErrors(['body'])
        ->assertSee('Type a message first.');

    expect(Message::query()->where('direction', MessageDirection::Out)->exists())->toBeFalse()
        ->and(app(FakeTextChannel::class)->sent())->toBeEmpty();
})->with(['empty' => [''], 'spaces and breaks' => ["  \n \t "]]);

it('refuses a reply over 1,600 characters and sends one of exactly 1,600', function () {
    $conversation = openConversationWithQuestion();

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', str_repeat('a', 1601))
        ->call('sendReply')
        ->assertHasErrors(['body'])
        ->assertSee('Keep replies to 1,600 characters or fewer.');

    expect(app(FakeTextChannel::class)->sent())->toBeEmpty();

    $accented = str_repeat('é', 1600);

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', $accented)
        ->call('sendReply')
        ->assertHasNoErrors();

    expect(app(FakeTextChannel::class)->sent()[0]['body'])->toBe($accented);
});

it('stores and sends emoji and accents unchanged', function () {
    $conversation = openConversationWithQuestion();
    $body = '¡Perfecto! Nos vemos el martes 👍';

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', $body)
        ->call('sendReply')
        ->assertHasNoErrors();

    expect(Message::query()->where('direction', MessageDirection::Out)->sole()->body)->toBe($body)
        ->and(app(FakeTextChannel::class)->sent()[0]['body'])->toBe($body);
});

it('replaces the reply box on a closed conversation and refuses the reply', function () {
    $this->actingAs(User::factory()->create());
    $conversation = Conversation::factory()->closed()->create();

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSee('This conversation is closed. Reopen it to reply.')
        ->set('body', 'Hello')
        ->call('sendReply')
        ->assertHasErrors(['body']);

    expect(fn () => app(SendOwnerReply::class)->handle($conversation, 'Hello'))
        ->toThrow(ValidationException::class, 'This conversation is closed. Reopen it to reply.')
        ->and(app(FakeTextChannel::class)->sent())->toBeEmpty();
});

it('refuses a reply when the conversation was closed after the page loaded', function () {
    $conversation = openConversationWithQuestion();

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation]);

    Conversation::query()->whereKey($conversation->id)->update(['status' => 'closed', 'closed_at' => now()]);

    $thread->set('body', 'Hello')->call('sendReply')->assertHasErrors(['body']);

    expect(app(FakeTextChannel::class)->sent())->toBeEmpty();
});

it('stores a reply the text channel could not send as failed and never as sent', function () {
    $conversation = openConversationWithQuestion();
    $this->app->bind(TextChannel::class, fn () => new class extends FakeTextChannel
    {
        public function send(string $toE164, string $body): string
        {
            throw new RuntimeException('Carrier rejected the number');
        }
    });
    Event::fake([CustomerRecordChanged::class]);

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', 'Tuesday at 9 works')
        ->call('sendReply')
        ->assertNotified(Notification::make()->title('The reply was not sent: Carrier rejected the number')->danger())
        ->assertSet('body', 'Tuesday at 9 works');

    $reply = Message::query()->where('direction', MessageDirection::Out)->sole();

    expect($reply->status)->toBe(MessageStatus::Failed)
        ->and($reply->meta['error'])->toBe('Carrier rejected the number')
        ->and($reply->provider_message_id)->toBeNull()
        ->and(Message::query()->where('status', MessageStatus::Sent)->exists())->toBeFalse()
        ->and($conversation->contact->activities()->where('type', ActivityType::Note)->sole()->summary)
        ->toBe('Reply not sent: Carrier rejected the number')
        ->and($conversation->contact->activities()->where('type', ActivityType::ReplySent)->exists())->toBeFalse();

    Event::assertDispatched(CustomerRecordChanged::class);
});

it('lists the thread in creation order, with same-second messages in arrival order', function () {
    Carbon::setTestNow('2026-10-11 10:00:00');
    $conversation = openConversationWithQuestion();
    Message::factory()->for($conversation)->create(['body' => 'Second question from Dana']);

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', 'Our answer')
        ->call('sendReply')
        ->assertSeeInOrder(['Is Tuesday OK?', 'Second question from Dana', 'Our answer']);
});
