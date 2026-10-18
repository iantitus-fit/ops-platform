<?php

use App\Enums\ConversationStatus;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Livewire\ConversationThread;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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

/**
 * The HTML of the Needs you card: from its heading to the Discard button.
 */
function conversationViewNeedsYouCard(string $html): string
{
    $start = strpos($html, 'Needs you');
    $end = strpos($html, 'wire:click="discardDraft"');

    return substr($html, $start, $end - $start);
}

it("shows the customer's last message in the Needs you card, above the draft and Send (G-02-1.1)", function () {
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago'));
    $conversation = conversationViewWithLiveDraft();
    // The helper's first text becomes the older one (it has the lower id, as a text that arrived first would), and
    // the question that is held arrives after it.
    Message::query()->where('conversation_id', $conversation->id)->where('direction', 'in')->sole()->update([
        'body' => 'Hi, my AC is acting up',
        'created_at' => now()->subMinutes(20),
    ]);
    Message::factory()->for($conversation)->create([
        'body' => CONVERSATION_VIEW_FIRST_TEXT,
        'created_at' => now()->subMinutes(10),
    ]);

    $response = $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))
        ->assertOk()
        ->assertSeeInOrder([
            'Needs you',
            'Held: asks about price',
            'Customer wrote',
            'Oct 13, 4:20 pm',
            CONVERSATION_VIEW_FIRST_TEXT,
            'wire:model="draftBody"',
            'wire:click="discardDraft"',
        ], escape: false);

    expect(conversationViewNeedsYouCard($response->getContent()))->not->toContain('Hi, my AC is acting up');

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation]);

    expect($thread->instance()->lastCustomerMessage->body)->toBe(CONVERSATION_VIEW_FIRST_TEXT);
});

it('cuts a long last message in the card and shows it whole in the thread (G-02-1.1)', function () {
    $conversation = conversationViewWithLiveDraft();
    $longText = collect(range(1, 40))->map(fn (int $number) => "word{$number} here")->implode("\n");
    expect(strlen($longText))->toBeGreaterThan(300);
    $longText .= "\n".str_repeat('tail ', 120).'TheVeryEnd';
    expect(strlen($longText))->toBeGreaterThan(600);
    Message::factory()->for($conversation)->create(['body' => $longText, 'created_at' => now()->subMinute()]);

    $html = $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))->getContent();
    $card = conversationViewNeedsYouCard($html);
    $cut = Str::limit(Str::squish($longText), 160);

    expect($cut)->toEndWith('...')
        ->and($card)->toContain(e($cut))
        ->and($card)->not->toContain('TheVeryEnd')
        ->and(substr($html, strpos($html, 'wire:click="discardDraft"')))->toContain('TheVeryEnd');
});

it("escapes the customer's words in the card (T-02-45)", function () {
    $conversation = conversationViewWithLiveDraft();
    Message::factory()->for($conversation)->create([
        'body' => '<script>alert(3)</script> help',
        'created_at' => now()->subMinute(),
    ]);

    $html = $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))->getContent();

    expect(conversationViewNeedsYouCard($html))->toContain('&lt;script&gt;alert(3)&lt;/script&gt; help')
        ->and($html)->not->toContain('<script>alert(3)');
});

it('shows no Customer wrote line without a held draft (G-02-1.1)', function () {
    $conversation = conversationViewWithLiveDraft();
    Message::query()->where('conversation_id', $conversation->id)->where('status', 'draft')->delete();

    $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))
        ->assertOk()
        ->assertSee(CONVERSATION_VIEW_FIRST_TEXT)
        ->assertDontSee('Customer wrote');
});

/**
 * Dana's thread with the draft marked as the answer to her first text, the way the front desk records it. Returns the
 * conversation.
 */
function conversationViewWithDraftAnsweringFirstText(): Conversation
{
    $conversation = conversationViewWithLiveDraft();
    $firstText = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'in')->sole();

    Message::query()->where('conversation_id', $conversation->id)->where('status', 'draft')->sole()
        ->update(['meta' => ['in_reply_to' => [$firstText->id]]]);

    return $conversation;
}

