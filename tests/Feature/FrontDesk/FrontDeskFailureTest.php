<?php

use App\Actions\ClaimInboundBurst;
use App\Actions\RecordIntakeAnswers;
use App\Actions\ReportFrontDeskFailure;
use App\Ai\Agents\FrontDeskAgent;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Jobs\ProcessInbound;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

it('tells the owner, and texts the customer the holding reply, when every model failed (AGNT-04, D-51)', function () {
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts();
    FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();

    expect(fn () => runQueuedFrontDeskJobs())->not->toThrow(Throwable::class);

    $alert = OwnerAlert::query()->first();
    $sent = textsSentToCustomers();

    expect(Message::query()->where('direction', MessageDirection::Out)->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(Message::query()->where('direction', MessageDirection::Out)->sole()->kind->value)->toBe('holding_reply')
        ->and(OwnerAlert::query()->count())->toBe(1)
        ->and(Message::query()->where('direction', MessageDirection::In)->sole()->handled_at)->not->toBeNull()
        ->and(Contact::sole()->activities()->where('type', ActivityType::Note)->sole()->summary)->toBe('Front desk could not answer: every model failed')
        ->and($alert?->reason)->toBe(AlertReason::FrontDeskFailed)
        ->and($alert?->reason->value)->toBe('front_desk_failed')
        ->and($alert?->sent_at)->not->toBeNull()
        ->and($sent)->toHaveCount(2)
        ->and(array_column($sent, 'to'))->toContain('+12025550199', '+12025550123')
        ->and(frontDeskFailureOwnerAlertText())->toContain('Front desk could not answer');
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

it('reports the claimed text, not a newer one that has its own queued job (WR-03)', function () {
    $this->freezeTime();
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $earlier = Message::factory()->for($conversation)->create(['handled_at' => now()->subHour()]);
    $claimed = Message::factory()->for($conversation)->create(['handled_at' => now()]);
    $newer = Message::factory()->for($conversation)->create(['handled_at' => null]);

    (new ProcessInbound($claimed->id, $conversation->id))->failed(new RuntimeException('timed out'));

    $alert = OwnerAlert::query()->sole();

    expect($alert->message_id)->toBe($claimed->id)
        ->and($alert->message_id)->not->toBe($newer->id)
        ->and($alert->message_id)->not->toBe($earlier->id);
});

it('does not tell the owner twice when the run reported its text and then timed out with a newer text waiting (WR-03)', function () {
    $this->freezeTime();
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $earlier = Message::factory()->for($conversation)->create(['handled_at' => now()->subHour()]);
    $claimed = Message::factory()->for($conversation)->create(['handled_at' => now()]);
    Message::factory()->for($conversation)->create(['handled_at' => null]);

    app(ReportFrontDeskFailure::class)->handle($conversation, $claimed, 'it stopped with an error', 'Front desk stopped with an error');
    (new ProcessInbound($claimed->id, $conversation->id))->failed(new RuntimeException('timed out'));

    expect(OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->count())->toBe(1);
});

it('reports the customer text, not a web form that was claimed with it (WR-03)', function () {
    $this->freezeTime();
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $earlier = Message::factory()->for($conversation)->create(['handled_at' => now()->subHour()]);
    $text = Message::factory()->for($conversation)->create(['handled_at' => now()]);
    $form = Message::factory()->for($conversation)->create(['channel' => MessageChannel::Web, 'handled_at' => now()]);

    (new ProcessInbound($form->id, $conversation->id))->failed(new RuntimeException('timed out'));

    expect(OwnerAlert::query()->sole()->message_id)->toBe($text->id);
});

it('reports nothing in failed() when this job claimed no text from the customer (WR-03)', function () {
    User::factory()->withPhone('+12025550199')->create();
    $conversation = Conversation::factory()->create();
    $unclaimed = Message::factory()->for($conversation)->create(['handled_at' => null]);

    (new ProcessInbound($unclaimed->id, $conversation->id))->failed(new RuntimeException('timed out'));

    expect(OwnerAlert::query()->count())->toBe(0);
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

it('tells the owner, and texts the customer the holding reply, when the live folder has no agent for texts (WR-01, D-51)', function () {
    User::factory()->withPhone('+12025550199')->create();
    File::delete(icmPath('agents/front-desk.md'));
    frontDeskCustomerTexts();

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();
    $sent = textsSentToCustomers();

    expect(Message::query()->where('direction', MessageDirection::Out)->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(Message::query()->where('direction', MessageDirection::In)->sole()->handled_at)->not->toBeNull()
        ->and(Contact::sole()->activities()->where('type', ActivityType::Note)->sole()->summary)->toBe('Front desk could not answer: no agent file answers texts')
        ->and($alert->reason)->toBe(AlertReason::FrontDeskFailed)
        ->and($alert->body)->toContain('No front desk agent is set up for texts')
        ->and($alert->sent_at)->not->toBeNull()
        ->and($sent)->toHaveCount(2)
        ->and(frontDeskFailureTextsToDana())->toHaveCount(1);
});

it('tells the owner, at once, and texts the customer the holding reply, when a Phase 1 version with no agent is still live (D-51)', function () {
    User::factory()->withPhone('+12025550199')->create();

    $version = activateExampleFolder();
    $compiled = $version->compiled;
    unset($compiled['index']['agent']);
    $compiled['files']['rules/sending.md']['front_matter']['owner_alert'] = 'Text the owner when a draft is waiting or a customer asks for a person';
    $version->update(['compiled' => $compiled]);
    app()->forgetScopedInstances();

    $this->travelTo(Carbon::parse('2026-10-13 21:00', 'America/Chicago'));
    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    expect($alert->reason)->toBe(AlertReason::FrontDeskFailed)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and($alert->body)->toContain('No front desk agent is set up for texts')
        ->and(textsSentToCustomers())->toHaveCount(2)
        ->and(frontDeskFailureTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1);
});

const FRONT_DESK_FAILURE_OWNER_PHONE = '+12025550199';

const FRONT_DESK_FAILURE_CUSTOMER_PHONE = '+12025550123';

const FRONT_DESK_FAILURE_COMPLAINT = 'The last tech was rude and the AC still is not working.';

const FRONT_DESK_FAILURE_AFTER_HOURS = '2026-10-14 02:00';

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function frontDeskFailureTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === FRONT_DESK_FAILURE_CUSTOMER_PHONE,
    ));
}

/**
 * The body of the text the owner received about the front desk failing.
 */
function frontDeskFailureOwnerAlertText(): string
{
    return (string) collect(textsSentToCustomers())->first(fn (array $text): bool => $text['to'] === FRONT_DESK_FAILURE_OWNER_PHONE)['body'];
}

/**
 * The owner with a phone, the clock at the given moment in the shop's zone, and Dana's text in the live example folder
 * while every model is down.
 */
function frontDeskFailureDanaTextsWhileEveryModelIsDown(string $body = 'My AC is blowing warm air', string $at = '2026-10-13 10:00'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(FRONT_DESK_FAILURE_OWNER_PHONE)->create();
    frontDeskCustomerTexts($body);
    FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();
}

/**
 * The one alert of a reason, or null.
 */
function frontDeskFailureAlert(AlertReason $reason): ?OwnerAlert
{
    return OwnerAlert::query()->where('reason', $reason->value)->first();
}

it('sends the holding reply once when Jev is down and every model failed (D-51)', function () {
    frontDeskFailureDanaTextsWhileEveryModelIsDown();
    fakeClassifier()->failWith('Jev could not be reached or took too long to answer.');

    runQueuedFrontDeskJobs();

    expect(frontDeskFailureTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(frontDeskFailureAlert(AlertReason::FrontDeskFailed))->not->toBeNull();
});

it('treats an answer with no possible_danger as a failed model, so the customer gets the holding reply once and the owner hears of it (WR-02, D-51)', function () {
    User::factory()->withPhone(FRONT_DESK_FAILURE_OWNER_PHONE)->create();
    frontDeskCustomerTexts();
    FrontDeskAgent::fake(fn () => [
        'reply' => 'A technician can come out tomorrow.',
        'label' => 'faq_answer',
        'wants_person' => false,
        'service_slug' => null,
        'intake_answers' => [],
        'customer_name' => null,
    ])->preventStrayPrompts();

    runQueuedFrontDeskJobs();

    expect(frontDeskFailureTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::AgentReply->value)->count())->toBe(0)
        ->and(frontDeskFailureAlert(AlertReason::FrontDeskFailed))->not->toBeNull();
});

it('sends the added-info reply, not a second holding reply, when a second text arrives while every model is still down (D-29, D-51; Ian, 2026-10-16)', function () {
    frontDeskFailureDanaTextsWhileEveryModelIsDown();
    runQueuedFrontDeskJobs();

    $this->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('d', 32), 'Body' => 'Hello?']))->assertOk();
    runQueuedFrontDeskJobs();

    expect(frontDeskFailureTextsToDana())->toHaveCount(2)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(1)
        ->and(OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->count())->toBe(2);
});

