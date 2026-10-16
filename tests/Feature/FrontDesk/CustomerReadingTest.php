<?php

use App\Ai\AgentReply;
use App\Ai\AgentRun;
use App\Ai\Agents\FrontDeskAgent;
use App\Ai\FrontDeskPrompt;
use App\Enums\AlertReason;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\ClassifierFailed;
use App\SendRule\CustomerReading;
use App\SendRule\DecideSendOrDraft;
use App\SendRule\TriageQuestions;
use Illuminate\Support\Carbon;

const CUSTOMER_READING_OWNER_PHONE = '+12025550199';

const CUSTOMER_READING_CUSTOMER_PHONE = '+12025550123';

const CUSTOMER_READING_DIZZY = 'My head is pounding and the kids feel dizzy since the furnace came on.';

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's text in the live example folder.
 */
function customerReadingDanaTexts(string $body, string $at = '2026-10-13 10:00'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(CUSTOMER_READING_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function customerReadingTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === CUSTOMER_READING_CUSTOMER_PHONE,
    ));
}

it('sends the safety line first when Jev reads a hazard the keywords miss, even if the text also asked a price (gap 3)', function () {
    customerReadingDanaTexts(CUSTOMER_READING_DIZZY.' How much is a furnace repair?');
    fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9]);
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['furnace-repair']]);

    runQueuedFrontDeskJobs();

    $line = Message::query()->where('kind', MessageKind::LifeSafety->value)->sole();
    $burst = Message::query()->where('author', MessageAuthor::Customer->value)->get();
    $reading = fakeClassifier()->readingCalls();

    expect(customerReadingTextsToDana())->toHaveCount(1)
        ->and($line->gate['life_safety']['trigger'])->toBe('classifier')
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::LifeSafety)
        ->and(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::LifeSafety)
        ->and($reading)->toHaveCount(1)
        ->and($reading[0]['draft_reply'])->toBe('')
        ->and($reading[0]['customer_message'])->toBe(app(FrontDeskPrompt::class)->promptText($burst));
});

it('reads a plain text once for the customer and once for the words, and the intake question goes out', function () {
    customerReadingDanaTexts('My AC is blowing warm air');
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(fakeClassifier()->readingCalls())->toHaveCount(1)
        ->and(fakeClassifier()->calls())->toHaveCount(1)
        ->and(customerReadingTextsToDana())->toHaveCount(1)
        ->and(customerReadingTextsToDana()[0]['body'])->toContain('Thanks, what is the system doing?');
});

it('gives the reading and the words check the same customer characters (ANSR-03 encoding)', function () {
    customerReadingDanaTexts('My AC is blowing warm air, it’s 85° — “help”');
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(fakeClassifier()->readingCalls()[0]['customer_message'])->toBe(fakeClassifier()->calls()[0]['customer_message']);
});

it('makes no Jev call at all for a customer who opted out', function () {
    customerReadingDanaTexts('My AC is blowing warm air');
    Contact::query()->sole()->update(['sms_opted_out_at' => now()]);
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(fakeClassifier()->allCalls())->toBe([])
        ->and(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::OptedOutMessage);
});

it('scripts the words check with answer() and the reading with answerReading(), and fails each on its own', function () {
    $classifier = fakeClassifier();
    $questions = TriageQuestions::fromConfig(['intent_question' => 'q', 'intents' => [['key' => 'faq', 'means' => 'm', 'sendable' => true]], 'questions' => ['life_safety' => 'x']]);

    $classifier->answer('price_question', 0.9);
    $default = $classifier->classify('hello', '', $questions);
    $words = $classifier->classify('hello', 'a reply', $questions);

    expect($default->intent)->toBe('faq')
        ->and($default->yesNo['life_safety'])->toBe(0.01)
        ->and($words->intent)->toBe('price_question')
        ->and($classifier->readingCalls())->toHaveCount(1)
        ->and($classifier->calls())->toHaveCount(1)
        ->and($classifier->allCalls())->toHaveCount(2);

    $classifier->failReadingWith('reading down');

    expect(fn () => $classifier->classify('hello', '', $questions))->toThrow(ClassifierFailed::class, 'reading down')
        ->and($classifier->classify('hello', 'a reply', $questions)->intent)->toBe('price_question');

    $classifier->failWith('everything down');

    expect(fn () => $classifier->classify('hello', 'a reply', $questions))->toThrow(ClassifierFailed::class, 'everything down');
});

