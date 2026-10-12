<?php

use App\Actions\ClaimInboundBurst;
use App\Actions\RecordIntakeAnswers;
use App\Ai\Agents\FrontDeskAgent;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Jobs\ProcessInbound;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('tells the owner, and texts the customer nothing, when every model failed (AGNT-04)', function () {
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();
    FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();

    expect(fn () => runQueuedFrontDeskJobs())->not->toThrow(Throwable::class);

    $alert = OwnerAlert::query()->first();
    $sent = textsSentToCustomers();

    expect(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(Message::query()->where('direction', MessageDirection::In)->sole()->handled_at)->not->toBeNull()
        ->and(Contact::sole()->activities()->where('type', ActivityType::Note)->sole()->summary)->toBe('Front desk could not answer: every model failed')
        ->and($alert?->reason)->toBe(AlertReason::FrontDeskFailed)
        ->and($alert?->reason->value)->toBe('front_desk_failed')
        ->and($alert?->sent_at)->not->toBeNull()
        ->and($sent)->toHaveCount(1)
        ->and($sent[0]['to'])->toBe('+12025550199')
        ->and($sent[0]['body'])->toContain('Front desk could not answer');
});

it('keeps a reply the channel would not take as failed, and tells the owner why', function () {
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    config(['channels.text.driver' => 'twilio']);

    expect(fn () => runQueuedFrontDeskJobs())->not->toThrow(Throwable::class);

    $failed = Message::query()->where('direction', MessageDirection::Out)->first();
    $alert = OwnerAlert::query()->first();

    expect(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(1)
        ->and($failed->author)->toBe(MessageAuthor::Agent)
        ->and($failed->status)->toBe(MessageStatus::Failed)
        ->and($failed->meta['error'])->not->toBe('')
        ->and($failed->gate_outcome)->toBe(GateOutcome::Send)
        ->and(Contact::sole()->activities()->where('type', ActivityType::Note)->where('summary', 'like', 'Reply not sent:%')->count())->toBe(1)
        ->and($alert?->reason)->toBe(AlertReason::FrontDeskFailed)
        ->and($alert?->body)->toContain('The reply could not be sent');
});

it('keeps the reply as a draft and sends nothing when the owner is sending to the same customer', function () {
    config(['gate.agent.lock_wait_seconds' => 0]);
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    $lock = Cache::lock('reply:'.Conversation::sole()->id, 30);
    $lock->get();

    try {
        runQueuedFrontDeskJobs();
    } finally {
        $lock->release();
    }

    $draft = theAgentMessage();

    expect(textsSentToCustomers())->toBe([])
        ->and($draft->status)->toBe(MessageStatus::Draft)
        ->and($draft->body)->toBe('What is the system doing?')
        ->and($draft->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($draft->gate_reason)->toBe('send_refused')
        ->and($draft->gate['reason']['plain'])->toBe('Held: could not send right now');
});

it('tells the owner and writes a note when something unexpected breaks the run, and texts the customer nothing (CR-01)', function () {
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    $this->mock(RecordIntakeAnswers::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('database went away: secret-detail-123')));

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();
    $sent = textsSentToCustomers();

    expect(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0)
        ->and(Message::query()->where('direction', MessageDirection::In)->sole()->handled_at)->not->toBeNull()
        ->and(Contact::sole()->activities()->where('type', ActivityType::Note)->sole()->summary)->toBe('Front desk could not answer: it stopped with an error')
        ->and($alert->reason)->toBe(AlertReason::FrontDeskFailed)
        ->and($alert->body)->toContain('Front desk stopped with an error')
        ->and($alert->body)->not->toContain('database went away')
        ->and($alert->body)->not->toContain('secret-detail-123')
        ->and($alert->body)->not->toContain('RuntimeException')
        ->and($sent)->toHaveCount(1)
        ->and($sent[0]['to'])->toBe('+12025550199');
});

/**
 * A job for Dana's conversation, as the queue would build it after her text.
 */
function frontDeskJobForTheOnlyConversation(): ProcessInbound
{
    return new ProcessInbound(Message::query()->where('direction', MessageDirection::In)->sole()->id, Conversation::sole()->id);
}

it('tells the owner when the worker stops the run before it finishes', function () {
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();
    app(ClaimInboundBurst::class)->handle(Conversation::sole()->id);

    frontDeskJobForTheOnlyConversation()->failed(new RuntimeException('timed out: secret-detail-456'));

    $alert = OwnerAlert::query()->sole();

    expect(Contact::sole()->activities()->where('type', ActivityType::Note)->sole()->summary)->toBe('Front desk could not answer: it stopped before finishing')
        ->and($alert->reason)->toBe(AlertReason::FrontDeskFailed)
        ->and($alert->body)->toContain('Front desk stopped before answering')
        ->and($alert->body)->not->toContain('secret-detail-456')
        ->and(Message::query()->where('direction', MessageDirection::In)->sole()->handled_at)->not->toBeNull()
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0)
        ->and(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['to'])->toBe('+12025550199');
});

it('reports a crash once even when failed() runs after the job already reported it', function () {
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    $this->mock(RecordIntakeAnswers::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('database went away')));

    runQueuedFrontDeskJobs();
    frontDeskJobForTheOnlyConversation()->failed(new RuntimeException('timed out'));

    expect(OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->count())->toBe(1)
        ->and(Contact::sole()->activities()->where('type', ActivityType::Note)->count())->toBe(1)
        ->and(textsSentToCustomers())->toHaveCount(1);
});

it('does nothing in failed() for a conversation that no longer exists', function () {
    User::factory()->withPhone('+12025550199')->create();

    (new ProcessInbound(1, 999999))->failed(new RuntimeException('timed out'));

    expect(OwnerAlert::query()->count())->toBe(0)
        ->and(textsSentToCustomers())->toBe([]);
});

it('gives the job more time than the slowest front desk run, and the queue more time than the job', function () {
    $job = new ProcessInbound(1, 1);

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(300)
        ->and($job->failOnTimeout)->toBeTrue()
        ->and($job->timeout)->toBeGreaterThan(config('gate.agent.chain_budget_seconds') + config('services.jev.timeout') + 120)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(300)
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan(300);
});