it('says the customer wrote again since the draft, above Send (D-22)', function () {
    $conversation = conversationViewWithDraftAnsweringFirstText();
    Message::factory()->for($conversation)->create(['body' => 'Also, it is leaking', 'created_at' => now()->subMinute()]);

    $response = $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))
        ->assertOk()
        ->assertSeeInOrder([
            'Needs you',
            'The customer wrote again since this draft',
            'Also, it is leaking',
            'wire:model="draftBody"',
            'Send anyway',
        ], escape: false);

    expect(conversationViewNeedsYouCard($response->getContent()))->toContain('The customer wrote again since this draft');

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation]);

    expect($thread->instance()->customerWroteAgain)->toBeTrue();
});

it('shows no wrote-again line when the customer has said nothing since the draft (D-22)', function () {
    $conversation = conversationViewWithDraftAnsweringFirstText();

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSee('Needs you')
        ->assertDontSee('The customer wrote again since this draft');

    expect($thread->instance()->customerWroteAgain)->toBeFalse();
});

it('counts a text that arrived while the run was in flight, with an id below the draft (D-22)', function () {
    $conversation = conversationViewWithDraftAnsweringFirstText();
    $draft = Message::query()->where('conversation_id', $conversation->id)->where('status', 'draft')->sole();

    // Made after the draft's burst was read but before the draft was saved: its id is between the two. Created first
    // by id order, then the draft is pushed after it so the draft keeps the highest id.
    $lateText = Message::factory()->for($conversation)->create(['body' => 'One more thing', 'created_at' => now()->subMinutes(6)]);
    $newDraft = $draft->replicate();
    $draft->delete();
    $newDraft->save();

    expect($lateText->id)->toBeLessThan($newDraft->id);

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSee('The customer wrote again since this draft');

    expect($thread->instance()->customerWroteAgain)->toBeTrue();
});

it('shows no wrote-again line on a draft that does not record what it answered (D-22)', function () {
    $conversation = conversationViewWithLiveDraft();
    Message::factory()->for($conversation)->create(['body' => 'Hello again', 'created_at' => now()->subMinute()]);

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSee('Needs you')
        ->assertDontSee('The customer wrote again since this draft');

    expect($thread->instance()->customerWroteAgain)->toBeFalse();
});

it('shows no wrote-again line without a held draft (D-22)', function () {
    $conversation = conversationViewWithLiveDraft();
    Message::query()->where('conversation_id', $conversation->id)->where('status', 'draft')->delete();

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertDontSee('The customer wrote again since this draft');

    expect($thread->instance()->customerWroteAgain)->toBeFalse();
});

/**
 * The first button on the page that runs the given Livewire action, as written in the page, or an empty string.
 */
function conversationViewButtonRunning(string $html, string $action): string
{
    preg_match('/<button[^>]*wire:target="'.$action.'"[^>]*>.*?<\/button>/s', $html, $matches);

    return $matches[0] ?? '';
}

/**
 * The words on a button, with the markup and the extra spaces taken out.
 */
function conversationViewButtonWords(string $button): string
{
    return Str::squish(strip_tags($button));
}

it('reads Send on the draft when the customer has not written again (D-48)', function () {
    $conversation = conversationViewWithDraftAnsweringFirstText();

    $html = Livewire::test(ConversationThread::class, ['conversation' => $conversation])->html();

    expect(conversationViewButtonWords(conversationViewButtonRunning($html, 'approveDraft')))->toBe('Send')
        ->and($html)->not->toContain('Send anyway');
});

it('reads Send anyway on the draft when the customer wrote again, and approving it still sends (D-48)', function () {
    $conversation = conversationViewWithDraftAnsweringFirstText();
    Message::factory()->for($conversation)->create(['body' => 'Also, it is leaking', 'created_at' => now()->subMinute()]);

    $html = Livewire::test(ConversationThread::class, ['conversation' => $conversation])->html();

    expect(conversationViewButtonWords(conversationViewButtonRunning($html, 'approveDraft')))->toBe('Send anyway');
});

it('disables the draft Send, the reply Send and Discard while their own action runs (D-48)', function () {
    $conversation = conversationViewWithLiveDraft();

    $html = Livewire::test(ConversationThread::class, ['conversation' => $conversation])->html();

    foreach (['approveDraft', 'sendReply', 'discardDraft'] as $action) {
        expect(conversationViewButtonRunning($html, $action))->toContain('wire:loading.attr="disabled"');
    }
});

it('asks before Discard does anything (D-48)', function () {
    $conversation = conversationViewWithLiveDraft();

    $html = Livewire::test(ConversationThread::class, ['conversation' => $conversation])->html();

    expect(conversationViewButtonRunning($html, 'discardDraft'))
        ->toContain('wire:confirm="Discard this draft? Nothing will be sent to the customer."');
});