const CUSTOMER_READING_AFTER_HOURS = '2026-10-14 02:00';

/**
 * Take the given alert reasons off the folder's right_away list. Call it before Dana's text arrives, because the
 * folder is activated when the text does.
 *
 * @param  list<string>  $reasons
 */
function customerReadingTakeOffRightAway(array $reasons): void
{
    breakFile('rules/sending.md', fn (string $text): string => preg_replace_callback(
        '/right_away: \[([^\]]*)\]/',
        fn (array $match): string => 'right_away: ['.implode(', ', array_filter(
            array_map('trim', explode(',', $match[1])),
            fn (string $reason): bool => ! in_array($reason, $reasons, true),
        )).']',
        $text,
        1,
    ));
}

/**
 * The conversation the owner has taken over, so the assistant is paused in it.
 */
function customerReadingOwnerTakesOver(): void
{
    Conversation::query()->sole()->update(['paused_at' => now(), 'paused_reason' => PausedReason::TakenOver]);
}

/**
 * The one alert of a reason, or null.
 */
function customerReadingAlert(AlertReason $reason): ?OwnerAlert
{
    return OwnerAlert::query()->where('reason', $reason->value)->first();
}

/**
 * The shop's next opening after the after-hours text, as the alert stores it.
 */
function customerReadingDueAtOpening(OwnerAlert $alert): string
{
    return $alert->due_at->setTimezone('America/Chicago')->format('Y-m-d H:i');
}

it('still sends the safety line in a paused conversation when Jev reads a hazard the keywords miss (D-45)', function () {
    customerReadingDanaTexts(CUSTOMER_READING_DIZZY);
    customerReadingOwnerTakesOver();
    fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?']);

    runQueuedFrontDeskJobs();

    $line = Message::query()->where('kind', MessageKind::LifeSafety->value)->sole();

    expect(customerReadingTextsToDana())->toHaveCount(1)
        ->and($line->gate['life_safety']['trigger'])->toBe('classifier')
        ->and(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::LifeSafety)
        ->and(customerReadingAlert(AlertReason::PausedMessage))->toBeNull();

    FrontDeskAgent::assertNeverPrompted();
});

it('sends the life_safety alert at once at 2 am even with complaint and asked_for_person off right_away', function () {
    customerReadingTakeOffRightAway(['complaint', 'asked_for_person']);
    customerReadingDanaTexts(CUSTOMER_READING_DIZZY, CUSTOMER_READING_AFTER_HOURS);
    customerReadingOwnerTakesOver();
    fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?']);

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    expect($alert->reason)->toBe(AlertReason::LifeSafety)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull();
});

it('alerts the owner of a complaint the keywords cannot see in a paused conversation, and the customer gets nothing (D-45)', function () {
    customerReadingDanaTexts('The last tech left a mess in my basement and nobody called me back.', CUSTOMER_READING_AFTER_HOURS);
    customerReadingOwnerTakesOver();
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that.']);

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    expect($alert->reason)->toBe(AlertReason::Complaint)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and(customerReadingTextsToDana())->toBe([])
        ->and(Message::query()->where('kind', MessageKind::AgentReply->value)->count())->toBe(0);

    FrontDeskAgent::assertNeverPrompted();
});

it('holds a paused complaint alert until the shop opens when the folder takes complaint off right_away', function () {
    customerReadingTakeOffRightAway(['complaint']);
    customerReadingDanaTexts('The last tech left a mess in my basement and nobody called me back.', CUSTOMER_READING_AFTER_HOURS);
    customerReadingOwnerTakesOver();
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that.']);

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    expect($alert->reason)->toBe(AlertReason::Complaint)
        ->and($alert->urgent)->toBeFalse()
        ->and(customerReadingDueAtOpening($alert))->toBe('2026-10-14 08:00')
        ->and($alert->sent_at)->toBeNull()
        ->and(customerReadingTextsToDana())->toBe([]);
});

