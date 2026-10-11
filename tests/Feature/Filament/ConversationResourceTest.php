<?php

use App\Actions\CloseConversation;
use App\Actions\ReopenConversation;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Events\CustomerRecordChanged;
use App\Filament\Resources\Conversations\Pages\ListConversations;
use App\Filament\Resources\Conversations\Pages\ViewConversation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists conversations with the most recent message first', function () {
    $oldest = Conversation::factory()->create(['last_message_at' => now()->subDays(3)]);
    $newest = Conversation::factory()->create(['last_message_at' => now()->subMinute()]);
    $middle = Conversation::factory()->create(['last_message_at' => now()->subDay()]);

    Livewire::test(ListConversations::class)
        ->assertCanSeeTableRecords([$newest, $middle, $oldest], inOrder: true)
        ->assertSee($newest->contact->phone_e164);
});

it('closes an open conversation and notes it on the timeline', function () {
    $conversation = Conversation::factory()->create();

    Livewire::test(ViewConversation::class, ['record' => $conversation->id])
        ->assertActionVisible('close')
        ->assertActionHidden('reopen')
        ->callAction('close');

    $conversation->refresh();

    expect($conversation->status)->toBe(ConversationStatus::Closed)
        ->and($conversation->closed_at)->not->toBeNull()
        ->and($conversation->contact->activities()->where('type', ActivityType::Note)->sole()->summary)
        ->toBe('Conversation closed by owner');
});

it('reopens a closed conversation when the contact has no other open one', function () {
    $conversation = Conversation::factory()->closed()->create();

    Livewire::test(ViewConversation::class, ['record' => $conversation->id])
        ->assertActionVisible('reopen')
        ->assertActionHidden('close')
        ->callAction('reopen');

    $conversation->refresh();

    expect($conversation->status)->toBe(ConversationStatus::Open)
        ->and($conversation->closed_at)->toBeNull()
        ->and($conversation->contact->activities()->where('type', ActivityType::Note)->sole()->summary)
        ->toBe('Conversation reopened by owner');
});

it('hides the reopen action when the contact already has another open conversation', function () {
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $closed = Conversation::factory()->closed()->for($contact)->create();
    $open = Conversation::factory()->for($contact)->create();

    Livewire::test(ViewConversation::class, ['record' => $closed->id])
        ->assertActionHidden('reopen');

    expect(fn () => app(ReopenConversation::class)->handle($closed))
        ->toThrow(DomainException::class, 'Dana Whitfield already has an open conversation. Reply there instead.');

    expect($closed->refresh()->status)->toBe(ConversationStatus::Closed)
        ->and($open->refresh()->status)->toBe(ConversationStatus::Open);
});

it('names a contact without a name by phone when reopening is refused', function () {
    $contact = Contact::factory()->create(['name' => null, 'phone_e164' => '+12025550123']);
    $closed = Conversation::factory()->closed()->for($contact)->create();
    Conversation::factory()->for($contact)->create();

    expect(fn () => app(ReopenConversation::class)->handle($closed))
        ->toThrow(DomainException::class, '+12025550123 already has an open conversation. Reply there instead.');
});

it('announces that the customer record changed when a conversation is closed or reopened', function () {
    $conversation = Conversation::factory()->create();
    Event::fake([CustomerRecordChanged::class]);

    app(CloseConversation::class)->handle($conversation);
    app(ReopenConversation::class)->handle($conversation);

    Event::assertDispatchedTimes(CustomerRecordChanged::class, 2);
});

it('does nothing when a conversation that is already closed is closed again', function () {
    $conversation = Conversation::factory()->create();
    $stale = Conversation::query()->findOrFail($conversation->id);

    app(CloseConversation::class)->handle($conversation);
    $closedAt = $conversation->fresh()->closed_at;

    Event::fake([CustomerRecordChanged::class]);
    $this->travel(5)->minutes();

    // The second tab still holds the record as it was when it was open.
    app(CloseConversation::class)->handle($stale);

    expect($conversation->fresh()->closed_at->equalTo($closedAt))->toBeTrue()
        ->and($conversation->contact->activities()->where('type', ActivityType::Note)->count())->toBe(1);

    Event::assertNotDispatched(CustomerRecordChanged::class);
});

it('does nothing when a conversation that is already open is reopened again', function () {
    $conversation = Conversation::factory()->closed()->create();
    $stale = Conversation::query()->findOrFail($conversation->id);

    app(ReopenConversation::class)->handle($conversation);

    Event::fake([CustomerRecordChanged::class]);

    app(ReopenConversation::class)->handle($stale);

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Open)
        ->and($conversation->contact->activities()->where('type', ActivityType::Note)->count())->toBe(1);

    Event::assertNotDispatched(CustomerRecordChanged::class);
});
