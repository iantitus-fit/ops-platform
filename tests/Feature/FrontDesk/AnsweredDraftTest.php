<?php

use App\Actions\ResolveDraft;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\DraftResolution;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Jobs\SendDueAlerts;
use App\Livewire\ConversationThread;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\Records\CustomerCard;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Mockery\MockInterface;

const ANSWERED_DRAFT_OWNER_PHONE = '+12025550199';

const ANSWERED_DRAFT_CUSTOMER_PHONE = '+12025550123';

const ANSWERED_DRAFT_AC_SENTENCE = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

const ANSWERED_DRAFT_LARGE_SENTENCE = 'For a larger system, 3 tons and up, a replacement system typically runs $5,000 to $10,000 installed.';

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

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's first text answered with the given
 * script, so a draft W waits with its holding reply and its one alert. Returns the owner.
 *
 * @param  array<string, mixed>  $reply
 */
function answeredDraftQuestionHeld(string $body, array $reply): User
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    $owner = User::factory()->withPhone(ANSWERED_DRAFT_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
    scriptFrontDeskReplies($reply);
    runQueuedFrontDeskJobs();

    return $owner;
}

/**
 * Dana texts again a minute later and the front desk answers that burst with the given script.
 *
 * @param  array<string, mixed>  $reply
 */
function answeredDraftCustomerTexts(string $body, array $reply): void
{
    static $count = 0;
    $count++;

    test()->travel(1)->minutes();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'a', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function answeredDraftTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === ANSWERED_DRAFT_CUSTOMER_PHONE,
    ));
}

/**
 * The words as Dana receives them after the first automated text: the sign-off closes them, the disclosure is left out.
 */
function answeredDraftLater(string $words): string
{
    return $words."\n".app(ActiveConfig::class)->voice()['sign_off'];
}

/**
 * Take the replacement system's default sentence out of the folder, so a "Not sure" size has no sentence on file.
 */
function answeredDraftWithoutDefaultSentence(): void
{
    breakFile('business/services/system-replacement.md', fn (string $text) => preg_replace('/^price_default:\n(?:  .*\n)+/m', '', $text));
}

/**
 * The model's script for the first text of a replacement-system price question whose size is "Not sure", which the
 * folder without a default sentence cannot price.
 *
 * @return array<string, mixed>
 */
function answeredDraftNotSureScript(): array
{
    return [
        'reply' => '',
        'service_slug' => 'system-replacement',
        'price_services' => ['system-replacement'],
        'intake_answers' => [['question_key' => 'unit_size', 'option' => 'Not sure', 'raw_text' => 'Not sure what size I have']],
    ];
}

/**
 * The model's script for the later text that says the size is large, with the touches flag the row names.
 *
 * @return array<string, mixed>
 */
function answeredDraftLargeScript(?bool $touches): array
{
    return [
        'reply' => '',
        'service_slug' => 'system-replacement',
        'price_services' => ['system-replacement'],
        'intake_answers' => [['question_key' => 'unit_size', 'option' => 'Large (3 tons and up)', 'raw_text' => "I checked, it's about 4 tons"]],
        'touches_waiting' => $touches,
    ];
}

it('closes a price draft that named no service when the next text brings its sentence and touches it (D-21)', function () {
    $owner = answeredDraftQuestionHeld('How much is a repair?', ['reply' => 'Mike will text you the repair price.', 'label' => 'price']);
    $waiting = answeredDraftTheDraft();

    test()->actingAs($owner);

    $thread = Livewire::test(ConversationThread::class, ['conversation' => Conversation::query()->sole()]);

    expect($waiting->meta['price_slugs'])->toBe([])
        ->and($waiting->meta['price_open'])->toBe([]);

    answeredDraftCustomerTexts("It's the AC", ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $sentence = Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole();
    $waiting->refresh();

    expect(answeredDraftTextsToDana())->toHaveCount(2)
        ->and(answeredDraftTextsToDana()[1]['body'])->toBe(answeredDraftLater(ANSWERED_DRAFT_AC_SENTENCE))
        ->and($waiting->status)->toBe(MessageStatus::Withdrawn)
        ->and($waiting->resolution)->toBe(DraftResolution::Answered)
        ->and($waiting->meta['answered_by'])->toBe($sentence->id)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(Conversation::query()->sole()->contact->activities()->where('type', ActivityType::DraftAnswered)->count())->toBe(1);

    $thread->call('approveDraft')
        ->assertNotified(Notification::make()->title('Already answered')->danger());

    expect(answeredDraftTextsToDana())->toHaveCount(2);
});

it('sends the sentence and leaves a draft that named no service live when the text does not clearly touch it (D-21)', function (?bool $touches) {
    answeredDraftQuestionHeld('How much is a repair?', ['reply' => 'Mike will text you the repair price.', 'label' => 'price']);
    $waiting = answeredDraftTheDraft();

    answeredDraftCustomerTexts("It's the AC", ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => $touches]);

    $live = answeredDraftTheDraft();

    expect(answeredDraftTextsToDana())->toHaveCount(2)
        ->and(answeredDraftTextsToDana()[1]['body'])->toBe(answeredDraftLater(ANSWERED_DRAFT_AC_SENTENCE))
        ->and($live->id)->toBe($waiting->id)
        ->and($live->body)->toBe($waiting->body)
        ->and($live->gate)->toBe($waiting->gate)
        ->and($live->resolution)->toBeNull()
        ->and(OwnerAlert::query()->count())->toBe(1);
})->with([
    'touches_waiting false' => [false],
    'touches_waiting missing' => [null],
]);

