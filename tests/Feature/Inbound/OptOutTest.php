<?php

use App\Actions\InboundData;
use App\Actions\OutboundSender;
use App\Actions\RecordInbound;
use App\Actions\ResolveDraft;
use App\Actions\SendFixedReply;
use App\Actions\SendOwnerReply;
use App\Ai\Agents\FrontDeskAgent;
use App\Channels\FakeTextChannel;
use App\Channels\RecipientOptedOut;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\SendOrigin;
use App\Jobs\ProcessInbound;
use App\Livewire\ConversationThread;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\Records\CustomerCard;
use App\SendRule\CustomerReading;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

const OPT_OUT_SENTENCE = 'Opted out by texting STOP on Oct 13.';

const OPT_OUT_CUSTOMER_PHONE = '+12025550123';

/**
 * A reading of the customer's text that ran and found nothing to worry about, for the fixed replies that must carry one.
 */
function optOutReadingThatFoundNothing(): CustomerReading
{
    return new CustomerReading(
        ran: true,
        failed: false,
        error: null,
        model: 'fake-jev',
        answers: null,
        reading: ['sendable' => true, 'reason_code' => null, 'reasons' => [], 'life_safety' => false, 'asks_for_person' => false, 'thresholds' => []],
    );
}

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner signed in and reachable by text, and Dana's first text
 * answered by the front desk. Dana is on file so the alert and the card can name her.
 */
function optOutDanaIsAnswered(string $firstText = 'My AC is out', string $label = 'faq_answer'): Conversation
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));
    test()->actingAs(User::factory()->withPhone('+12025550199')->create());

    Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => OPT_OUT_CUSTOMER_PHONE]);

    frontDeskCustomerTexts($firstText);
    scriptFrontDeskReplies(['reply' => 'A technician can come out.', 'label' => $label]);
    runQueuedFrontDeskJobs();

    return Conversation::query()->sole();
}

/**
 * Dana texts STOP, as Twilio's Advanced Opt-Out reports it.
 *
 * @param  array<string, string>  $overrides
 */
function optOutDanaTextsStop(array $overrides = []): void
{
    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        ...['MessageSid' => 'SM'.str_repeat('5', 32), 'Body' => 'STOP', 'OptOutType' => 'STOP'],
        ...$overrides,
    ]))->assertOk();
}

