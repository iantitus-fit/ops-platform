<?php

use App\Channels\FakeTextChannel;
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
use Illuminate\Support\Facades\Event;
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