it('alerts asked_for_person in a paused conversation on Jev\'s reading or a keyword phrase, and sends nothing', function (string $text, array $reading) {
    customerReadingDanaTexts($text, CUSTOMER_READING_AFTER_HOURS);
    customerReadingOwnerTakesOver();
    fakeClassifier()->answerReading('faq', 0.95, $reading);
    scriptFrontDeskReplies(['reply' => 'Sure.']);

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    expect($alert->reason)->toBe(AlertReason::AskedForPerson)
        ->and($alert->urgent)->toBeTrue()
        ->and($alert->sent_at)->not->toBeNull()
        ->and(customerReadingTextsToDana())->toBe([]);
})->with([
    'Jev reads a request' => ['Honestly I\'d rather sort this out with Mike directly.', ['asks_for_person' => 0.9]],
    'a keyword phrase' => ['Can I talk to a real person?', []],
]);

it('holds a paused asked_for_person alert until the shop opens when the folder takes it off right_away', function () {
    customerReadingTakeOffRightAway(['asked_for_person']);
    customerReadingDanaTexts('Can I talk to a real person?', CUSTOMER_READING_AFTER_HOURS);
    customerReadingOwnerTakesOver();
    scriptFrontDeskReplies(['reply' => 'Sure.']);

    runQueuedFrontDeskJobs();

    $alert = OwnerAlert::query()->sole();

    expect($alert->reason)->toBe(AlertReason::AskedForPerson)
        ->and($alert->urgent)->toBeFalse()
        ->and(customerReadingDueAtOpening($alert))->toBe('2026-10-14 08:00')
        ->and($alert->sent_at)->toBeNull()
        ->and(customerReadingTextsToDana())->toBe([]);
});

/**
 * Six texts from the front desk in the last hour, so the reply cap is reached.
 */
function customerReadingFillReplyCap(): void
{
    foreach ([5, 10, 20, 30, 40, 55] as $minutes) {
        Message::factory()->for(Conversation::query()->sole())->outbound()->create([
            'kind' => MessageKind::AgentReply,
            'body' => 'Earlier automated text',
            'created_at' => now()->subMinutes($minutes),
        ]);
    }
}

it('alerts a complaint on the reply-cap path beside the reply_cap alert, sends nothing and pauses (D-45, D-52)', function () {
    customerReadingDanaTexts('The last tech left a mess in my basement and nobody called me back.');
    customerReadingFillReplyCap();
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that.']);

    runQueuedFrontDeskJobs();

    $complaint = customerReadingAlert(AlertReason::Complaint);

    expect($complaint)->not->toBeNull()
        ->and($complaint->urgent)->toBeTrue()
        ->and($complaint->sent_at)->not->toBeNull()
        ->and(customerReadingAlert(AlertReason::ReplyCap))->not->toBeNull()
        ->and(customerReadingTextsToDana())->toBe([])
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::ReplyCap);

    FrontDeskAgent::assertNeverPrompted();
});

it('lets the complaint alert wait for opening on the reply-cap path while the reply_cap alert keeps its own timing', function () {
    customerReadingTakeOffRightAway(['complaint']);
    customerReadingDanaTexts('The last tech left a mess in my basement and nobody called me back.', CUSTOMER_READING_AFTER_HOURS);
    customerReadingFillReplyCap();
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that.']);

    runQueuedFrontDeskJobs();

    $complaint = customerReadingAlert(AlertReason::Complaint);
    $cap = customerReadingAlert(AlertReason::ReplyCap);

    expect($complaint->urgent)->toBeFalse()
        ->and(customerReadingDueAtOpening($complaint))->toBe('2026-10-14 08:00')
        ->and($complaint->sent_at)->toBeNull()
        ->and($cap->urgent)->toBeTrue()
        ->and($cap->sent_at)->not->toBeNull();
});

it('sends the safety line first and only when a hazard the keywords miss comes with a request for a person (D-45)', function () {
    customerReadingDanaTexts(CUSTOMER_READING_DIZZY.' Can I talk to a real person?');
    fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9, 'asks_for_person' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Sure.']);

    runQueuedFrontDeskJobs();

    expect(customerReadingTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::HandoffReply->value)->count())->toBe(0)
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->count())->toBe(1)
        ->and(OwnerAlert::query()->pluck('reason')->all())->toEqualCanonicalizing([AlertReason::LifeSafety, AlertReason::AskedForPerson])
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::LifeSafety);

    FrontDeskAgent::assertNeverPrompted();
});

