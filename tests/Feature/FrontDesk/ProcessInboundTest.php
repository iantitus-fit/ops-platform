<?php

use App\Actions\OutboundSender;
use App\Ai\Agents\FrontDeskAgent;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\ReplyLabel;
use App\Enums\SendOrigin;
use App\Icm\ActiveConfig;
use App\Jobs\ProcessInbound;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

it('answers a customer text with the disclosure, the reply and the sign-off, and stores the verdict', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that. What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['to'])->toBe('+12025550123')
        ->and(textsSentToCustomers()[0]['body'])->toBe(composedFirstReply('Sorry to hear that. What is the system doing?'));

    $inbound = Message::query()->where('direction', MessageDirection::In)->sole();
    $reply = theAgentMessage();

    expect($reply->conversation_id)->toBe($inbound->conversation_id)
        ->and($reply->author)->toBe(MessageAuthor::Agent)
        ->and($reply->kind)->toBe(MessageKind::AgentReply)
        ->and($reply->status)->toBe(MessageStatus::Sent)
        ->and($reply->provider_message_id)->toBe(textsSentToCustomers()[0]['provider_message_id'])
        ->and($reply->label)->toBe(ReplyLabel::IntakeQuestion)
        ->and($reply->gate_outcome)->toBe(GateOutcome::Send)
        ->and($reply->gate_reason)->toBeNull()
        ->and($reply->gate['version'])->toBe(1)
        ->and($reply->meta['in_reply_to'])->toBe([$inbound->id]);
});

it('records a reply sent by the front desk on the timeline', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $activity = Contact::sole()->activities()->where('type', ActivityType::ReplySent)->sole();

    expect($activity->summary)->toBe('Reply sent by front desk')
        ->and($activity->subject_id)->toBe(theAgentMessage()->id);
});

it('puts the disclosure only on the first automated text of a conversation', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('d', 32), 'Body' => 'It just blows warm']))->assertOk();
    scriptFrontDeskReplies(['reply' => 'How old is the system?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    $voice = app(ActiveConfig::class)->voice();

    expect(textsSentToCustomers())->toHaveCount(2)
        ->and(textsSentToCustomers()[1]['body'])->toBe("How old is the system?\n".$voice['sign_off'])
        ->and(textsSentToCustomers()[1]['body'])->not->toContain($voice['ai_disclosure']);
});

it('answers a burst of texts once, with every text of the burst as the customer text', function () {
    frontDeskCustomerTexts();
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat('d', 32), 'Body' => 'It is 90 degrees inside']))->assertOk();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $inboundIds = Message::query()->where('direction', MessageDirection::In)->orderBy('id')->pluck('id')->all();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(theAgentMessage()->meta['in_reply_to'])->toBe($inboundIds)
        ->and(fakeClassifier()->calls()[0]['customer_message'])->toBe("My AC is blowing warm air\nIt is 90 degrees inside");
});

it('asks Jev once, about the burst text and the words the agent wrote, not the disclosure or the sign-off (D-04)', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that. What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(fakeClassifier()->calls())->toBe([[
        'customer_message' => 'My AC is blowing warm air',
        'draft_reply' => 'Sorry to hear that. What is the system doing?',
    ]]);
});

