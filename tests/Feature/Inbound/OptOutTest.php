<?php

use App\Actions\OutboundSender;
use App\Actions\ResolveDraft;
use App\Actions\SendOwnerReply;
use App\Enums\ActivityType;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\SendOrigin;
use App\Jobs\ProcessInbound;
use App\Livewire\ConversationThread;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Records\CustomerCard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

const OPT_OUT_SENTENCE = 'Opted out by texting STOP on Oct 13.';

const OPT_OUT_CUSTOMER_PHONE = '+12025550123';

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
