<?php

use App\Actions\ResolveDraft;
use App\Actions\SendFixedReply;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\DraftResolution;
use App\Enums\MessageDirection;
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
use App\SendRule\HeldReasons;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Mockery\MockInterface;

const ANSWERED_DRAFT_OWNER_PHONE = '+12025550199';

const ANSWERED_DRAFT_CUSTOMER_PHONE = '+12025550123';

const ANSWERED_DRAFT_AC_SENTENCE = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

const ANSWERED_DRAFT_PARTIAL_LINE = "I've passed your other question to Mike, and he'll follow up on it.";

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
 * Dana texts again a minute later and the front desk answers that burst with the given scripts, one per model call.
 *
 * @param  array<string, mixed>  ...$replies
 */
function answeredDraftCustomerTexts(string $body, array ...$replies): void
{
    static $count = 0;
    $count++;

    test()->travel(1)->minutes();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'a', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
    scriptFrontDeskReplies(...$replies);

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

it('holds blank words labelled promise under label:promise, and a later sentence sends without closing the draft (WR-03)', function () {
    answeredDraftWithoutDefaultSentence();

    answeredDraftQuestionHeld('How much for a new system? Not sure what size I have.', [...answeredDraftNotSureScript(), 'label' => 'promise']);
    $waiting = answeredDraftTheDraft();

    expect($waiting->gate_reason)->toBe('label:promise')
        ->and($waiting->gate['reason']['plain'])->toBe('Held: makes a promise')
        ->and($waiting->meta['price_open'])->toBe(['system-replacement'])
        ->and(Conversation::query()->sole()->contact->activities()->where('type', ActivityType::DraftHeld)->latest('id')->first()->summary)->toContain('Held: makes a promise');

    answeredDraftCustomerTexts("I checked, it's about 4 tons", answeredDraftLargeScript(true));

    $live = answeredDraftTheDraft();

    expect(answeredDraftTextsToDana())->toHaveCount(2)
        ->and(answeredDraftTextsToDana()[1]['body'])->toBe(answeredDraftLater(ANSWERED_DRAFT_LARGE_SENTENCE))
        ->and($live->id)->toBe($waiting->id)
        ->and($live->resolution)->toBeNull()
        ->and($live->meta)->not->toHaveKey('answered_by')
        ->and($live->gate_reason)->toBe('label:promise')
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1);
});