it('sends the safety line for a hazard the keywords miss when every model fails (D-45)', function () {
    customerReadingDanaTexts(CUSTOMER_READING_DIZZY);
    fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9]);
    FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();

    runQueuedFrontDeskJobs();

    expect(customerReadingTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::LifeSafety->value)->count())->toBe(1);
});

it('hands the customer to the owner on Jev\'s asks_for_person reading before the agent is asked (D-42)', function () {
    customerReadingDanaTexts('Honestly I\'d rather sort this out with Mike directly.');
    fakeClassifier()->answerReading('faq', 0.95, ['asks_for_person' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Sure, what is going on?']);

    runQueuedFrontDeskJobs();

    expect(customerReadingTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::HandoffReply->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::AgentReply->value)->count())->toBe(0)
        ->and(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::AskedForPerson)
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::AskedForPerson);

    FrontDeskAgent::assertNeverPrompted();
});

it('does not hand off on an unsure asks_for_person reading and goes on to the agent', function () {
    customerReadingDanaTexts('Honestly I\'d rather sort this out with Mike directly.');
    fakeClassifier()->answerReading('faq', 0.95, ['asks_for_person' => 0.5]);
    scriptFrontDeskReplies(['reply' => 'Sure, what is going on?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(Message::query()->where('kind', MessageKind::HandoffReply->value)->count())->toBe(0)
        ->and(Conversation::query()->sole()->isPaused())->toBeFalse();

    FrontDeskAgent::assertPrompted(fn () => true);
});

it('hands off a complaint that asks for a person, with a complaint alert beside the asked_for_person one (D-34, D-45)', function () {
    customerReadingDanaTexts('Your last tech was rude. Can I talk to a real person?');
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that.']);

    runQueuedFrontDeskJobs();

    $complaint = customerReadingAlert(AlertReason::Complaint);
    $person = customerReadingAlert(AlertReason::AskedForPerson);

    expect(customerReadingTextsToDana())->toHaveCount(1)
        ->and(Message::query()->where('kind', MessageKind::HandoffReply->value)->count())->toBe(1)
        ->and($complaint->urgent)->toBeTrue()
        ->and($complaint->sent_at)->not->toBeNull()
        ->and($person->urgent)->toBeTrue()
        ->and($person->sent_at)->not->toBeNull()
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::AskedForPerson);
});

it('lets the complaint alert on a handoff wait for opening when complaint is off right_away, while the person alert goes at once', function () {
    customerReadingTakeOffRightAway(['complaint']);
    customerReadingDanaTexts('Your last tech was rude. Can I talk to a real person?', CUSTOMER_READING_AFTER_HOURS);
    fakeClassifier()->answerReading('complaint', 0.95, ['complaint' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that.']);

    runQueuedFrontDeskJobs();

    $complaint = customerReadingAlert(AlertReason::Complaint);
    $person = customerReadingAlert(AlertReason::AskedForPerson);

    expect($complaint->urgent)->toBeFalse()
        ->and(customerReadingDueAtOpening($complaint))->toBe('2026-10-14 08:00')
        ->and($complaint->sent_at)->toBeNull()
        ->and($person->urgent)->toBeTrue()
        ->and($person->sent_at)->not->toBeNull();
});

/**
 * The one draft the run kept for the owner.
 */
function customerReadingTheDraft(): Message
{
    return Message::query()->where('status', MessageStatus::Draft->value)->sole();
}

it('stores the reading on a sent reply\'s verdict, beside the unchanged checks (D-42)', function () {
    customerReadingDanaTexts('My AC is blowing warm air');
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $gate = theAgentMessage()->gate;

    expect($gate['customer_reading']['ran'])->toBeTrue()
        ->and($gate['customer_reading']['failed'])->toBeFalse()
        ->and($gate['customer_reading']['model'])->toBe('fake-jev')
        ->and($gate['customer_reading']['reading']['life_safety'])->toBeFalse()
        ->and(array_keys($gate['checks']))->toBe(['label', 'length', 'code', 'classifier'])
        ->and(array_keys($gate['checks']['classifier']))->toBe(['ran', 'model', 'answers', 'error', 'reading']);
});

it('stores the reading next to the words check on a hold that a label veto decided', function () {
    customerReadingDanaTexts('Can you come Thursday?');
    scriptFrontDeskReplies(['reply' => 'We will be there Thursday.', 'label' => 'promise']);

    runQueuedFrontDeskJobs();

    $draft = customerReadingTheDraft();

    expect($draft->gate['customer_reading']['ran'])->toBeTrue()
        ->and($draft->gate_reason)->toBe('label:promise')
        ->and($draft->gate['checks']['classifier']['ran'])->toBeTrue()
        ->and($draft->gate['checks']['classifier']['reading'])->not->toBeNull();
});

it('stores the reading on a hold written without model words', function () {
    $run = new AgentRun(AgentReply::fromStructured(['reply' => '', 'label' => 'faq_answer', 'wants_person' => false, 'price_services' => ['ac-repair']]), 'anthropic', 'claude', []);
    activateExampleFolder();

    $verdict = app(DecideSendOrDraft::class)->holdWithoutWords($run, 'How much?', CustomerReading::failed('Jev is down.'), 'price:no_sentence');

    expect($verdict->details['customer_reading']['failed'])->toBeTrue()
        ->and($verdict->details['customer_reading']['error'])->toBe('Jev is down.');
});

it('holds the model\'s words as classifier_failed when the reading failed, and says so on the verdict (D-42)', function () {
    customerReadingDanaTexts('My AC is blowing warm air');
    fakeClassifier()->failReadingWith('Jev could not be reached or took too long to answer.');
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $draft = customerReadingTheDraft();

    expect($draft->gate_reason)->toBe('classifier_failed')
        ->and($draft->gate['customer_reading']['failed'])->toBeTrue()
        ->and($draft->gate['customer_reading']['error'])->toBe('Jev could not be reached or took too long to answer.')
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1);
});

it('holds the model\'s words when the reading is unsure about a person, and does not hand off', function () {
    customerReadingDanaTexts('Honestly I\'d rather sort this out with Mike directly.');
    fakeClassifier()->answerReading('faq', 0.95, ['asks_for_person' => 0.5]);
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(customerReadingTheDraft()->gate_reason)->toBe('classifier_unsure:asks_for_person')
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::HandoffReply->value)->count())->toBe(0)
        ->and(Conversation::query()->sole()->isPaused())->toBeFalse();
});

