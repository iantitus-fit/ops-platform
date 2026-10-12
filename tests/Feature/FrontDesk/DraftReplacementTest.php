<?php

use App\Ai\FrontDeskPrompt;
use App\Enums\DraftResolution;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Tuesday 2026-10-13 at the given business-zone time, the owner with a phone, and Dana's first text answered by a
 * held draft with the given label.
 */
function draftReplacementFirstDraftHeld(string $label = 'price', string $at = '2026-10-13 10:00'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone('+12025550199')->create();

    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you about that.', 'label' => $label]);

    runQueuedFrontDeskJobs();
}

/**
 * Dana texts again and the front desk answers that burst with a held draft of the given label.
 */
function draftReplacementSecondDraftHeld(string $label = 'price'): void
{
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('d', 32), 'Body' => 'Also, do you do heat pumps?']))->assertOk();
    scriptFrontDeskReplies(['reply' => 'Mike will text you about both.', 'label' => $label]);

    runQueuedFrontDeskJobs();
}

/**
 * The drafts the front desk wrote, oldest first.
 *
 * @return list<Message>
 */
function draftReplacementAgentDrafts(): array
{
    return Message::query()->where('kind', MessageKind::AgentReply->value)->orderBy('id')->get()->all();
}

it('withdraws the old draft as replaced and keeps it when a newer text gets a new draft (D-28)', function () {
    draftReplacementFirstDraftHeld();
    draftReplacementSecondDraftHeld();

    [$old, $new] = draftReplacementAgentDrafts();

    expect($old->status)->toBe(MessageStatus::Withdrawn)
        ->and($old->resolution)->toBe(DraftResolution::Replaced)
        ->and($old->resolved_at)->not->toBeNull()
        ->and($old->body)->toBe('Mike will text you about that.')
        ->and($new->status)->toBe(MessageStatus::Draft)
        ->and($new->resolution)->toBeNull()
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1);
});

it('sends no second alert when the replacement is held for the same kind of reason (D-22)', function () {
    draftReplacementFirstDraftHeld();
    draftReplacementSecondDraftHeld();

    $alerts = OwnerAlert::query()->get();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->reason->value)->toBe('draft_waiting')
        ->and($alerts[0]->sent_at)->not->toBeNull();
});

it('sends a second alert when the replacement is a complaint, because urgency rose (D-22)', function () {
    draftReplacementFirstDraftHeld();
    draftReplacementSecondDraftHeld('complaint');

    $alerts = OwnerAlert::query()->orderBy('id')->get();
    [, $new] = draftReplacementAgentDrafts();

    expect($alerts)->toHaveCount(2)
        ->and($alerts[1]->reason->value)->toBe('complaint')
        ->and($alerts[1]->urgent)->toBeTrue()
        ->and($alerts[1]->message_id)->toBe($new->id);
});

it('alerts for the replacement when the first alert never reached the owner, so the owner is not left unaware', function () {
    draftReplacementFirstDraftHeld('price', '2026-10-13 20:00');

    expect(OwnerAlert::query()->sole()->sent_at)->toBeNull();

    draftReplacementSecondDraftHeld();

    [, $new] = draftReplacementAgentDrafts();

    expect(OwnerAlert::query()->count())->toBe(2)
        ->and(OwnerAlert::query()->orderByDesc('id')->first()->message_id)->toBe($new->id);
});

it('refuses a second live draft in one conversation at the database (D-28)', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->for($conversation)->draft()->create();

    expect(fn () => Message::factory()->for($conversation)->draft()->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows a draft in each of two conversations, and a new draft once the old one is withdrawn', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->for($conversation)->draft()->create();
    Message::factory()->draft()->create();
    Message::factory()->for($conversation)->withdrawn()->create();

    expect(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(2);
});

it('leaves a withdrawn draft out of the history the next run reads', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->for($conversation)->create(['body' => 'My AC stopped']);
    Message::factory()->for($conversation)->withdrawn()->create(['body' => 'An old held draft']);
    $new = Message::factory()->for($conversation)->create(['body' => 'Hello?']);

    $history = app(FrontDeskPrompt::class)->history($conversation, Message::query()->whereKey($new->id)->get());

    expect(array_map(fn ($turn) => [$turn->role->value, $turn->content], $history))->toBe([
        ['user', 'My AC stopped'],
        ['assistant', '(No reply was sent.)'],
    ]);
});

it('sends one alert for a chain of replacements, not one per draft (D-22)', function () {
    draftReplacementFirstDraftHeld();
    draftReplacementSecondDraftHeld();

    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('e', 32), 'Body' => 'Hello? Anyone there?']))->assertOk();
    scriptFrontDeskReplies(['reply' => 'Mike will text you about all of it.', 'label' => 'price']);
    runQueuedFrontDeskJobs();

    $drafts = draftReplacementAgentDrafts();

    expect($drafts)->toHaveCount(3)
        ->and(array_map(fn (Message $draft) => $draft->status, $drafts))->toBe([MessageStatus::Withdrawn, MessageStatus::Withdrawn, MessageStatus::Draft])
        ->and(OwnerAlert::query()->count())->toBe(1);
});
