<?php

use App\Actions\ResolveDraft;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\DraftResolution;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Livewire\DevChat;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

const DEV_CHAT_DRAFT_WORDS = 'Mike will text you a price for a new unit.';

/**
 * Tuesday 2026-10-13 at 4:30 pm in the business zone, and Dana's price question answered by a sent holding reply and
 * a held draft. Returns the owner who can send the draft.
 */
function devChatPriceQuestionHeld(): User
{
    app()['env'] = 'local';
    test()->travelTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago'));

    $owner = User::factory()->withPhone('+12025550199')->create();

    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => DEV_CHAT_DRAFT_WORDS, 'label' => 'price']);
    runQueuedFrontDeskJobs();

    return $owner;
}

/**
 * The held draft Dana's price question left.
 */
function devChatTheDraft(): Message
{
    return Message::query()->where('kind', MessageKind::AgentReply->value)->orderBy('id')->firstOrFail();
}

/**
 * The holding reply that went out beside the held draft.
 */
function devChatTheHoldingReply(): Message
{
    return Message::query()->where('kind', MessageKind::HoldingReply->value)->firstOrFail();
}

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

it('leaves a held draft off the dev chat page (G-02-2.3)', function () {
    devChatPriceQuestionHeld();
    $draft = devChatTheDraft();
    $holdingReply = devChatTheHoldingReply();
    $customerText = Message::query()->where('author', 'customer')->sole();

    $chat = Livewire::test(DevChat::class)->set('from', '+12025550123');

    expect($draft->status)->toBe(MessageStatus::Draft)
        ->and($chat->instance()->thread->pluck('id')->all())->toContain($customerText->id, $holdingReply->id)
        ->and($chat->instance()->thread->pluck('id')->all())->not->toContain($draft->id);

    $chat->assertSee('How much for a new unit?')->assertDontSee(DEV_CHAT_DRAFT_WORDS);
});

it('leaves withdrawn, queued and failed texts off the page', function () {
    $this->app['env'] = 'local';

    $contact = Contact::factory()->create(['phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();

    $discarded = Message::factory()->for($conversation)->withdrawn()->create([
        'body' => 'Discarded draft words',
        'resolution' => DraftResolution::Discarded,
    ]);
    $replaced = Message::factory()->for($conversation)->withdrawn()->create(['body' => 'Replaced draft words']);
    $queued = Message::factory()->for($conversation)->outbound()->create(['body' => 'Queued text words', 'status' => MessageStatus::Queued]);
    $failed = Message::factory()->for($conversation)->outbound()->create(['body' => 'Failed text words', 'status' => MessageStatus::Failed]);

    $chat = Livewire::test(DevChat::class)->set('from', '+12025550123');
    $ids = $chat->instance()->thread->pluck('id')->all();

    foreach ([$discarded, $replaced, $queued, $failed] as $message) {
        expect($ids)->not->toContain($message->id);
        $chat->assertDontSee($message->body);
    }
});

it("shows the customer's texts and every text that reached them", function () {
    $this->app['env'] = 'local';

    $contact = Contact::factory()->create(['phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();

    $received = Message::factory()->for($conversation)->create();
    $sent = Message::factory()->for($conversation)->outbound()->create();
    $delivered = Message::factory()->for($conversation)->outbound()->create(['status' => MessageStatus::Delivered]);
    $fromOwner = Message::factory()->for($conversation)->fromOwner()->create();

    $ids = Livewire::test(DevChat::class)->set('from', '+12025550123')->instance()->thread->pluck('id')->all();

    expect($ids)->toContain($received->id, $sent->id, $delivered->id, $fromOwner->id);
});

it('shows a held draft once the owner sends it', function () {
    $owner = devChatPriceQuestionHeld();
    $draft = devChatTheDraft();

    app(ResolveDraft::class)->approve($draft, $owner);

    $chat = Livewire::test(DevChat::class)->set('from', '+12025550123');

    expect($chat->instance()->thread->pluck('id')->all())->toContain($draft->id);
    $chat->assertSee(DEV_CHAT_DRAFT_WORDS);
});

it('names the statuses a customer has on their phone', function () {
    expect(MessageStatus::onTheCustomersPhone())->toBe([MessageStatus::Received, MessageStatus::Sent, MessageStatus::Delivered]);
});
