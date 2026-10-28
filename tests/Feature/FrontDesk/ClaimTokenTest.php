<?php

use App\Actions\ClaimInboundBurst;
use App\Enums\MessageDirection;
use App\Jobs\ProcessInbound;
use App\Models\Conversation;
use App\Models\Message;
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
