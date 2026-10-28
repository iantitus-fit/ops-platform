<?php

use App\Actions\ClaimInboundBurst;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Jobs\ProcessInbound;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The claim token ties a failed front-desk run to the texts that run claimed (06 D-12, ALRT-02, WR-22).
 *
 * These tests run ProcessInbound through Queue::fake, which calls handle() on the very object that was pushed, so a
 * token chosen inside handle() would pass here and fail in production, where failed() runs on a copy the queue
 * rebuilds from the stored payload. The serialize cases below are what prove the token reaches that copy (D-12 (4),
 * Pitfall 4).
 */
it('gives each job a claim token when it is built, and keeps one it is given', function () {
    $first = new ProcessInbound(1, 1);
    $second = new ProcessInbound(1, 1);
    $given = new ProcessInbound(1, 1, 'a-known-token');

    expect($first->claimToken)->not->toBe('')
        ->and($second->claimToken)->not->toBe('')
        ->and($first->claimToken)->not->toBe($second->claimToken)
        ->and($given->claimToken)->toBe('a-known-token');
});

it('keeps the claim token in the serialized form of the job, the way the queue stores it', function () {
    $job = new ProcessInbound(7, 9);

    $rebuilt = unserialize(serialize($job));

    expect($rebuilt)->toBeInstanceOf(ProcessInbound::class)
        ->and($rebuilt->claimToken)->toBe($job->claimToken)
        ->and($rebuilt->messageId)->toBe(7)
        ->and($rebuilt->conversationId)->toBe(9);
});

it('writes the first queued job\'s token on both of Dana\'s texts when she texts twice before the run', function () {
    frontDeskCustomerTexts('My AC is blowing warm air');
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('d', 32), 'Body' => 'It is also leaking water']))->assertOk();
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that. What is the system doing?', 'label' => 'intake_question']);

    $firstJob = Queue::pushed(ProcessInbound::class)->first();

    runQueuedFrontDeskJobs();

    $texts = Message::query()->where('direction', MessageDirection::In)->orderBy('id')->get();

    expect($texts)->toHaveCount(2)
        ->and($firstJob)->toBeInstanceOf(ProcessInbound::class)
        ->and($texts->pluck('claim_token')->unique()->all())->toBe([$firstJob->claimToken])
        ->and($texts->every(fn (Message $text): bool => $text->handled_at !== null))->toBeTrue();
});

it('leaves a text an earlier run claimed with that run\'s token when a later run claims the new texts', function () {
    $conversation = Conversation::factory()->create();
    $earlier = Message::factory()->for($conversation)->create(['handled_at' => now()->subHour(), 'claim_token' => 'earlier-run']);
    $later = Message::factory()->for($conversation)->create();

    app(ClaimInboundBurst::class)->handle($conversation->id, 'later-run');

    expect($earlier->fresh()->claim_token)->toBe('earlier-run')
        ->and($later->fresh()->claim_token)->toBe('later-run');
});

it('writes one fresh token on every row it claims when called without a token, and the given token when called with one', function () {
    $conversation = Conversation::factory()->create();
    $untokened = Message::factory()->for($conversation)->count(2)->create();

    $claimedWithoutToken = app(ClaimInboundBurst::class)->handle($conversation->id);

    $tokens = $claimedWithoutToken->pluck('claim_token')->unique();

    expect($claimedWithoutToken->pluck('id')->all())->toBe($untokened->pluck('id')->all())
        ->and($tokens)->toHaveCount(1)
        ->and($tokens->first())->not->toBeNull()
        ->and($tokens->first())->not->toBe('');

    $token = (string) Str::uuid();
    $other = Message::factory()->for($conversation)->create();

    app(ClaimInboundBurst::class)->handle($conversation->id, $token);

    expect($other->fresh()->claim_token)->toBe($token);
});

it('stores a STOP text handled on arrival with no claim token', function () {
    frontDeskCustomerTexts('STOP');

    $stop = Message::query()->where('direction', MessageDirection::In)->sole();

    expect($stop->handled_at)->not->toBeNull()
        ->and(Message::query()->whereKey($stop->id)->whereNull('claim_token')->exists())->toBeTrue();
});

/**
 * Dana's text, claimed by the job the queue captured for it, and a later text of hers that no run has claimed.
 *
 * @return array{ProcessInbound, Message, Message} the captured job, her claimed text and her later text
 */
function claimTokenDanaClaimedThenTextsAgain(): array
{
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();

    $job = Queue::pushed(ProcessInbound::class)->first();
    $claimed = app(ClaimInboundBurst::class)->handle($job->conversationId, $job->claimToken)->sole();

    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('e', 32), 'Body' => 'Hello? Any news?']))->assertOk();
    $later = Message::query()->where('direction', MessageDirection::In)->latest('id')->first();

    return [$job, $claimed, $later];
}