it('labels the draft box and the reply box for a screen reader (D-48)', function () {
    $conversation = conversationViewWithLiveDraft();

    $html = Livewire::test(ConversationThread::class, ['conversation' => $conversation])->html();

    expect(substr_count($html, 'aria-label="Draft reply to the customer"'))->toBe(1)
        ->and(substr_count($html, 'aria-label="Reply to the customer"'))->toBe(1)
        ->and($html)->toMatch('/<textarea[^>]*wire:model="draftBody"[^>]*aria-label="Draft reply to the customer"/')
        ->and($html)->toMatch('/<textarea[^>]*wire:model="body"[^>]*aria-label="Reply to the customer"/');
});

it('labels the disabled reply box too when replies are blocked (D-48)', function () {
    $conversation = conversationViewWithLiveDraft();
    $conversation->forceFill(['status' => ConversationStatus::Closed])->save();

    $html = Livewire::test(ConversationThread::class, ['conversation' => $conversation])->html();

    expect($html)->toContain('This conversation is closed. Reopen it to reply.')
        ->and($html)->toMatch('/<textarea[^>]*disabled[^>]*aria-label="Reply to the customer"/')
        ->and($html)->not->toContain('wire:model="body"');
});

/**
 * Dana's thread, the draft answering her first text, and a newer text from her at 4:29 pm on Oct 13 (the business zone).
 * Returns the conversation.
 */
function conversationViewWithNewerText(string $body): Conversation
{
    activateExampleFolder();
    test()->travelTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago'));
    $conversation = conversationViewWithDraftAnsweringFirstText();
    Message::factory()->for($conversation)->create(['body' => $body, 'created_at' => now()->subMinute()]);

    return $conversation;
}

it("quotes the customer's newest text with its time on the wrote-again line, above Send anyway (D-48)", function () {
    $conversation = conversationViewWithNewerText('Actually it started making a grinding noise too');

    $response = $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))
        ->assertOk()
        ->assertSeeInOrder([
            'Needs you',
            'The customer wrote again since this draft',
            'Oct 13, 4:29 pm',
            'Actually it started making a grinding noise too',
            'wire:model="draftBody"',
            'Send anyway',
        ], escape: false);

    expect(conversationViewNeedsYouCard($response->getContent()))
        ->not->toContain('Customer wrote,')
        ->and(substr_count($response->getContent(), 'Actually it started making a grinding noise too'))->toBe(2);
});

it('keeps the Customer wrote line and no wrote-again line when nothing is newer than the draft (D-48)', function () {
    activateExampleFolder();
    $this->travelTo(Carbon::parse('2026-10-13 16:30', 'America/Chicago'));
    $conversation = conversationViewWithDraftAnsweringFirstText();

    $response = $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))->assertOk();
    $card = conversationViewNeedsYouCard($response->getContent());

    expect($card)->toContain('Customer wrote, Oct 13, 4:20 pm:')
        ->and($card)->toContain(CONVERSATION_VIEW_FIRST_TEXT)
        ->and($card)->not->toContain('The customer wrote again since this draft');
});

it('squishes and cuts the quoted newest text to 160 characters, multibyte letters included (D-48, ANSR-03)', function () {
    $longText = "ça  ne marche pas\n\n".str_repeat('é', 200);
    $conversation = conversationViewWithNewerText($longText);

    $card = conversationViewNeedsYouCard($this->get(ConversationResource::getUrl('view', ['record' => $conversation]))->getContent());
    $cut = Str::limit(Str::squish($longText), 160);

    expect(mb_strlen($cut))->toBeLessThanOrEqual(163)
        ->and($cut)->toEndWith('...')
        ->and($card)->toContain(e($cut))
        ->and($card)->not->toContain(str_repeat('é', 161));
});

it("escapes the customer's newest text on the wrote-again line (T-02.1-56)", function () {
    $conversation = conversationViewWithNewerText('<b>bold</b> <script>alert(4)</script>');

    $html = $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))->getContent();

    expect(conversationViewNeedsYouCard($html))->toContain('&lt;b&gt;bold&lt;/b&gt; &lt;script&gt;alert(4)&lt;/script&gt;')
        ->and($html)->not->toContain('<script>alert(4)')
        ->and($html)->not->toContain('<b>bold</b>');
});