it('keeps a price reply as a draft even when the agent labels it a faq answer, and the veto stays its reason (GATE-01, D-02, D-45)', function () {
    frontDeskCustomerTexts('How much is a diagnostic?');
    scriptFrontDeskReplies(['reply' => 'A diagnostic visit is $89.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    $draft = theAgentMessage();

    expect(textsSentForAgentReplies())->toBe([])
        ->and($draft->status)->toBe(MessageStatus::Draft)
        ->and($draft->author)->toBe(MessageAuthor::Agent)
        ->and($draft->kind)->toBe(MessageKind::AgentReply)
        ->and($draft->body)->toBe('A diagnostic visit is $89.')
        ->and($draft->label)->toBe(ReplyLabel::FaqAnswer)
        ->and($draft->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($draft->gate_reason)->toBe('code:price')
        ->and($draft->provider_message_id)->toBeNull()
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['A diagnostic visit is $89.']);
});

it('never sends a reply the agent labels price, promise, discount or complaint, or other', function (string $label) {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'The owner will follow up with you.', 'label' => $label]);

    runQueuedFrontDeskJobs();

    expect(textsSentForAgentReplies())->toBe([])
        ->and(theAgentMessage()->status)->toBe(MessageStatus::Draft)
        ->and(theAgentMessage()->gate_outcome)->toBe(GateOutcome::Hold)
        ->and(theAgentMessage()->gate_reason)->toBe("label:{$label}")
        ->and(theAgentMessage()->gate['checks']['classifier']['ran'])->toBeTrue()
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['The owner will follow up with you.']);
})->with(['price', 'promise', 'discount', 'complaint', 'other']);

it('keeps the floor in code: a stored rule that lists price as sendable still holds a price reply (D-08)', function () {
    frontDeskCustomerTexts();

    $this->partialMock(ActiveConfig::class, function (MockInterface $config) {
        $config->shouldReceive('sendingRules')->andReturn([
            'send_on_own' => ['intake_question', 'scheduling', 'faq_answer', 'price', 'promise', 'discount', 'complaint'],
            'draft_for_owner' => ['other'],
            'owner_alert' => ['channels' => ['text'], 'right_away' => [], 'after_hours' => 'next_opening'],
        ]);
    });
    scriptFrontDeskReplies(['reply' => 'The owner will follow up with you.', 'label' => 'price']);

    runQueuedFrontDeskJobs();

    expect(textsSentForAgentReplies())->toBe([])
        ->and(theAgentMessage()->gate_reason)->toBe('label:price')
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['The owner will follow up with you.']);
});

it('holds a reply that uses a phrase the voice file says never to use', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'Sure, no problem at all.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    expect(textsSentForAgentReplies())->toBe([])
        ->and(theAgentMessage()->gate_reason)->toBe('code:never_say')
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe(['Sure, no problem at all.']);
});

it('holds a final text over 1,600 characters instead of cutting it (Pitfall 10)', function () {
    frontDeskCustomerTexts();
    $words = trim(str_repeat('Thanks for waiting on us. ', 57));
    scriptFrontDeskReplies(['reply' => $words, 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    $draft = theAgentMessage();

    expect(mb_strlen($words))->toBeLessThan(1600)
        ->and(mb_strlen(composedFirstReply($words)))->toBeGreaterThan(1600)
        ->and(textsSentForAgentReplies())->toBe([])
        ->and($draft->status)->toBe(MessageStatus::Draft)
        ->and($draft->body)->toBe($words)
        ->and($draft->gate_reason)->toBe('too_long')
        ->and($draft->gate['checks']['length'])->toMatchArray(['characters' => mb_strlen(composedFirstReply($words)), 'limit' => 1600])
        ->and(array_column(fakeClassifier()->calls(), 'draft_reply'))->toBe([$words]);
});

it('holds the reply when Jev is down', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    fakeClassifier()->failWith('down');

    runQueuedFrontDeskJobs();

    $draft = theAgentMessage();

    expect(textsSentForAgentReplies())->toBe([])
        ->and($draft->status)->toBe(MessageStatus::Draft)
        ->and($draft->gate_reason)->toBe('classifier_failed')
        ->and($draft->gate['checks']['classifier']['ran'])->toBeTrue()
        ->and($draft->gate['checks']['classifier']['error'])->toBe('down');
});

it('holds the reply when Jev reads it as an intent the owner has not made sendable', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    fakeClassifier()->answer('price_question', 0.99);

    runQueuedFrontDeskJobs();

    expect(textsSentForAgentReplies())->toBe([])
        ->and(theAgentMessage()->gate_reason)->toBe('classifier_intent:price_question')
        ->and(theAgentMessage()->gate['checks']['classifier']['reading']['sendable'])->toBeFalse();
});