/**
 * The texts that reached Dana's own phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function optOutTextsSentToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === OPT_OUT_CUSTOMER_PHONE,
    ));
}

it('records a STOP on the contact with no reply of our own and keeps the keyword away from the front desk (D-39)', function () {
    $conversation = optOutDanaIsAnswered();
    $sentBefore = count(textsSentToCustomers());

    optOutDanaTextsStop();

    $stop = Message::query()->where('body', 'STOP')->sole();
    $contact = $conversation->contact->refresh();

    $this->assertTrue($contact->isOptedOut());

    expect($contact->sms_opted_out_at)->not->toBeNull()
        ->and($stop->dispatched_at)->not->toBeNull()
        ->and($stop->handled_at)->not->toBeNull()
        ->and($stop->meta['opt_out_type'])->toBe('STOP')
        ->and(count(textsSentToCustomers()))->toBe($sentBefore)
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(1);

    Queue::assertNotPushed(ProcessInbound::class, fn (ProcessInbound $job) => $job->messageId === $stop->id);

    $entry = $contact->activities()->where('type', ActivityType::OptedOut)->sole();

    expect($entry->summary)->toBe('Opted out by texting STOP')
        ->and($entry->subject_id)->toBe($stop->id);
});

it('answers the STOP webhook with empty TwiML (D-39)', function () {
    optOutDanaIsAnswered();

    $this->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_repeat('6', 32),
        'Body' => 'stop',
        'OptOutType' => 'stop',
    ]))
        ->assertOk()
        ->assertSee('<Response', false)
        ->assertDontSee('<Message', false);
});

it('keeps the first opt-out date and one timeline entry when STOP arrives again', function () {
    $conversation = optOutDanaIsAnswered();
    optOutDanaTextsStop();
    $firstOptOut = $conversation->contact->refresh()->sms_opted_out_at;

    $this->travelTo(Carbon::parse('2026-10-14 10:00', 'America/Chicago'));
    optOutDanaTextsStop(['MessageSid' => 'SM'.str_repeat('7', 32)]);

    $contact = $conversation->contact->refresh();

    expect($contact->sms_opted_out_at->equalTo($firstOptOut))->toBeTrue()
        ->and($contact->activities()->where('type', ActivityType::OptedOut)->count())->toBe(1);
});

it('refuses every origin once the contact has opted out, with the date in the business time zone (D-40)', function (SendOrigin $origin) {
    $conversation = optOutDanaIsAnswered();
    optOutDanaTextsStop();

    $this->travelTo(Carbon::parse('2026-10-20 10:00', 'America/Chicago'));

    expect(app(OutboundSender::class)->whyBlocked($conversation->refresh(), $origin))->toBe(OPT_OUT_SENTENCE);
})->with([
    'the owner' => [SendOrigin::Owner],
    'automated text' => [SendOrigin::Automated],
    'the life-safety line' => [SendOrigin::LifeSafety],
]);

it('refuses to send anything through the one outbound path after STOP', function () {
    $conversation = optOutDanaIsAnswered();
    optOutDanaTextsStop();
    $sentBefore = count(textsSentToCustomers());

    expect(fn () => app(OutboundSender::class)->send(
        $conversation,
        'Hello',
        MessageAuthor::Agent,
        SendOrigin::LifeSafety,
    ))->toThrow(DomainException::class, OPT_OUT_SENTENCE);

    expect(count(textsSentToCustomers()))->toBe($sentBefore)
        ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(1);
});

it('shows the owner why they cannot reply, in place of the form, and refuses the reply (D-40)', function () {
    $conversation = optOutDanaIsAnswered();
    optOutDanaTextsStop();
    $sentBefore = count(textsSentToCustomers());

    $thread = Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->assertSee(OPT_OUT_SENTENCE);

    expect($thread->html())->not->toMatch('/<textarea[^>]*wire:model[^>]*="body"/');

    expect(fn () => app(SendOwnerReply::class)->handle($conversation, 'Can I still help?'))
        ->toThrow(ValidationException::class, OPT_OUT_SENTENCE);

    expect(count(textsSentToCustomers()))->toBe($sentBefore);
});

it('refuses to approve a live draft after STOP and sends nothing', function () {
    $conversation = optOutDanaIsAnswered('How much for a new unit?', 'price');
    $draft = theAgentMessage();

    expect($draft->status->value)->toBe('draft');

    optOutDanaTextsStop();
    $sentToDanaBefore = count(optOutTextsSentToDana());

    expect(fn () => app(ResolveDraft::class)->approve($draft, User::query()->firstOrFail()))
        ->toThrow(ValidationException::class, OPT_OUT_SENTENCE);

    expect(count(optOutTextsSentToDana()))->toBe($sentToDanaBefore)
        ->and($draft->refresh()->status->value)->toBe('draft')
        ->and($conversation->contact->refresh()->isOptedOut())->toBeTrue();
});

it('shows the opt-out on the contact card', function () {
    $conversation = optOutDanaIsAnswered();
    optOutDanaTextsStop();

    $card = app(CustomerCard::class)->render($conversation->contact->refresh());

    expect($card)->toContain('Opted out of texts')
        ->and($card)->toContain('Opted out by texting STOP');
});

it('has a factory state for a contact who opted out', function () {
    $contact = Contact::factory()->optedOut()->create();

    expect($contact->isOptedOut())->toBeTrue()
        ->and(Contact::factory()->create()->isOptedOut())->toBeFalse();
});

/**
 * Dana texts a word to the business with no OptOutType, the way a number would text before the carrier reports it.
 */
function optOutDanaTexts(string $body, string $sid): void
{
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat($sid, 32), 'Body' => $body]))->assertOk();
}

