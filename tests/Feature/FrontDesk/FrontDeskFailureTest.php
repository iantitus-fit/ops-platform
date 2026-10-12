<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
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
