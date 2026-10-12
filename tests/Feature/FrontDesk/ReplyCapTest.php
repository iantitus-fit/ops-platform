<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Enums\AlertReason;
use App\Enums\MessageKind;
use App\Enums\PausedReason;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;

const REPLY_CAP_OWNER_PHONE = '+12025550199';

const REPLY_CAP_CUSTOMER_PHONE = '+12025550123';

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner reachable, and Dana's text arrived but not yet answered.
 */
function replyCapDanaTexts(): Conversation
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(REPLY_CAP_OWNER_PHONE)->create();

    frontDeskCustomerTexts('Are you still coming?');

    return Conversation::query()->sole();
}

/**
 * Earlier texts from the front desk, each the given number of minutes before now.
 *
 * @param  list<int>  $minutesAgo
 */
function replyCapAgentTextsSent(Conversation $conversation, array $minutesAgo, ?MessageKind $kind = MessageKind::AgentReply): void
{
    foreach ($minutesAgo as $minutes) {
        Message::factory()->for($conversation)->outbound()->create([
            'kind' => $kind,
            'body' => 'Earlier automated text',
            'created_at' => now()->subMinutes($minutes),
        ]);
    }
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function replyCapTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === REPLY_CAP_CUSTOMER_PHONE,
    ));
}

/**
 * The cap tripped: no agent run, no new text, paused with reply_cap, and the owner alerted.
 */
function replyCapAssertStopped(Conversation $conversation, int $textsSoFar): void
{
    $alert = OwnerAlert::query()->sole();

    FrontDeskAgent::assertNeverPrompted();

    expect(replyCapTextsToDana())->toBe([])
        ->and(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'out')->count())->toBe($textsSoFar)
        ->and($conversation->fresh()->isPaused())->toBeTrue()
        ->and($conversation->fresh()->paused_reason)->toBe(PausedReason::ReplyCap)
        ->and($alert->reason)->toBe(AlertReason::ReplyCap)
        ->and($alert->body)->toContain('Reply limit reached');
}

it('stops at six agent texts in the last hour: no agent run, paused, owner alerted (D-52)', function () {
    $conversation = replyCapDanaTexts();
    replyCapAgentTextsSent($conversation, [5, 10, 20, 30, 40, 55]);
    scriptFrontDeskReplies(['reply' => 'Yes, he is on the way.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    replyCapAssertStopped($conversation, 6);
});

it('still answers with five agent texts in the last hour (D-52)', function () {
    $conversation = replyCapDanaTexts();
    replyCapAgentTextsSent($conversation, [5, 10, 20, 30, 40]);
    scriptFrontDeskReplies(['reply' => 'Yes, he is on the way.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    expect(replyCapTextsToDana())->toHaveCount(1)
        ->and(replyCapTextsToDana()[0]['body'])->toContain('Yes, he is on the way.')
        ->and($conversation->fresh()->isPaused())->toBeFalse()
        ->and(OwnerAlert::query()->exists())->toBeFalse();
});

it('stops at twenty agent texts in 24 hours even when no hour is full (D-52)', function () {
    $conversation = replyCapDanaTexts();
    replyCapAgentTextsSent($conversation, array_map(fn (int $i): int => 90 + $i * 60, range(0, 19)));
    scriptFrontDeskReplies(['reply' => 'Yes, he is on the way.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    replyCapAssertStopped($conversation, 20);
});

it('still answers with nineteen agent texts in 24 hours', function () {
    $conversation = replyCapDanaTexts();
    replyCapAgentTextsSent($conversation, array_map(fn (int $i): int => 90 + $i * 60, range(0, 18)));
    scriptFrontDeskReplies(['reply' => 'Yes, he is on the way.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    expect(replyCapTextsToDana())->toHaveCount(1)
        ->and($conversation->fresh()->isPaused())->toBeFalse();
});

it('does not count texts older than 24 hours, drafts, failed sends or the safety line (D-52)', function () {
    $conversation = replyCapDanaTexts();
    replyCapAgentTextsSent($conversation, [1500, 1600, 1700, 1800, 1900, 2000, 2100, 2200]);
    replyCapAgentTextsSent($conversation, [1, 2, 3, 4, 5, 6, 7], MessageKind::LifeSafety);
    Message::factory()->for($conversation)->draft()->create(['created_at' => now()->subMinutes(3)]);
    Message::factory()->for($conversation)->outbound()->create(['status' => 'failed', 'created_at' => now()->subMinutes(4)]);
    scriptFrontDeskReplies(['reply' => 'Yes, he is on the way.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    expect(replyCapTextsToDana())->toHaveCount(1)
        ->and($conversation->fresh()->isPaused())->toBeFalse();
});