it('reports her claimed text, not her later one, when failed() runs on a copy rebuilt from the serialized job (D-12 (4))', function () {
    [$job, $claimed, $later] = claimTokenDanaClaimedThenTextsAgain();

    unserialize(serialize($job))->failed(new RuntimeException('timed out: secret-detail-789'));

    $alert = OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->sole();

    expect($later->id)->not->toBe($claimed->id)
        ->and($later->claim_token)->toBeNull()
        ->and($alert->message_id)->toBe($claimed->id)
        ->and($alert->body)->toContain('Front desk stopped before answering')
        ->and($alert->body)->not->toContain('secret-detail-789')
        ->and(Contact::sole()->activities()->where('type', ActivityType::Note)->sole()->summary)->toBe('Front desk could not answer: it stopped before finishing');
});

it('does not blame an earlier run\'s text for the later text\'s job, whose token claimed nothing (WR-22)', function () {
    [$earlierJob, $claimed, $later] = claimTokenDanaClaimedThenTextsAgain();
    $laterJob = Queue::pushed(ProcessInbound::class)->last();

    unserialize(serialize($laterJob))->failed(new RuntimeException('timed out'));

    expect($laterJob->messageId)->toBe($later->id)
        ->and($laterJob->claimToken)->not->toBe($earlierJob->claimToken)
        ->and($claimed->claim_token)->toBe($earlierJob->claimToken)
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('makes the same report on the job object that was never serialized, the shape the sync queue gives', function () {
    [$job, $claimed] = claimTokenDanaClaimedThenTextsAgain();

    $job->failed(new RuntimeException('timed out: secret-detail-789'));

    $alert = OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->sole();

    expect($alert->message_id)->toBe($claimed->id)
        ->and($alert->body)->toContain('Front desk stopped before answering')
        ->and($alert->body)->not->toContain('secret-detail-789')
        ->and(Contact::sole()->activities()->where('type', ActivityType::Note)->sole()->summary)->toBe('Front desk could not answer: it stopped before finishing');
});

it('reports only its own run\'s text when two runs claimed different texts of one conversation (D-12 (3))', function () {
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $firstText = Message::factory()->for($conversation)->claimedBy('first-run')->create();
    $secondText = Message::factory()->for($conversation)->claimedBy('second-run')->create();
    $firstJob = new ProcessInbound($firstText->id, $conversation->id, 'first-run');
    $secondJob = new ProcessInbound($secondText->id, $conversation->id, 'second-run');

    unserialize(serialize($firstJob))->failed(new RuntimeException('timed out'));

    expect(OwnerAlert::query()->pluck('message_id')->all())->toBe([$firstText->id]);

    unserialize(serialize($secondJob))->failed(new RuntimeException('timed out'));

    expect(OwnerAlert::query()->orderBy('id')->pluck('message_id')->all())->toBe([$firstText->id, $secondText->id]);
});

it('never reports a STOP text that was handled on arrival after the run claimed her first text (WR-22)', function () {
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();
    $job = Queue::pushed(ProcessInbound::class)->first();
    $claimed = app(ClaimInboundBurst::class)->handle($job->conversationId, $job->claimToken)->sole();

    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('f', 32), 'Body' => 'STOP']))->assertOk();
    $stop = Message::query()->where('direction', MessageDirection::In)->latest('id')->first();

    unserialize(serialize($job))->failed(new RuntimeException('timed out'));

    expect($stop->id)->toBeGreaterThan($claimed->id)
        ->and($stop->handled_at)->not->toBeNull()
        ->and($stop->claim_token)->toBeNull()
        ->and(OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->sole()->message_id)->toBe($claimed->id);
});

it('reports a web form lead claimed on its own, tied to the form (D-20)', function () {
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $form = Message::factory()->web()->for($conversation)->claimedBy('form-run')->create();

    unserialize(serialize(new ProcessInbound($form->id, $conversation->id, 'form-run')))->failed(new RuntimeException('timed out'));

    $alert = OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->sole();

    expect($form->channel)->toBe(MessageChannel::Web)
        ->and($alert->message_id)->toBe($form->id)
        ->and($alert->body)->toContain('Front desk stopped before answering')
        ->and($conversation->contact->activities()->where('type', ActivityType::Note)->sole()->summary)->toBe('Front desk could not answer: it stopped before finishing');
});

it('reports the text, not the newer web form, when a run claimed both (D-20)', function () {
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $text = Message::factory()->for($conversation)->claimedBy('mixed-run')->create();
    $form = Message::factory()->web()->for($conversation)->claimedBy('mixed-run')->create();

    unserialize(serialize(new ProcessInbound($form->id, $conversation->id, 'mixed-run')))->failed(new RuntimeException('timed out'));

    expect(OwnerAlert::query()->sole()->message_id)->toBe($text->id);
});

it('reports nothing when the job\'s token claimed nothing (D-13)', function () {
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $someoneElses = Message::factory()->for($conversation)->claimedBy('another-run')->create();

    unserialize(serialize(new ProcessInbound($someoneElses->id, $conversation->id, 'a-run-that-claimed-nothing')))->failed(new RuntimeException('timed out'));

    expect(OwnerAlert::query()->count())->toBe(0);
});
