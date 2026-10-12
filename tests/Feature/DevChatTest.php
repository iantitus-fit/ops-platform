<?php

use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Livewire\DevChat;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Message;
use Livewire\Livewire;

it('returns 404 for the dev chat page outside the local environment', function () {
    $this->get('/dev/chat')->assertNotFound();
});

it('shows the dev chat page in the local environment', function () {
    $this->app['env'] = 'local';

    $this->get('/dev/chat')->assertOk()->assertSee('Dev chat');
});

it('records a text from the dev chat as a customer text on the timeline', function () {
    $this->app['env'] = 'local';

    Livewire::test(DevChat::class)
        ->set('from', '+12025550123')
        ->set('body', 'Hi from dev chat')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSee('Hi from dev chat');

    $contact = Contact::query()->where('phone_e164', '+12025550123')->sole();
    $conversation = $contact->conversations()->sole();
    $message = $conversation->messages()->sole();

    expect($conversation->status)->toBe(ConversationStatus::Open)
        ->and($message->channel)->toBe(MessageChannel::Sms)
        ->and($message->direction)->toBe(MessageDirection::In)
        ->and($message->author)->toBe(MessageAuthor::Customer)
        ->and($message->body)->toBe('Hi from dev chat')
        ->and($message->provider)->toBe('fake')
        ->and($message->provider_message_id)->toStartWith('dev-')
        ->and($contact->activities()->where('type', ActivityType::TextReceived)->count())->toBe(1);
});

it('refuses to mount the dev chat component outside the local environment', function () {
    Livewire::test(DevChat::class)->assertStatus(404);
});

it('refuses to send from the dev chat component outside the local environment', function () {
    $this->app['env'] = 'local';
    $component = Livewire::test(DevChat::class)->set('body', 'Sneaky');

    $this->app['env'] = 'production';

    $component->call('send')->assertStatus(404);

    expect(Message::query()->count())->toBe(0);
});

it('refuses an empty or whitespace-only message and records nothing', function (string $body) {
    $this->app['env'] = 'local';

    Livewire::test(DevChat::class)
        ->set('body', $body)
        ->call('send')
        ->assertHasErrors(['body'])
        ->assertSee('Type a message first.');

    expect(Message::query()->count())->toBe(0)
        ->and(Contact::query()->count())->toBe(0);
})->with(['empty' => [''], 'spaces' => ['   '], 'new lines' => ["\n \n"]]);

it('normalizes the from number before recording the text', function () {
    $this->app['env'] = 'local';

    Livewire::test(DevChat::class)
        ->set('from', '(202) 555-0123')
        ->set('body', 'Hello')
        ->call('send')
        ->assertHasNoErrors();

    expect(Contact::query()->sole()->phone_e164)->toBe('+12025550123');
});

it('refuses an unreadable from number and records nothing', function () {
    $this->app['env'] = 'local';

    Livewire::test(DevChat::class)
        ->set('from', '555-0100')
        ->set('body', 'Hello')
        ->call('send')
        ->assertHasErrors(['from'])
        ->assertSee('Use a real-format number like +12025550123.');

    expect(Message::query()->count())->toBe(0)
        ->and(Contact::query()->count())->toBe(0);
});

it('records the same text sent twice as two messages in one open conversation', function () {
    $this->app['env'] = 'local';

    $component = Livewire::test(DevChat::class)->set('from', '+12025550123');
    $component->set('body', 'Same words')->call('send');
    $component->set('body', 'Same words')->call('send');

    $conversation = Contact::query()->sole()->conversations()->sole();
    $messages = $conversation->messages()->get();

    expect($messages)->toHaveCount(2)
        ->and($messages->pluck('provider_message_id')->unique())->toHaveCount(2)
        ->and($messages->every(fn (Message $message) => str_starts_with($message->provider_message_id, 'dev-')))->toBeTrue();
});

it('shows a reply recorded outside the component on the next refresh', function () {
    $this->app['env'] = 'local';

    $component = Livewire::test(DevChat::class)
        ->set('from', '+12025550123')
        ->set('body', 'Is anyone there?')
        ->call('send');

    $conversation = Contact::query()->sole()->conversations()->sole();
    $conversation->messages()->create([
        'channel' => MessageChannel::Sms,
        'direction' => MessageDirection::Out,
        'author' => MessageAuthor::Owner,
        'body' => 'Yes, on our way',
        'status' => 'sent',
    ]);

    $component->assertDontSee('Yes, on our way')->call('$refresh')->assertSee('Yes, on our way');
});

it('shows nothing in the thread for a number that has not texted yet', function () {
    $this->app['env'] = 'local';

    Livewire::test(DevChat::class)->assertSee('No messages yet.');

    expect(Activity::query()->count())->toBe(0);
});

it('opts the contact out when STOP is sent from the dev chat, the way Twilio would report it (D-39)', function () {
    $this->app['env'] = 'local';

    Livewire::test(DevChat::class)
        ->set('from', '+12025550123')
        ->set('body', 'stop')
        ->call('send')
        ->assertHasNoErrors();

    $contact = Contact::query()->where('phone_e164', '+12025550123')->sole();
    $message = Message::query()->sole();

    expect($contact->isOptedOut())->toBeTrue()
        ->and($message->meta['opt_out_type'])->toBe('STOP')
        ->and($message->handled_at)->not->toBeNull();
});
