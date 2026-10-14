<?php

use App\Actions\ResolveDraft;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\DraftResolution;
use App\Enums\MessageStatus;
use App\Jobs\SendDueAlerts;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\Records\CustomerCard;
use Illuminate\Support\Carbon;

const ANSWERED_DRAFT_OWNER_PHONE = '+12025550199';

/**
 * Tuesday 2026-10-13 at the given business-zone time, the owner with a phone, and Dana's price question answered by a
 * held draft. Returns the owner.
 */
function answeredDraftPriceQuestionHeld(string $at = '2026-10-13 10:00'): User
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    $owner = User::factory()->withPhone(ANSWERED_DRAFT_OWNER_PHONE)->create();

    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);
    runQueuedFrontDeskJobs();

    return $owner;
}

/**
 * The held draft.
 */
function answeredDraftTheDraft(): Message
{
    return Message::query()->where('status', MessageStatus::Draft->value)->sole();
}

/**
 * A text that went out to the customer in the draft's conversation and answered the question.
 */
function answeredDraftTheAnswer(): Message
{
    return Message::factory()->for(Conversation::query()->sole())->outbound()->create([
        'body' => 'A new unit is $9,000 installed.',
    ]);
}

it('closes a draft as answered and keeps its words, its verdict and the way it ended (D-21)', function () {
    answeredDraftPriceQuestionHeld();
    $draft = answeredDraftTheDraft();
    $words = $draft->body;
    $gate = $draft->gate;
    $answer = answeredDraftTheAnswer();

    $closed = app(ResolveDraft::class)->closeAsAnswered($draft, $answer);

    $draft->refresh();
    $contact = Conversation::query()->sole()->contact;
    $activity = $contact->activities()->where('type', ActivityType::DraftAnswered)->sole();

    expect($closed)->toBeTrue()
        ->and($draft->status)->toBe(MessageStatus::Withdrawn)
        ->and($draft->resolution)->toBe(DraftResolution::Answered)
        ->and($draft->resolved_at)->not->toBeNull()
        ->and($draft->resolved_by)->toBeNull()
        ->and($draft->body)->toBe($words)
        ->and($draft->gate)->toBe($gate)
        ->and($draft->meta)->toHaveKey('in_reply_to')
        ->and($draft->meta['answered_by'])->toBe($answer->id)
        ->and($activity->summary)->toBe('Draft closed: a price sentence answered it')
        ->and($activity->subject_id)->toBe($draft->id)
        ->and(app(CustomerCard::class)->render($contact->fresh()))->toContain('Draft answered');
});

it('leaves a draft alone that a newer text already replaced, and says so (D-21)', function () {
    answeredDraftPriceQuestionHeld();
    $draft = answeredDraftTheDraft();
    $draft->update([
        'status' => MessageStatus::Withdrawn,
        'resolution' => DraftResolution::Replaced,
        'resolved_at' => now()->subMinute(),
    ]);
    $resolvedAt = $draft->refresh()->resolved_at;
    $answer = answeredDraftTheAnswer();

    $closed = app(ResolveDraft::class)->closeAsAnswered($draft, $answer);

    $draft->refresh();

    expect($closed)->toBeFalse()
        ->and($draft->status)->toBe(MessageStatus::Withdrawn)
        ->and($draft->resolution)->toBe(DraftResolution::Replaced)
        ->and($draft->resolved_at->equalTo($resolvedAt))->toBeTrue()
        ->and(Conversation::query()->sole()->contact->activities()->where('type', ActivityType::DraftAnswered)->exists())->toBeFalse();
});

it('refuses Send and Discard on an answered draft with exactly Already answered (D-21)', function (string $action) {
    $owner = answeredDraftPriceQuestionHeld();
    $draft = answeredDraftTheDraft();
    app(ResolveDraft::class)->closeAsAnswered($draft, answeredDraftTheAnswer());
    $textsBefore = count(textsSentToCustomers());

    $call = fn () => $action === 'approve'
        ? app(ResolveDraft::class)->approve($draft, $owner)
        : app(ResolveDraft::class)->discard($draft, $owner);

    expect($call)->toThrow(DomainException::class, 'Already answered')
        ->and(count(textsSentToCustomers()))->toBe($textsBefore)
        ->and($draft->refresh()->resolution)->toBe(DraftResolution::Answered);
})->with(['approve', 'discard']);

it('never tells the owner about a waiting after-hours alert once the draft is answered (D-21)', function () {
    answeredDraftPriceQuestionHeld('2026-10-13 18:00');
    $alert = OwnerAlert::query()->where('reason', AlertReason::DraftWaiting->value)->sole();

    expect($alert->due_at)->not->toBeNull()
        ->and($alert->sent_at)->toBeNull();

    app(ResolveDraft::class)->closeAsAnswered(answeredDraftTheDraft(), answeredDraftTheAnswer());

    test()->travelTo(Carbon::parse('2026-10-14 08:05', 'America/Chicago'));
    $textsBefore = count(textsSentToCustomers());

    app()->call([new SendDueAlerts, 'handle']);

    $toOwner = array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] === ANSWERED_DRAFT_OWNER_PHONE);

    expect($alert->refresh()->sent_at)->toBeNull()
        ->and($alert->skipped_at)->not->toBeNull()
        ->and($toOwner)->toBe([])
        ->and(count(textsSentToCustomers()))->toBe($textsBefore);
});