it('can only be held by Jev, never rescued: a clear Jev answer does not send a label the floor holds', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'The owner will follow up with you.', 'label' => 'promise']);
    fakeClassifier()->answer('faq', 0.99);

    runQueuedFrontDeskJobs();

    expect(textsSentForAgentReplies())->toBe([])
        ->and(theAgentMessage()->gate_reason)->toBe('label:promise');
});

it('stores the whole verdict on the message: inputs, every check, thresholds and the model that wrote the reply (D-07)', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $gate = theAgentMessage()->gate;

    expect($gate['version'])->toBe(1)
        ->and($gate['label'])->toBe('intake_question')
        ->and($gate['wants_person'])->toBeFalse()
        ->and($gate['inputs'])->toBe(['customer_text' => 'My AC is blowing warm air', 'agent_reply' => 'What is the system doing?'])
        ->and($gate['checks']['label']['passed'])->toBeTrue()
        ->and($gate['checks']['length'])->toMatchArray(['passed' => true, 'limit' => 1600])
        ->and($gate['checks']['code'])->toBe(['passed' => true, 'findings' => []])
        ->and($gate['checks']['classifier']['ran'])->toBeTrue()
        ->and($gate['checks']['classifier']['model'])->toBe('fake-jev')
        ->and($gate['checks']['classifier']['answers']['intent'])->toBe('faq')
        ->and($gate['checks']['classifier']['error'])->toBeNull()
        ->and($gate['checks']['classifier']['reading']['sendable'])->toBeTrue()
        ->and($gate['thresholds'])->toBe(config('gate.classifier'))
        ->and($gate['reply_model'])->toBe(['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5'])
        ->and($gate['failed_attempts'])->toBe([])
        ->and($gate['reason'])->toBe(['code' => null, 'plain' => null]);
});

it('stores the rule that held a reply and a plain reason on the verdict', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'A diagnostic visit is $89.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    $gate = theAgentMessage()->gate;

    expect($gate['checks']['code']['passed'])->toBeFalse()
        ->and($gate['checks']['code']['findings'][0])->toBe(['rule' => 'price', 'match' => '$89'])
        ->and($gate['checks']['classifier']['ran'])->toBeTrue()
        ->and($gate['reason']['code'])->toBe('code:price')
        ->and($gate['reason']['plain'])->toBeString()->not->toBe('');
});

it('stores what Jev said about life safety and a request for a person on the verdict', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    fakeClassifier()->answer('faq', 0.95, ['life_safety' => 0.9]);

    runQueuedFrontDeskJobs();

    $reading = theAgentMessage()->gate['checks']['classifier']['reading'];

    expect(textsSentForAgentReplies())->toBe([])
        ->and(theAgentMessage()->gate_reason)->toBe('classifier:life_safety')
        ->and($reading['life_safety'])->toBeTrue()
        ->and($reading['asks_for_person'])->toBeFalse();
});

it('keeps the reply as a draft when the sender refuses it, such as a conversation closed in the meantime', function () {
    frontDeskCustomerTexts();
    Conversation::sole()->update(['status' => ConversationStatus::Closed, 'closed_at' => now()]);
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $draft = theAgentMessage();

    expect(textsSentToCustomers())->toBe([])
        ->and($draft->status)->toBe(MessageStatus::Draft)
        ->and($draft->body)->toBe('What is the system doing?')
        ->and($draft->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($draft->gate_reason)->toBe('send_refused');
});

it('writes a note and sends only the holding reply when every model failed (D-51)', function () {
    frontDeskCustomerTexts();
    FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();

    runQueuedFrontDeskJobs();

    $note = Contact::sole()->activities()->where('type', ActivityType::Note)->sole();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(Message::query()->where('direction', MessageDirection::Out)->sole()->kind)->toBe(MessageKind::HoldingReply)
        ->and($note->summary)->toBe('Front desk could not answer: every model failed');
});

it('never texts a web form lead, even when a reply is scripted (D-41)', function () {
    activateExampleFolder();
    scriptFrontDeskReplies(['reply' => 'Thanks for reaching out.', 'label' => 'intake_question']);

    $this->postJson(route('leads.store'), [
        'name' => 'Pat Newcomer',
        'phone' => '(202) 555-0188',
        'email' => 'pat@example.com',
        'message' => 'Need AC looked at this week',
        'source' => 'angi',
    ])->assertOk();

    runQueuedFrontDeskJobs();

    expect(textsSentToCustomers())->toBe([])
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0);

    FrontDeskAgent::assertNeverPrompted();
});