/**
 * Tuesday 10:00 in the shop's zone, the owner with a phone, and Dana's price question held as a draft with the holding
 * reply while the models are up. A minute later she texts again and the given models answer: every model down by
 * default.
 */
function frontDeskFailureDanaWaitsThenTextsAgain(string $secondText = 'Hello? Any news?'): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(FRONT_DESK_FAILURE_OWNER_PHONE)->create();
    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);
    runQueuedFrontDeskJobs();

    test()->travel(1)->minutes();
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('e', 32), 'Body' => $secondText]))->assertOk();
    FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();
}

it('sends the added-info reply once and tells the owner when every model fails while an earlier held draft is still current (item 6)', function () {
    frontDeskFailureDanaWaitsThenTextsAgain();

    runQueuedFrontDeskJobs();

    $texts = frontDeskFailureTextsToDana();
    $newest = Message::query()->where('direction', MessageDirection::Out)->latest('id')->first();

    expect($texts)->toHaveCount(2)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(1)
        ->and($newest->kind)->toBe(MessageKind::AddedInfoReply)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(1)
        ->and(OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->count())->toBe(1);
});

it('sends the added-info reply, not the complaint reply, and raises both alerts when a complaint finds every model down during a current wait (item 6)', function () {
    frontDeskFailureDanaWaitsThenTextsAgain(FRONT_DESK_FAILURE_COMPLAINT);
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    runQueuedFrontDeskJobs();

    expect(frontDeskFailureTextsToDana())->toHaveCount(2)
        ->and(Message::query()->where('kind', MessageKind::AddedInfoReply->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(0)
        ->and(frontDeskFailureAlert(AlertReason::Complaint))->not->toBeNull()
        ->and(frontDeskFailureAlert(AlertReason::FrontDeskFailed))->not->toBeNull();
});

it('sends the complaint reply, a complaint alert and the failure alert when a complaint finds every model down (D-45, D-51)', function () {
    frontDeskFailureDanaTextsWhileEveryModelIsDown(FRONT_DESK_FAILURE_COMPLAINT);
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    runQueuedFrontDeskJobs();

    $complaint = frontDeskFailureAlert(AlertReason::Complaint);

    expect(frontDeskFailureTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0)
        ->and($complaint?->urgent)->toBeTrue()
        ->and($complaint?->sent_at)->not->toBeNull()
        ->and(frontDeskFailureAlert(AlertReason::FrontDeskFailed))->not->toBeNull();
});

it('sends the complaint alert at once after hours when the folder lists complaint under right_away and every model is down (D-45)', function () {
    frontDeskFailureDanaTextsWhileEveryModelIsDown(FRONT_DESK_FAILURE_COMPLAINT, FRONT_DESK_FAILURE_AFTER_HOURS);
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    runQueuedFrontDeskJobs();

    $complaint = frontDeskFailureAlert(AlertReason::Complaint);

    expect($complaint?->urgent)->toBeTrue()
        ->and($complaint?->sent_at)->not->toBeNull();
});

it('keeps the complaint alert for the next opening when complaint is off right_away, and the failure alert keeps its own timing (D-45)', function () {
    breakFile('rules/sending.md', fn (string $text): string => str_replace('right_away: [complaint, ', 'right_away: [', $text));
    frontDeskFailureDanaTextsWhileEveryModelIsDown(FRONT_DESK_FAILURE_COMPLAINT, FRONT_DESK_FAILURE_AFTER_HOURS);
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);

    runQueuedFrontDeskJobs();

    $complaint = frontDeskFailureAlert(AlertReason::Complaint);
    $failure = frontDeskFailureAlert(AlertReason::FrontDeskFailed);

    expect(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and($complaint?->urgent)->toBeFalse()
        ->and($complaint?->due_at->setTimezone('America/Chicago')->format('Y-m-d H:i'))->toBe('2026-10-14 08:00')
        ->and($complaint?->sent_at)->toBeNull()
        ->and($failure?->urgent)->toBeTrue()
        ->and($failure?->sent_at)->not->toBeNull();
});

it('sends the safety line and no holding reply when Jev reads a hazard and every model is down (D-45, D-51)', function () {
    frontDeskFailureDanaTextsWhileEveryModelIsDown('My head is pounding and the kids feel dizzy since the furnace came on.');
    fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9]);

    runQueuedFrontDeskJobs();

    expect(frontDeskFailureTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0);
});