it('opts a customer out from a STOP that arrives with no OptOutType, because the fake channel simulates Twilio (D-39)', function () {
    $conversation = optOutDanaIsAnswered();
    $sentBefore = count(textsSentToCustomers());

    optOutDanaTexts('STOP', '8');

    $stop = Message::query()->where('body', 'STOP')->sole();

    expect($conversation->contact->refresh()->isOptedOut())->toBeTrue()
        ->and($stop->meta['opt_out_type'])->toBe('STOP')
        ->and($stop->handled_at)->not->toBeNull()
        ->and(count(textsSentToCustomers()))->toBe($sentBefore);
});

it('opts a customer out from the backup matcher when the provider reports nothing (D-39)', function () {
    $conversation = optOutDanaIsAnswered();

    $inbound = new InboundData(
        channel: MessageChannel::Sms,
        source: 'text',
        body: 'Unsubscribe',
        phone: OPT_OUT_CUSTOMER_PHONE,
        email: null,
        name: null,
        provider: 'fake',
        providerMessageId: 'backup-1',
    );

    app(RecordInbound::class)->handle($inbound);

    expect($conversation->contact->refresh()->isOptedOut())->toBeTrue();
});

it('treats a sentence that starts with stop as an ordinary message (D-39)', function () {
    $conversation = optOutDanaIsAnswered();

    optOutDanaTexts('stop the noise please', '9');

    $message = Message::query()->where('body', 'stop the noise please')->sole();

    expect($conversation->contact->refresh()->isOptedOut())->toBeFalse()
        ->and($message->handled_at)->toBeNull();

    Queue::assertPushed(ProcessInbound::class, fn (ProcessInbound $job) => $job->messageId === $message->id);
});

it('clears the opt-out on START with an opted_in entry and no reply of our own (D-40)', function () {
    $conversation = optOutDanaIsAnswered();
    optOutDanaTextsStop();
    $sentBefore = count(textsSentToCustomers());

    optOutDanaTexts('START', 'a');

    $start = Message::query()->where('body', 'START')->sole();
    $contact = $conversation->contact->refresh();
    $entry = $contact->activities()->where('type', ActivityType::OptedIn)->sole();

    expect($contact->isOptedOut())->toBeFalse()
        ->and($contact->sms_opted_out_at)->toBeNull()
        ->and($entry->summary)->toBe('Opted back in by texting START')
        ->and($entry->subject_id)->toBe($start->id)
        ->and($start->handled_at)->not->toBeNull()
        ->and(count(textsSentToCustomers()))->toBe($sentBefore);

    Queue::assertNotPushed(ProcessInbound::class, fn (ProcessInbound $job) => $job->messageId === $start->id);
});

it('lets YES from a contact who is not opted out reach the front desk as an ordinary answer (Pitfall 14)', function () {
    $conversation = optOutDanaIsAnswered();

    optOutDanaTexts('YES', 'b');

    $yes = Message::query()->where('body', 'YES')->sole();

    expect($yes->handled_at)->toBeNull()
        ->and($conversation->contact->refresh()->activities()->where('type', ActivityType::OptedIn)->count())->toBe(0);

    Queue::assertPushed(ProcessInbound::class, fn (ProcessInbound $job) => $job->messageId === $yes->id);

    scriptFrontDeskReplies(['reply' => 'Great, we will get that booked.']);
    runQueuedFrontDeskJobs();

    FrontDeskAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'YES'));
});

it('opts a contact back in on YES when they are opted out (Pitfall 14)', function () {
    $conversation = optOutDanaIsAnswered();
    optOutDanaTextsStop();

    optOutDanaTexts('Yes', 'd');

    $yes = Message::query()->where('body', 'Yes')->sole();

    expect($conversation->contact->refresh()->isOptedOut())->toBeFalse()
        ->and($conversation->contact->activities()->where('type', ActivityType::OptedIn)->count())->toBe(1)
        ->and($yes->handled_at)->not->toBeNull();

    Queue::assertNotPushed(ProcessInbound::class, fn (ProcessInbound $job) => $job->messageId === $yes->id);
});