it('never closes a draft that waits on one service with the sentence for another (D-21)', function () {
    answeredDraftWithoutDefaultSentence();

    answeredDraftQuestionHeld('How much for a new system? Not sure what size I have.', answeredDraftNotSureScript());
    $waiting = answeredDraftTheDraft();

    answeredDraftCustomerTexts('How much is an AC repair?', ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $live = answeredDraftTheDraft();

    expect(answeredDraftTextsToDana())->toHaveCount(2)
        ->and(answeredDraftTextsToDana()[1]['body'])->toBe(answeredDraftLater(ANSWERED_DRAFT_AC_SENTENCE.' '.ANSWERED_DRAFT_PARTIAL_LINE))
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

/**
 * Dana's price question and a complaint in one text, held as a draft that Jev read as both. Returns the waiting draft.
 */
function answeredDraftPriceAndComplaintHeld(): Message
{
    fakeClassifier()->answer('faq', 0.95, ['price' => 0.9, 'complaint' => 0.9]);

    answeredDraftQuestionHeld('How much for a new unit? Your last tech was rude.', ['reply' => 'Sorry about that. Mike will text you.', 'label' => 'faq_answer']);
    $waiting = answeredDraftTheDraft();

    expect($waiting->gate['checks']['classifier']['reading']['reasons'])->toBe(['classifier:complaint', 'classifier:price']);

    fakeClassifier()->answer('faq');

    return $waiting;
}

/**
 * Whether a text that reached Dana carries the AC repair sentence.
 */
function answeredDraftAcSentenceSent(): bool
{
    return collect(answeredDraftTextsToDana())->contains(fn (array $text): bool => str_contains($text['body'], ANSWERED_DRAFT_AC_SENTENCE));
}

it('sends the sentence, replaces the draft held on price and a complaint with the fresh reply, and does not close it as answered (D-21)', function () {
    $waiting = answeredDraftPriceAndComplaintHeld();

    answeredDraftCustomerTexts("It's the AC", ['reply' => 'Happy to help with your AC.', 'label' => 'faq_answer', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $waiting->refresh();
    $live = answeredDraftTheDraft();

    expect(answeredDraftAcSentenceSent())->toBeTrue()
        ->and($waiting->resolution)->toBe(DraftResolution::Replaced)
        ->and(Message::query()->where('resolution', DraftResolution::Answered->value)->count())->toBe(0)
        ->and($live->id)->not->toBe($waiting->id);
});

it('sends the sentence and gives a blank reply that touches the draft held on price and a complaint a whole-thread draft (D-21, WR-04)', function () {
    $waiting = answeredDraftPriceAndComplaintHeld();

    answeredDraftCustomerTexts("It's the AC", [
        'reply' => '',
        'label' => 'faq_answer',
        'price_services' => ['ac-repair'],
        'touches_waiting' => true,
    ], ['reply' => 'Sorry about the last visit. Mike will text you the new unit price.', 'label' => 'faq_answer']);

    $waiting->refresh();
    $live = answeredDraftTheDraft();

    expect(answeredDraftAcSentenceSent())->toBeTrue()
        ->and($waiting->resolution)->toBe(DraftResolution::Replaced)
        ->and(Message::query()->where('resolution', DraftResolution::Answered->value)->count())->toBe(0)
        ->and($live->id)->not->toBe($waiting->id)
        ->and($live->body)->toBe('Sorry about the last visit. Mike will text you the new unit price.')
        ->and($live->gate_reason)->toBe('touches_waiting');
});

it('sends the sentence and leaves the draft held on price and a complaint live when the whole-thread call fails (D-21, WR-04)', function () {
    $waiting = answeredDraftPriceAndComplaintHeld();

    answeredDraftCustomerTexts("It's the AC", ['reply' => '', 'label' => 'faq_answer', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $waiting->refresh();

    expect(answeredDraftAcSentenceSent())->toBeTrue()
        ->and($waiting->status)->toBe(MessageStatus::Draft)
        ->and($waiting->resolution)->toBeNull()
        ->and(answeredDraftTheDraft()->id)->toBe($waiting->id)
        ->and(Message::query()->where('resolution', DraftResolution::Answered->value)->count())->toBe(0);
});

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

it('never closes a price draft as answered when the customer also complained, and keeps its complaint alert (CR-02)', function () {
    test()->travelTo(Carbon::parse('2026-10-13 18:00', 'America/Chicago'));
    User::factory()->withPhone(ANSWERED_DRAFT_OWNER_PHONE)->create();

    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);
    frontDeskCustomerTexts('Your tech was rude last time and I am furious. How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);
    runQueuedFrontDeskJobs();

    $waiting = answeredDraftTheDraft();
    $alert = OwnerAlert::query()->where('reason', AlertReason::Complaint->value)->sole();

    expect($waiting->gate['customer_reading']['ran'])->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull();

    fakeClassifier()->answerReading();

    answeredDraftCustomerTexts("It's the AC", ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $waiting->refresh();
    $sentToDana = collect(answeredDraftTextsToDana())->contains(fn (array $text): bool => str_contains($text['body'], ANSWERED_DRAFT_AC_SENTENCE));

    expect($sentToDana)->toBeTrue()
        ->and($waiting->status)->toBe(MessageStatus::Draft)
        ->and($waiting->resolution)->toBeNull()
        ->and(Message::query()->where('resolution', DraftResolution::Answered->value)->count())->toBe(0)
        ->and($alert->refresh()->sent_at)->not->toBeNull()
        ->and($alert->skipped_at)->toBeNull();
});

it('reads the words of a vetoed price draft, finds the promise in them, and never closes the draft when the sentence goes out (CR-02, D-45)', function () {
    fakeClassifier()->answer('faq', 0.95, ['promise' => 0.9]);

    answeredDraftQuestionHeld('How much is a repair?', ['reply' => 'Mike will text you the repair price by 9 am tomorrow.', 'label' => 'price']);
    $waiting = answeredDraftTheDraft();

    expect($waiting->gate_reason)->toBe('label:price')
        ->and($waiting->gate['checks']['classifier']['ran'])->toBeTrue()
        ->and($waiting->gate['checks']['classifier']['reading']['reasons'])->toContain('classifier:promise')
        ->and(fakeClassifier()->calls())->toHaveCount(1)
        ->and(fakeClassifier()->calls()[0]['draft_reply'])->toBe('Mike will text you the repair price by 9 am tomorrow.');

    fakeClassifier()->answer('faq');

    answeredDraftCustomerTexts("It's the AC", ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $live = answeredDraftTheDraft();

    expect(answeredDraftTextsToDana())->toHaveCount(2)
        ->and(answeredDraftTextsToDana()[1]['body'])->toBe(answeredDraftLater(ANSWERED_DRAFT_AC_SENTENCE.' '.ANSWERED_DRAFT_PARTIAL_LINE))
        ->and($live->id)->toBe($waiting->id)
        ->and($live->resolution)->toBeNull()
        ->and(Message::query()->where('resolution', DraftResolution::Answered->value)->count())->toBe(0);
});

it('lets a words check on a vetoed reply add the safety line, and keeps the veto as the reason (D-45)', function () {
    fakeClassifier()->answer('faq', 0.95, ['life_safety' => 0.9]);

    answeredDraftQuestionHeld('How much is a repair?', ['reply' => 'Mike will text you the repair price.', 'label' => 'price']);
    $waiting = answeredDraftTheDraft();

    expect($waiting->gate_reason)->toBe('label:price')
        ->and($waiting->gate['checks']['classifier']['ran'])->toBeTrue()
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->count())->toBe(1)
        ->and(HeldReasons::priceAlone($waiting))->toBeFalse();
});

it('still closes a price draft whose words check and customer reading are both clean when its sentence goes out (D-21, CR-02 control)', function () {
    answeredDraftQuestionHeld('How much is a repair?', ['reply' => 'Mike will text you the repair price.', 'label' => 'price']);
    $waiting = answeredDraftTheDraft();

    expect($waiting->gate['checks']['classifier']['ran'])->toBeTrue()
        ->and($waiting->gate['checks']['classifier']['reading']['reasons'])->toBe([]);

    answeredDraftCustomerTexts("It's the AC", ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $waiting->refresh();

    expect($waiting->resolution)->toBe(DraftResolution::Answered)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0);
});

it('closes a price draft as answered when its run read the complaint at exactly 0.15, and never at 0.16 (ANSR-03 boundary)', function (float $complaint, bool $closes) {
    fakeClassifier()->answerReading('faq', 0.95, ['complaint' => $complaint]);

    answeredDraftQuestionHeld('How much for a new unit?', ['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);
    $waiting = answeredDraftTheDraft();

    expect($waiting->gate_reason)->toBe($closes ? 'label:price' : 'classifier:complaint');

    fakeClassifier()->answerReading();

    answeredDraftCustomerTexts("It's the AC", ['reply' => '', 'price_services' => ['ac-repair'], 'touches_waiting' => true]);

    $waiting->refresh();

    expect($waiting->resolution === DraftResolution::Answered)->toBe($closes)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe($closes ? 0 : 1);
})->with([
    'exactly 0.15' => [0.15, true],
    'at 0.16' => [0.16, false],
]);

/**
 * The newest text that reached the customer, whoever wrote it.
 */
function answeredDraftNewestTextToDana(): Message
{
    return Message::query()
        ->where('direction', MessageDirection::Out->value)
        ->whereIn('status', [MessageStatus::Queued->value, MessageStatus::Sent->value, MessageStatus::Delivered->value])
        ->orderByDesc('id')
        ->firstOrFail();
}

it('answers the third text with the added-info line, not a second holding reply, after the partial line closed the waiting draft (WR-01, D-30)', function () {
    answeredDraftQuestionHeld('How much is a repair?', ['reply' => 'Mike will text you the repair price.', 'label' => 'price']);
    $waiting = answeredDraftTheDraft();

    answeredDraftCustomerTexts("It's the AC. Can you come Thursday?", [
        'reply' => 'We can come Thursday morning.',
        'label' => 'promise',
        'price_services' => ['ac-repair'],
        'touches_waiting' => true,
    ]);

    $partial = Message::query()->where('kind', MessageKind::PartialHolding->value)->sole();
    $waiting->refresh();

    expect($waiting->resolution)->toBe(DraftResolution::Answered)
        ->and($waiting->meta['answered_by'])->toBe($partial->id)
        ->and(answeredDraftTheDraft()->id)->not->toBe($waiting->id)
        ->and(answeredDraftTheDraft()->body)->toBe('We can come Thursday morning.');

    answeredDraftCustomerTexts('Hello?', [
        'reply' => 'Mike has your question and will follow up.',
        'label' => 'other',
        'touches_waiting' => true,
        'waiting_note' => 'added_info',
    ]);

    expect(answeredDraftNewestTextToDana()->kind)->toBe(MessageKind::AddedInfoReply)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1);
});

it('sends a new holding reply for the next held question once the owner approved the draft that waited (D-14, WR-01 control)', function () {
    $owner = answeredDraftQuestionHeld('How much is a repair?', ['reply' => 'Mike will text you the repair price.', 'label' => 'price']);

    app(ResolveDraft::class)->approve(answeredDraftTheDraft(), $owner, 'A repair visit is $89.');

    answeredDraftCustomerTexts('How much for a new unit?', ['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);

    expect(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(2)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(0);
});

it('keeps the wait when a draft was closed as answered with no answered_by, so the next text gets the added-info line, not a second holding reply (Ian, 2026-10-16)', function () {
    answeredDraftQuestionHeld('How much is a repair?', ['reply' => 'Mike will text you the repair price.', 'label' => 'price']);
    $waiting = answeredDraftTheDraft();

    $waiting->update([
        'status' => MessageStatus::Withdrawn,
        'resolution' => DraftResolution::Answered,
        'resolved_at' => now(),
    ]);

    expect(app(SendFixedReply::class)->holdingAlreadySent($waiting->conversation))->toBeTrue();

    answeredDraftCustomerTexts('Hello?', [
        'reply' => 'Mike has your question and will follow up.',
        'label' => 'other',
        'touches_waiting' => true,
        'waiting_note' => 'added_info',
    ]);

    expect(answeredDraftNewestTextToDana()->kind)->toBe(MessageKind::AddedInfoReply)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1);
});

/**
 * The customer reading a verdict keeps under gate.customer_reading (D-42). The defaults are a reading that ran, did not
 * fail and found nothing; a row changes only what it is about.
 *
 * @param  list<string>  $reasons
 * @return array<string, mixed>
 */
function answeredDraftCustomerReading(array $reasons = [], bool $lifeSafety = false, bool $asksForPerson = false, bool $ran = true, bool $failed = false, bool $hasReading = true): array
{
    return [
        'ran' => $ran,
        'failed' => $failed,
        'error' => $failed ? 'timeout' : null,
        'model' => $failed ? null : 'fake-jev',
        'answers' => null,
        'reading' => $hasReading ? [
            'sendable' => $reasons === [] && ! $lifeSafety && ! $asksForPerson,
            'reason_code' => $reasons[0] ?? null,
            'reasons' => $reasons,
            'life_safety' => $lifeSafety,
            'asks_for_person' => $asksForPerson,
        ] : null,
    ];
}

/**
 * A stored verdict as the send rule leaves it: the reason code and, under checks, whatever the row names. The checks a
 * row leaves out are absent, which the read treats as not failed. The customer reading is a clean one unless the row
 * gives another; a row that wants none removes the key itself. A row with model words names them in $agentReply.
 *
 * @param  array<string, mixed>  $checks
 * @param  ?array<string, mixed>  $customerReading
 * @return array<string, mixed>
 */
function answeredDraftGate(string $code, array $checks = [], ?array $customerReading = null, ?string $agentReply = null): array
{
    return [
        'version' => 1,
        'reason' => ['code' => $code, 'plain' => 'Held'],
        ...($agentReply === null ? [] : ['inputs' => ['customer_text' => 'How much?', 'agent_reply' => $agentReply]]),
        'customer_reading' => $customerReading ?? answeredDraftCustomerReading(),
        'checks' => $checks,
    ];
}

/**
 * A verdict stored before the customer reading existed.
 *
 * @param  array<string, mixed>  $gate
 * @return array<string, mixed>
 */
function answeredDraftWithoutCustomerReading(array $gate): array
{
    unset($gate['customer_reading']);

    return $gate;
}

/**
 * Jev's stored reading with the reasons the row gives.
 *
 * @param  list<string>  $reasons
 * @return array<string, mixed>
 */
function answeredDraftReading(array $reasons, bool $lifeSafety = false, bool $asksForPerson = false): array
{
    return [
        'classifier' => [
            'ran' => true,
            'reading' => [
                'sendable' => false,
                'reason_code' => $reasons[0] ?? null,
                'reasons' => $reasons,
                'life_safety' => $lifeSafety,
                'asks_for_person' => $asksForPerson,
            ],
        ],
    ];
}

/**
 * The classifier block of a words check that ran, with the reasons the row gives and nothing else set.
 *
 * @param  ?list<string>  $reasons  Null for a words check that ran and left no reading.
 * @return array<string, array<string, mixed>>
 */
function answeredDraftWordsChecked(?array $reasons, bool $lifeSafety = false, bool $asksForPerson = false): array
{
    return ['classifier' => [
        'ran' => true,
        'error' => null,
        'reading' => $reasons === null ? null : [
            'sendable' => $reasons === [],
            'reason_code' => $reasons[0] ?? null,
            'reasons' => $reasons,
            'life_safety' => $lifeSafety,
            'asks_for_person' => $asksForPerson,
        ],
    ]];
}

/**
 * A live draft with the given verdict, in a conversation of its own.
 *
 * @param  ?array<string, mixed>  $gate
 * @param  array<string, mixed>  $meta
 */
function answeredDraftWithGate(?array $gate, array $meta = []): Message
{
    return Message::factory()->draft()->create([
        'gate' => $gate,
        'gate_reason' => $gate['reason']['code'] ?? null,
        'meta' => $meta,
    ]);
}

/**
 * A draft held the way the kind says, with the services its run looked up and the ones left without a sentence. A null
 * list leaves the key out of the meta, as on a draft stored before the keys existed. A draft that replaced others lists
 * them in meta.replaces.
 *
 * @param  ?list<string>  $slugs
 * @param  ?list<string>  $open
 * @param  list<int>  $replaces
 */
function answeredDraftHeldAs(string $kind, ?array $slugs, ?array $open, array $replaces = []): Message
{
    $gate = match ($kind) {
        'complaint' => answeredDraftGate('label:complaint', ['label' => ['passed' => false, 'label' => 'complaint']]),
        'price and complaint' => answeredDraftGate('classifier:price', [
            'label' => ['passed' => true, 'label' => 'faq_answer'],
            ...answeredDraftReading(['classifier:price', 'classifier:complaint']),
        ]),
        default => answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']]),
    };

    $meta = [
        ...($slugs === null ? [] : ['price_slugs' => $slugs]),
        ...($open === null ? [] : ['price_open' => $open]),
        ...($replaces === [] ? [] : ['replaces' => $replaces]),
    ];

    return answeredDraftWithGate($gate, $meta);
}

it('reads a draft as held on price alone only when every recorded check agrees (D-21)', function (?array $gate, bool $expected) {
    expect(HeldReasons::priceAlone(answeredDraftWithGate($gate)))->toBe($expected);
})->with([
    'label check failed with label price' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']]), true],
    'one code finding, the price rule' => [answeredDraftGate('code:price', [
        'label' => ['passed' => true, 'label' => 'faq_answer'],
        'code' => ['passed' => false, 'findings' => [['rule' => 'price', 'match' => '$89']]],
    ]), true],
    'Jev read classifier:price' => [answeredDraftGate('classifier:price', answeredDraftReading(['classifier:price'])), true],
    'Jev read the price question intent' => [answeredDraftGate('classifier_intent:price_question', answeredDraftReading(['classifier_intent:price_question'])), true],
    'no sentence on file, checks never ran' => [answeredDraftGate('price:no_sentence', [
        'label' => ['passed' => null, 'label' => 'faq_answer'],
        'length' => ['passed' => null],
        'code' => ['passed' => null, 'findings' => []],
        'classifier' => ['ran' => false, 'reading' => null],
    ]), true],
    'no sentence, checks never ran, the label was a promise (WR-03)' => [answeredDraftGate('price:no_sentence', [
        'label' => ['passed' => null, 'label' => 'promise'],
        'length' => ['passed' => null],
        'code' => ['passed' => null, 'findings' => []],
        'classifier' => ['ran' => false, 'reading' => null],
    ], agentReply: ''), false],
    'no sentence, checks never ran, the label was a discount (WR-03)' => [answeredDraftGate('price:no_sentence', [
        'label' => ['passed' => null, 'label' => 'discount'],
        'length' => ['passed' => null],
        'code' => ['passed' => null, 'findings' => []],
        'classifier' => ['ran' => false, 'reading' => null],
    ], agentReply: ''), false],
    'the label check passed with a promise label (WR-03)' => [answeredDraftGate('label:price', ['label' => ['passed' => true, 'label' => 'promise']]), false],
    'Jev read price and a complaint' => [answeredDraftGate('classifier:price', answeredDraftReading(['classifier:price', 'classifier:complaint'])), false],
    'code findings price and promise' => [answeredDraftGate('code:price', [
        'code' => ['passed' => false, 'findings' => [['rule' => 'price', 'match' => '$89'], ['rule' => 'promise', 'match' => 'guarantee']]],
    ]), false],
    'the label was a complaint' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'complaint']]), false],
    'held because it touches the waiting draft' => [answeredDraftGate('touches_waiting', ['label' => ['passed' => true, 'label' => 'price']]), false],
    'the sender refused it' => [answeredDraftGate('send_refused'), false],
    'Jev did not answer' => [answeredDraftGate('classifier_failed', ['classifier' => ['ran' => true, 'error' => 'timeout', 'reading' => null]]), false],
    'too long for one text' => [answeredDraftGate('too_long', ['length' => ['passed' => false, 'characters' => 1700, 'limit' => 1600]]), false],
    'Jev unsure of the intent beside price' => [answeredDraftGate('classifier:price', answeredDraftReading(['classifier:price', 'classifier_unsure:intent'])), false],
    'Jev read a danger beside price' => [answeredDraftGate('classifier:price', answeredDraftReading(['classifier:price'], lifeSafety: true)), false],
    'Jev read a request for a person beside price' => [answeredDraftGate('classifier:price', answeredDraftReading(['classifier:price'], asksForPerson: true)), false],
    'no verdict at all' => [null, false],
    'no customer reading stored, a draft from before this gap run' => [answeredDraftWithoutCustomerReading(answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']])), false],
    'the customer reading failed' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']], answeredDraftCustomerReading(ran: true, failed: true, hasReading: false)), false],
    'the customer reading did not run' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']], answeredDraftCustomerReading(ran: false, hasReading: false)), false],
    'the customer reading ran with no answers' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']], answeredDraftCustomerReading(hasReading: false)), false],
    'the customer reading found a complaint' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']], answeredDraftCustomerReading(['classifier:complaint'])), false],
    'the customer reading found the complaint intent' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']], answeredDraftCustomerReading(['classifier_intent:complaint'])), false],
    'the customer reading found a danger' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']], answeredDraftCustomerReading(lifeSafety: true)), false],
    'the customer reading found a request for a person' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']], answeredDraftCustomerReading(asksForPerson: true)), false],
    'the customer reading was unsure of a request for a person' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']], answeredDraftCustomerReading(['classifier_unsure:asks_for_person'])), false],
    'the customer reading found price and discount, which never act (D-42)' => [answeredDraftGate('label:price', ['label' => ['passed' => false, 'label' => 'price']], answeredDraftCustomerReading(['classifier:price', 'classifier:discount'])), true],
    'model words and the words check never ran (the CR-02 shape)' => [answeredDraftGate('label:price', [
        'label' => ['passed' => false, 'label' => 'price'],
        'classifier' => ['ran' => false, 'error' => null, 'reading' => null],
    ], agentReply: 'Mike will text you the price.'), false],
    'model words and the words check found nothing' => [answeredDraftGate('label:price', [
        'label' => ['passed' => false, 'label' => 'price'],
        ...answeredDraftWordsChecked([]),
    ], agentReply: 'Mike will text you the price.'), true],
    'model words and the words check found only price' => [answeredDraftGate('label:price', [
        'label' => ['passed' => false, 'label' => 'price'],
        ...answeredDraftWordsChecked(['classifier:price']),
    ], agentReply: 'Mike will text you the price.'), true],
    'model words and the words check left no reading' => [answeredDraftGate('label:price', [
        'label' => ['passed' => false, 'label' => 'price'],
        ...answeredDraftWordsChecked(null),
    ], agentReply: 'Mike will text you the price.'), false],
    'model words and the words check found a promise' => [answeredDraftGate('label:price', [
        'label' => ['passed' => false, 'label' => 'price'],
        ...answeredDraftWordsChecked(['classifier:promise']),
    ], agentReply: 'Mike will text you the price by 9 am.'), false],
    'model words and the words check found a danger' => [answeredDraftGate('label:price', [
        'label' => ['passed' => false, 'label' => 'price'],
        ...answeredDraftWordsChecked([], lifeSafety: true),
    ], agentReply: 'Mike will text you the price.'), false],
    'blank model words, the words check not run, a clean customer reading' => [answeredDraftGate('price:no_sentence', [
        'label' => ['passed' => null, 'label' => 'faq_answer'],
        'length' => ['passed' => null],
        'code' => ['passed' => null, 'findings' => []],
        'classifier' => ['ran' => false, 'reading' => null],
    ], agentReply: ''), true],
]);

it('closes a draft only for a sentence about the service it still waits on (D-21)', function (string $heldAs, ?array $slugs, ?array $open, ?array $replacedSpec, array $sent, ?bool $touches, bool $expected) {
    $replaces = $replacedSpec === null ? [] : [answeredDraftHeldAs(...$replacedSpec)->id];

    $draft = answeredDraftHeldAs($heldAs, $slugs, $open, $replaces);

    expect(HeldReasons::answeredBy($draft, $sent, $touches))->toBe($expected);
})->with([
    'its own service, touches true' => ['price', ['system-replacement'], ['system-replacement'], null, ['system-replacement'], true, true],
    'its own service, touches false' => ['price', ['system-replacement'], ['system-replacement'], null, ['system-replacement'], false, true],
    'its own service, touches missing' => ['price', ['system-replacement'], ['system-replacement'], null, ['system-replacement'], null, true],
    'another service, touches true' => ['price', ['system-replacement'], ['system-replacement'], null, ['ac-repair'], true, false],
    'no sentence sent' => ['price', ['system-replacement'], ['system-replacement'], null, [], true, false],
    'two services open, one sentence' => ['price', ['ac-repair', 'furnace-repair'], ['ac-repair', 'furnace-repair'], null, ['ac-repair'], true, false],
    'two services open, both sentences' => ['price', ['ac-repair', 'furnace-repair'], ['ac-repair', 'furnace-repair'], null, ['furnace-repair', 'ac-repair'], true, true],
    'its own run already sent every sentence' => ['price', ['ac-repair'], [], null, ['ac-repair'], true, false],
    'no service named, touches true' => ['price', [], [], null, ['ac-repair'], true, true],
    'no service named, touches false' => ['price', [], [], null, ['ac-repair'], false, false],
    'no service named, touches missing' => ['price', [], [], null, ['ac-repair'], null, false],
    'no price keys in the meta, touches true' => ['price', null, null, null, ['ac-repair'], true, true],
    'no price keys in the meta, touches missing' => ['price', null, null, null, ['ac-repair'], null, false],
    'held on price and a complaint' => ['price and complaint', ['ac-repair'], ['ac-repair'], null, ['ac-repair'], true, false],
    'a complaint draft' => ['complaint', ['ac-repair'], ['ac-repair'], null, ['ac-repair'], true, false],
    'chain: another service open in the replaced draft, one sentence' => ['price', ['ac-repair'], ['ac-repair'], ['price', ['system-replacement'], ['system-replacement']], ['ac-repair'], true, false],
    'chain: both services get a sentence' => ['price', ['ac-repair'], ['ac-repair'], ['price', ['system-replacement'], ['system-replacement']], ['ac-repair', 'system-replacement'], true, true],
    'chain: the replaced draft was a complaint' => ['price', ['ac-repair'], ['ac-repair'], ['complaint', ['ac-repair'], ['ac-repair']], ['ac-repair'], true, false],
    'chain: the replaced draft named no service, touches missing' => ['price', ['ac-repair'], ['ac-repair'], ['price', [], []], ['ac-repair'], null, false],
    'chain: the replaced draft named no service, touches true' => ['price', ['ac-repair'], ['ac-repair'], ['price', [], []], ['ac-repair'], true, true],
]);