it('sends nothing and throws nothing when no business folder is live yet', function () {
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    $this->post(route('webhooks.text.fake'), fakeTextPayload())->assertOk();

    runQueuedFrontDeskJobs();

    expect(textsSentToCustomers())->toBe([])
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0);

    FrontDeskAgent::assertNeverPrompted();
});

it('sends one text when the queued job runs twice (AGNT-02 concurrency)', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $inbound = Message::query()->where('direction', MessageDirection::In)->sole();
    app()->call([new ProcessInbound($inbound->id, $inbound->conversation_id), 'handle']);

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(1);

    FrontDeskAgent::assertPromptedTimes(1);
});

it('keeps a failed send on the record and raises it, when the channel will not take the text', function () {
    $conversation = Conversation::factory()->for(Contact::factory()->create(['phone_e164' => '+12025550123']))->create();
    $this->mock(TextChannel::class, function (MockInterface $channel) {
        $channel->shouldReceive('name')->andReturn('fake');
        $channel->shouldReceive('send')->andThrow(new RuntimeException('carrier down'));
    });

    expect(fn () => app(OutboundSender::class)->send($conversation, 'Hello', MessageAuthor::Agent, SendOrigin::Automated))
        ->toThrow(DomainException::class, 'The reply was not sent: carrier down');

    $failed = Message::query()->sole();

    expect($failed->status)->toBe(MessageStatus::Failed)
        ->and($failed->meta['error'])->toBe('carrier down')
        ->and($conversation->contact->activities()->where('type', ActivityType::Note)->sole()->summary)->toBe('Reply not sent: carrier down');
});

it('makes an automated send wait for the lock and give up, but an owner send fails at once with the owner wording', function () {
    config(['gate.agent.lock_wait_seconds' => 0]);
    $conversation = Conversation::factory()->for(Contact::factory()->create(['phone_e164' => '+12025550123']))->create();
    $lock = Cache::lock("reply:{$conversation->id}", 30);
    $lock->get();

    try {
        expect(fn () => app(OutboundSender::class)->send($conversation, 'Hello', MessageAuthor::Agent, SendOrigin::Automated))
            ->toThrow(DomainException::class, 'Another reply to this customer is being sent.')
            ->and(fn () => app(OutboundSender::class)->send($conversation, 'Hello', MessageAuthor::Owner, SendOrigin::Owner))
            ->toThrow(DomainException::class, 'Another reply to this customer is being sent. Wait a moment, then check the thread before sending again.');
    } finally {
        $lock->release();
    }

    expect(Message::query()->count())->toBe(0);
});

it('says why a conversation cannot be written to', function () {
    $sender = app(OutboundSender::class);
    $open = Conversation::factory()->for(Contact::factory()->create(['phone_e164' => '+12025550123']))->create();
    $closed = Conversation::factory()->for(Contact::factory()->create(['phone_e164' => '+12025550124']))->create(['status' => ConversationStatus::Closed]);
    $noPhone = Conversation::factory()->for(Contact::factory()->create(['phone_e164' => null]))->create();

    expect($sender->whyBlocked($open, SendOrigin::Automated))->toBeNull()
        ->and($sender->whyBlocked($closed, SendOrigin::Automated))->toBeString()->toContain('closed')
        ->and($sender->whyBlocked($noPhone, SendOrigin::Automated))->toBeString()->toContain('phone');
});