it('handles HELP without sending anything of our own and keeps it from the front desk (D-39)', function () {
    $conversation = optOutDanaIsAnswered();
    $sentBefore = count(textsSentToCustomers());

    optOutDanaTexts('HELP', 'e');

    $help = Message::query()->where('body', 'HELP')->sole();

    expect($help->handled_at)->not->toBeNull()
        ->and($help->dispatched_at)->not->toBeNull()
        ->and($conversation->contact->refresh()->isOptedOut())->toBeFalse()
        ->and(count(textsSentToCustomers()))->toBe($sentBefore);

    Queue::assertNotPushed(ProcessInbound::class, fn (ProcessInbound $job) => $job->messageId === $help->id);
});

it('sends nothing to an opted-out customer who writes again and tells the owner so they can call (D-40)', function () {
    $conversation = optOutDanaIsAnswered();
    optOutDanaTextsStop();
    $sentToDanaBefore = count(optOutTextsSentToDana());

    optOutDanaTexts('Are you coming today?', 'f');
    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    expect(count(optOutTextsSentToDana()))->toBe($sentToDanaBefore)
        ->and($alert->reason)->toBe(AlertReason::OptedOutMessage)
        ->and($alert->body)->toContain('Are you coming today?')
        ->and($alert->sent_at)->not->toBeNull()
        ->and($conversation->contact->refresh()->isOptedOut())->toBeTrue();

    FrontDeskAgent::assertPromptedTimes(1);
});

it('keeps its own opted-out numbers like the carrier does and refuses a send to one (21610)', function () {
    $channel = new FakeTextChannel;

    $stop = $channel->parseInbound(Request::create('/', 'POST', fakeTextPayload(['Body' => 'Stop'])));

    expect($stop->optOutType)->toBe('STOP');

    expect(fn () => $channel->send(OPT_OUT_CUSTOMER_PHONE, 'Hello'))
        ->toThrow(RecipientOptedOut::class, '21610');

    expect($channel->sent())->toBe([])
        ->and($channel->refused())->toBe([['to' => OPT_OUT_CUSTOMER_PHONE, 'body' => 'Hello']]);

    $start = $channel->parseInbound(Request::create('/', 'POST', fakeTextPayload(['Body' => 'start'])));

    expect($start->optOutType)->toBe('START');

    $channel->send(OPT_OUT_CUSTOMER_PHONE, 'Welcome back');

    expect($channel->sent())->toHaveCount(1);
});

it('lets a test opt a number out and back in on the fake channel directly', function () {
    $channel = new FakeTextChannel;

    $channel->optOut('+12025550123');

    expect(fn () => $channel->send('+12025550123', 'Hi'))->toThrow(RecipientOptedOut::class);

    $channel->optIn('+12025550123');
    $channel->send('+12025550123', 'Hi');

    expect($channel->sent())->toHaveCount(1);
});

/**
 * Tuesday 10:00 in the shop's zone, the owner reachable by text, and Dana's first text waiting to be answered.
 * Nothing has run yet, so a test decides what happens before the front desk does.
 */
function optOutDanaHasTextedAndWaits(string $text): void
{
    test()->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));
    test()->actingAs(User::factory()->withPhone('+12025550199')->create());

    Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => OPT_OUT_CUSTOMER_PHONE]);

    frontDeskCustomerTexts($text);
}

/**
 * Script one agent reply that is safe to send on its own, and opt Dana out while the agent is still writing it, the
 * way a STOP can land between the burst being claimed and the reply going out.
 */
function optOutDanaStopsWhileTheAgentWrites(): void
{
    FrontDeskAgent::fake([function () {
        Contact::query()->update(['sms_opted_out_at' => now()]);

        return [
            'reply' => 'A technician can come out.',
            'label' => 'faq_answer',
            'wants_person' => false,
            'service_slug' => null,
            'intake_answers' => [],
            'customer_name' => null,
        ];
    }])->preventStrayPrompts();
}

it('sends no agent reply and no holding reply when the opt-out lands while the agent writes (D-40)', function () {
    optOutDanaHasTextedAndWaits('Is my appointment still on?');
    optOutDanaStopsWhileTheAgentWrites();

    runQueuedFrontDeskJobs();

    expect(optOutTextsSentToDana())->toBe([])
        ->and(Message::query()->where('direction', MessageDirection::Out)->where('status', MessageStatus::Sent)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->where('status', MessageStatus::Sent)->count())->toBe(0);
});

