<?php

use App\Filament\Resources\Conversations\ConversationResource;
use App\Livewire\ConversationThread;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Livewire\Livewire;

const CONVERSATION_VIEW_DRAFT_WORDS = 'Mike will text you a price for a new unit.';

const CONVERSATION_VIEW_FIRST_TEXT = 'How much for a new unit?';

/**
 * Dana's thread as the alert link opens it: her question, a held draft waiting on the owner, and the owner signed in.
 * Returns the conversation.
 */
function conversationViewWithLiveDraft(): Conversation
{
    test()->actingAs(User::factory()->create());

    $conversation = Conversation::factory()
        ->for(Contact::factory()->create(['name' => 'Dana Whitfield']))
        ->create(['last_message_at' => now()]);

    Message::factory()->for($conversation)->create([
        'body' => CONVERSATION_VIEW_FIRST_TEXT,
        'created_at' => now()->subMinutes(10),
    ]);
    Message::factory()->for($conversation)->draft()->create([
        'body' => CONVERSATION_VIEW_DRAFT_WORDS,
        'created_at' => now()->subMinutes(5),
    ]);

    return $conversation;
}

/**
 * The inline style on the first message wrapper written by the given author, or null when there is none.
 */
function conversationViewStyleFor(string $html, string $author): ?string
{
    preg_match('/data-author="'.$author.'"[^>]*style="([^"]*)"/', $html, $matches);

    return $matches[1] ?? null;
}

it('puts the held reason, the draft and Send ahead of the thread, with the customer details after them (D-29)', function () {
    $conversation = conversationViewWithLiveDraft();

    $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))
        ->assertOk()
        ->assertSeeInOrder([
            'Held: asks about price',
            // The draft box: its words are filled in by the browser, so the marker is the box itself.
            'wire:model="draftBody"',
            'Send',
            CONVERSATION_VIEW_FIRST_TEXT,
        ], escape: false);
});

it('collapses the customer details section so the draft comes first on a phone (D-29)', function () {
    $conversation = conversationViewWithLiveDraft();

    $html = $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))->getContent();

    $sendAt = strpos($html, 'wire:click="discardDraft"');
    $customerHeadingAt = preg_match('/fi-section-header-heading[^>]*>\s*Customer\s*</', $html, $matches, PREG_OFFSET_CAPTURE)
        ? $matches[0][1]
        : false;

    expect($sendAt)->not->toBeFalse()
        ->and($customerHeadingAt)->not->toBeFalse()
        ->and($customerHeadingAt)->toBeGreaterThan($sendAt)
        ->and($html)->toContain('fi-collapsed');
});

it('opens the thread at the latest message inside a bounded box (D-29)', function () {
    $conversation = conversationViewWithLiveDraft();

    $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))
        ->assertSee('x-init="$el.scrollTop = $el.scrollHeight"', false)
        ->assertSee('max-height: 60vh', false)
        ->assertSee('overflow-y: auto', false);
});

it('labels and styles customer, front desk and owner messages differently (D-29)', function () {
    $conversation = conversationViewWithLiveDraft();
    Message::factory()->for($conversation)->outbound()->create(['body' => 'Thanks, Mike will be in touch.']);
    Message::factory()->for($conversation)->fromOwner()->create(['body' => 'I can come Thursday morning.']);

    $html = $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))->getContent();

    $styles = [
        conversationViewStyleFor($html, 'customer'),
        conversationViewStyleFor($html, 'agent'),
        conversationViewStyleFor($html, 'owner'),
    ];

    expect($styles)->each->not->toBeNull()
        ->and(array_unique($styles))->toHaveCount(3);

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSeeInOrder([
            'Customer',
            CONVERSATION_VIEW_FIRST_TEXT,
            'Front desk',
            'Thanks, Mike will be in touch.',
            'You',
            'I can come Thursday morning.',
        ]);
});

it('gives the reply box and the draft box inner padding (D-29)', function () {
    $conversation = conversationViewWithLiveDraft();

    $html = Livewire::test(ConversationThread::class, ['conversation' => $conversation])->html();

    expect(preg_match_all('/<textarea[^>]*style="[^"]*padding: 0\.5rem 0\.75rem/', $html))->toBe(2);
});

it('flags a held draft whose alert could not be sent (D-19)', function () {
    $conversation = conversationViewWithLiveDraft();
    $draft = Message::query()->where('conversation_id', $conversation->id)->where('status', 'draft')->sole();
    OwnerAlert::factory()->create([
        'conversation_id' => $conversation->id,
        'message_id' => $draft->id,
        'failed_at' => now(),
        'failure' => 'No phone on file',
    ]);

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSee('Not alerted: No phone on file. Add your phone on your profile.');

    expect($thread->instance()->draftNotAlerted)->toBe('Not alerted: No phone on file. Add your phone on your profile.');
});

it('shows no flag when the alert for the draft was sent (D-19)', function () {
    $conversation = conversationViewWithLiveDraft();
    $draft = Message::query()->where('conversation_id', $conversation->id)->where('status', 'draft')->sole();
    OwnerAlert::factory()->create([
        'conversation_id' => $conversation->id,
        'message_id' => $draft->id,
        'sent_at' => now(),
    ]);

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertDontSee('Not alerted');

    expect($thread->instance()->draftNotAlerted)->toBeNull();
});