it('holds the main reply as a complaint when the reading found one, and the complaint reply and alert follow (D-31, D-34)', function () {
    customerReadingDanaTexts('Your tech was rude to my wife. Can someone come look at the AC again?');
    fakeClassifier()->answerReading('faq', 0.95, ['complaint' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that. What is the AC doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(customerReadingTheDraft()->gate_reason)->toBe('classifier:complaint')
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1)
        ->and(Message::query()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(0)
        ->and(customerReadingAlert(AlertReason::Complaint))->not->toBeNull();
});

it('holds as a complaint whatever else held the words first, and still records every check (Phase 2 D-07)', function () {
    customerReadingDanaTexts('Your tech was rude to my wife. Can someone come look at the AC again?');
    fakeClassifier()->answerReading('faq', 0.95, ['complaint' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'We will be there Thursday.', 'label' => 'promise']);

    runQueuedFrontDeskJobs();

    $draft = customerReadingTheDraft();

    expect($draft->gate_reason)->toBe('classifier:complaint')
        ->and($draft->gate['checks']['label']['passed'])->toBeFalse()
        ->and(Message::query()->where('kind', MessageKind::ComplaintHolding->value)->count())->toBe(1);
});

it('records the reading\'s price, promise and discount answers and never acts on them (D-42)', function () {
    customerReadingDanaTexts('My AC is blowing warm air');
    fakeClassifier()->answerReading('faq', 0.95, ['price' => 0.9, 'promise' => 0.9, 'discount' => 0.9]);
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $message = theAgentMessage();

    expect($message->status)->not->toBe(MessageStatus::Draft)
        ->and(customerReadingTextsToDana())->toHaveCount(1)
        ->and($message->gate['customer_reading']['answers']['yes_no']['price'])->toBe(0.9)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->count())->toBe(0);
});