it('sends no holding reply and no handoff reply to an opted-out customer (D-40)', function () {
    $conversation = optOutDanaIsAnswered();
    $conversation->contact->update(['sms_opted_out_at' => now()]);
    $sentBefore = count(textsSentToCustomers());

    expect(app(SendFixedReply::class)->holding($conversation->refresh(), optOutReadingThatFoundNothing()))->toBeNull()
        ->and(app(SendFixedReply::class)->handoff($conversation, optOutReadingThatFoundNothing()))->toBeNull()
        ->and(count(textsSentToCustomers()))->toBe($sentBefore);
});

it('answers a request for a person from an opted-out customer with an alert to the owner and no text (D-40)', function () {
    optOutDanaHasTextedAndWaits('Hi');
    Contact::query()->update(['sms_opted_out_at' => now()]);
    $sentToDanaBefore = count(optOutTextsSentToDana());

    optOutDanaTexts('Can I talk to a real person?', 'h');
    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->where('reason', AlertReason::OptedOutMessage->value)->sole();

    expect(count(optOutTextsSentToDana()))->toBe($sentToDanaBefore)
        ->and($alert->body)->toContain('Can I talk to a real person?')
        ->and(Message::query()->where('kind', MessageKind::HandoffReply->value)->count())->toBe(0);
});

it('sends no safety line to an opted-out customer but still alerts the owner and says why (D-40)', function () {
    optOutDanaHasTextedAndWaits('Hello');
    Contact::query()->update(['sms_opted_out_at' => now()]);

    optOutDanaTexts('I smell gas in the basement', 'g');
    runQueuedFrontDeskJobs();

    $safety = OwnerAlert::query()->where('reason', AlertReason::LifeSafety->value)->sole();

    expect(optOutTextsSentToDana())->toBe([])
        ->and($safety->body)->toContain('Safety message could not be sent')
        ->and($safety->sent_at)->not->toBeNull()
        ->and(OwnerAlert::query()->where('reason', AlertReason::OptedOutMessage->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->where('status', MessageStatus::Sent)->count())->toBe(0);
});

it('corrects a stale local record when the carrier refuses an agent reply because the number opted out (21610)', function () {
    optOutDanaHasTextedAndWaits('Is my appointment still on?');
    app(FakeTextChannel::class)->optOut(OPT_OUT_CUSTOMER_PHONE);
    scriptFrontDeskReplies(['reply' => 'A technician can come out.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    $failed = Message::query()->where('direction', MessageDirection::Out)->sole();
    $contact = Contact::query()->where('phone_e164', OPT_OUT_CUSTOMER_PHONE)->sole();
    $alert = OwnerAlert::query()->where('reason', AlertReason::FrontDeskFailed->value)->sole();

    expect($failed->status)->toBe(MessageStatus::Failed)
        ->and($failed->meta['error'])->toContain('21610')
        ->and($contact->isOptedOut())->toBeTrue()
        ->and($contact->activities()->where('type', ActivityType::Note)->where('summary', 'like', 'Not sent: the customer has opted out of texts%')->count())->toBe(1)
        ->and($alert->sent_at)->not->toBeNull()
        ->and(optOutTextsSentToDana())->toBe([])
        ->and(app(FakeTextChannel::class)->refused())->toHaveCount(1);
});

it('tells the owner the customer has opted out when the carrier refuses their reply (21610)', function () {
    $conversation = optOutDanaIsAnswered();
    app(FakeTextChannel::class)->optOut(OPT_OUT_CUSTOMER_PHONE);

    Livewire::test(ConversationThread::class, ['conversation' => $conversation])
        ->set('body', 'Can I still help?')
        ->call('sendReply')
        ->assertNotified(Notification::make()->title('The customer has opted out of texts.')->danger());

    expect($conversation->contact->refresh()->isOptedOut())->toBeTrue()
        ->and(Message::query()->where('body', 'Can I still help?')->sole()->status)->toBe(MessageStatus::Failed);
});