it('closes a price draft with the sentence for its own service, whatever the model read about the new text (D-21)', function (?bool $touches) {
    answeredDraftWithoutDefaultSentence();

    answeredDraftQuestionHeld('How much for a new system? Not sure what size I have.', answeredDraftNotSureScript());
    $waiting = answeredDraftTheDraft();

    expect($waiting->gate_reason)->toBe('price:no_sentence')
        ->and($waiting->body)->toBe('')
        ->and($waiting->meta['price_slugs'])->toBe(['system-replacement'])
        ->and($waiting->meta['price_open'])->toBe(['system-replacement'])
        ->and(answeredDraftTextsToDana())->toHaveCount(1)
        ->and(OwnerAlert::query()->count())->toBe(1);

    answeredDraftCustomerTexts("I checked, it's about 4 tons", answeredDraftLargeScript($touches));

    $sentence = Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole();
    $waiting->refresh();

    expect(answeredDraftTextsToDana())->toHaveCount(2)
        ->and(answeredDraftTextsToDana()[1]['body'])->toBe(answeredDraftLater(ANSWERED_DRAFT_LARGE_SENTENCE))
        ->and($waiting->resolution)->toBe(DraftResolution::Answered)
        ->and($waiting->meta['answered_by'])->toBe($sentence->id)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(1);
})->with([
    'touches_waiting true' => [true],
    'touches_waiting false' => [false],
    'touches_waiting missing' => [null],
]);

it('never closes a draft that waits on one service with the sentence for another (D-21)', function () {
    answeredDraftWithoutDefaultSentence();

    answeredDraftQuestionHeld('How much for a new system? Not sure what size I have.', answeredDraftNotSureScript());
    $waiting = answeredDraftTheDraft();

    answeredDraftCustomerTexts('How much is an AC repair?', ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $live = answeredDraftTheDraft();

    expect(answeredDraftTextsToDana())->toHaveCount(2)
        ->and(answeredDraftTextsToDana()[1]['body'])->toBe(answeredDraftLater(ANSWERED_DRAFT_AC_SENTENCE))
        ->and($live->id)->toBe($waiting->id)
        ->and($live->body)->toBe($waiting->body)
        ->and($live->gate)->toBe($waiting->gate)
        ->and($live->resolution)->toBeNull();
});

it('never closes a draft whose own run already sent a sentence for every service it looked up (D-21)', function () {
    answeredDraftQuestionHeld('How much is the AC diagnostic, and is it more on weekends?', [
        'reply' => 'Mike will tell you about weekend pricing.',
        'label' => 'price',
        'price_services' => ['ac-repair'],
    ]);
    $waiting = answeredDraftTheDraft();

    expect($waiting->gate_reason)->toBe('label:price')
        ->and($waiting->meta['price_slugs'])->toBe(['ac-repair'])
        ->and($waiting->meta['price_open'])->toBe([])
        ->and(answeredDraftTextsToDana())->toHaveCount(1)
        ->and(answeredDraftTextsToDana()[0]['body'])->toContain(ANSWERED_DRAFT_AC_SENTENCE);

    answeredDraftCustomerTexts('What was the diagnostic price again?', ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $live = answeredDraftTheDraft();

    expect(answeredDraftTextsToDana())->toHaveCount(2)
        ->and(answeredDraftTextsToDana()[1]['body'])->toContain(ANSWERED_DRAFT_AC_SENTENCE)
        ->and($live->id)->toBe($waiting->id)
        ->and($live->resolution)->toBeNull();
});

it('sends the sentence but never closes a draft held on price and a complaint (D-21)', function (string $reply) {
    fakeClassifier()->answer('faq', 0.95, ['price' => 0.9, 'complaint' => 0.9]);

    answeredDraftQuestionHeld('How much for a new unit? Your last tech was rude.', ['reply' => 'Sorry about that. Mike will text you.', 'label' => 'faq_answer']);
    $waiting = answeredDraftTheDraft();

    expect($waiting->gate['checks']['classifier']['reading']['reasons'])->toBe(['classifier:complaint', 'classifier:price']);

    fakeClassifier()->answer('faq');

    answeredDraftCustomerTexts("It's the AC", ['reply' => $reply, 'label' => 'faq_answer', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $waiting->refresh();
    $live = answeredDraftTheDraft();
    $sentToDana = collect(answeredDraftTextsToDana())->contains(fn (array $text): bool => str_contains($text['body'], ANSWERED_DRAFT_AC_SENTENCE));

    expect($sentToDana)->toBeTrue()
        ->and($waiting->resolution)->not->toBe(DraftResolution::Answered)
        ->and(Message::query()->where('resolution', DraftResolution::Answered->value)->count())->toBe(0)
        ->and($reply === '' ? $live->id === $waiting->id : $live->id !== $waiting->id)->toBeTrue();
})->with([
    'a fresh reply replaces the draft' => ['Happy to help with your AC.'],
    'a blank reply leaves the draft live' => [''],
]);

it('leaves the draft live when the sentence could not be sent (D-21)', function () {
    answeredDraftQuestionHeld('How much is a repair?', ['reply' => 'Mike will text you the repair price.', 'label' => 'price']);
    $waiting = answeredDraftTheDraft();

    $this->mock(TextChannel::class, function (MockInterface $channel) {
        $channel->shouldReceive('name')->andReturn('fake');
        $channel->shouldReceive('send')->andThrow(new RuntimeException('carrier down'));
    });

    answeredDraftCustomerTexts("It's the AC", ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $live = answeredDraftTheDraft();

    expect($live->id)->toBe($waiting->id)
        ->and($live->resolution)->toBeNull()
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->sole()->status)->toBe(MessageStatus::Failed)
        ->and(Conversation::query()->sole()->contact->activities()->where('type', ActivityType::DraftAnswered)->count())->toBe(0);
});
