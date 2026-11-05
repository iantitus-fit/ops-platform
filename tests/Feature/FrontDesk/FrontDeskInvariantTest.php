<?php

use App\Actions\PauseConversation;
use App\Actions\SendFixedReply;
use App\Ai\Agents\FrontDeskAgent;
use App\Enums\AlertReason;
use App\Enums\ContactStatus;
use App\Enums\DraftResolution;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Jobs\ProcessInbound;
use App\Jobs\RenderCustomerCard;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\SafetyCheckOutage;
use App\Models\User;
use App\SendRule\AsksForPerson;
use App\SendRule\Classifier;
use App\SendRule\ClassifierResult;
use App\SendRule\FakeClassifier;
use App\SendRule\LifeSafety;
use App\SendRule\TriageQuestions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

const INVARIANT_OWNER_PHONE = '+12025550199';

/**
 * The jobs held back while a combination runs: the front desk job until the test runs it, and the customer card render,
 * which rewrites the records folder for every customer each time and would make the whole matrix slower with every
 * combination. The card is not what these invariants are about.
 */
const INVARIANT_HELD_JOBS = [ProcessInbound::class, RenderCustomerCard::class];

const INVARIANT_JEV_FAILURE = 'Jev could not be reached or took too long to answer.';

/**
 * How the 1,440 combinations are split: eight tests of 180, so each runs in a database transaction of its own.
 */
const INVARIANT_SLICE_SIZE = 180;
const INVARIANT_SLICES = 8;

/**
 * The D-46 invariant test: the gap plans each closed one hole, and this file checks that they hold together. A
 * combination is an array of flags, one per dimension D-46 names. Every combination gets its own customer, runs through
 * the real webhook, the real queued job and the real front desk, and is then checked against the invariants below.
 *
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and the example folder live once.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-13 10:00', 'America/Chicago'));

    User::factory()->withPhone(INVARIANT_OWNER_PHONE)->create();

    activateExampleFolder();
});

/**
 * A full set of flags: every dimension is named, so a missing key can never read as a quiet default.
 *
 * `person` is who asks for a person: none, a keyword phrase in the text, or the agent's own wants_person. The flags
 * given in `$on` are switched on.
 *
 * @param  list<string>  $on  waiting, complaint, hazard, price, blank, checkin, paused, jevFail, cap, agentFail
 * @return array<string, mixed>
 */
function invariantFlags(array $on = [], string $person = 'none'): array
{
    return [
        'waiting' => false,
        'complaint' => false,
        'hazard' => false,
        'price' => false,
        'blank' => false,
        'checkin' => false,
        'paused' => false,
        'jevFail' => false,
        'cap' => false,
        'agentFail' => false,
        ...array_fill_keys($on, true),
        'person' => $person,
    ];
}

/**
 * Every combination of the D-46 dimensions that can happen. Three kinds are left out because they cannot: blank words
 * only come with a price ask (the model has nothing else to leave to code), a check-in only comes with a draft waiting,
 * and the agent cannot ask for a person when every model fails.
 *
 * @return list<array<string, mixed>>
 */
function invariantAllCombinations(): array
{
    $dimensions = ['waiting', 'complaint', 'hazard', 'price', 'blank', 'checkin', 'paused', 'jevFail', 'cap', 'agentFail'];
    $combinations = [];

    foreach (['none', 'keyword', 'agent'] as $person) {
        for ($mask = 0; $mask < (1 << count($dimensions)); $mask++) {
            $on = array_values(array_filter($dimensions, fn (string $dimension): bool => ($mask & (1 << array_search($dimension, $dimensions, true))) !== 0));
            $flags = invariantFlags($on, $person);

            $impossible = ($flags['blank'] && ! $flags['price'])
                || ($flags['checkin'] && ! $flags['waiting'])
                || ($flags['person'] === 'agent' && $flags['agentFail']);

            if (! $impossible) {
                $combinations[] = $flags;
            }
        }
    }

    return $combinations;
}

/**
 * The flags that are on, as one line for a failure message.
 *
 * @param  array<string, mixed>  $flags
 */
function invariantLabel(array $flags): string
{
    $on = array_keys(array_filter($flags, fn (mixed $value, string $key): bool => $value === true, ARRAY_FILTER_USE_BOTH));

    return implode(' + ', $on === [] ? ['plain text'] : $on).' | person='.$flags['person'];
}

/**
 * What the customer writes, built from the flags. Every piece is fictional and none is in the keyword lists, except
 * the person phrase, which is there on purpose.
 *
 * @param  array<string, mixed>  $flags
 */
function invariantText(array $flags): string
{
    return implode(' ', array_filter([
        'My AC is blowing warm air.',
        $flags['complaint'] ? 'Your last tech was rude.' : null,
        $flags['hazard'] ? 'My head is pounding and the kids feel dizzy since the furnace came on.' : null,
        $flags['price'] ? 'How much is an AC repair?' : null,
        $flags['checkin'] ? 'Just checking in, have you seen my question?' : null,
        $flags['person'] === 'keyword' ? 'Can I talk to a real person?' : null,
    ]));
}

/**
 * A phone number no earlier combination used.
 */
function invariantNextNumber(): string
{
    static $next = 1000;

    return '+1202555'.$next++;
}

/**
 * One text arriving through the fake webhook, with an id of its own.
 */
function invariantPost(string $number, string $body): void
{
    static $sequence = 0;

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'From' => $number,
        'Body' => $body,
        'MessageSid' => 'SM'.md5($number.'|'.$sequence++),
    ]))->assertOk();
}

/**
 * Run the one queued job the text just queued. The queue fake is replaced first so only that job is waiting; the
 * shared runner would otherwise walk every job of every earlier combination each time.
 */
function invariantPostAndRun(string $number, string $body): void
{
    Queue::fake(INVARIANT_HELD_JOBS);

    invariantPost($number, $body);
    runQueuedFrontDeskJobs();
}

/**
 * The conversation of a customer number, or null before the customer has texted.
 *
 * Two lookups, each on an index: the contact by its unique phone number, then the conversation by contact_id. A single
 * whereHas() query is an EXISTS that PostgreSQL can run by walking every conversation, and a slice adds 180 customers
 * inside one transaction, so that form grew slower with every combination (over half the file's time on PostgreSQL).
 */
function invariantConversation(string $number): ?Conversation
{
    $contactId = Contact::query()->where('phone_e164', $number)->value('id');

    if ($contactId === null) {
        return null;
    }

    return Conversation::query()->where('contact_id', $contactId)->first();
}

/**
 * Script Jev for a combination: the reading and the words check say the same about a complaint and a hazard, the
 * reading alone says the customer asks for a person, and a Jev failure fails both.
 *
 * @param  array<string, mixed>  $flags
 */
function invariantScriptClassifier(array $flags): void
{
    $yesNo = [];
    $intent = 'faq';

    if ($flags['complaint']) {
        $yesNo['complaint'] = 0.9;
        $intent = 'complaint';
    }

    if ($flags['hazard']) {
        $yesNo['life_safety'] = 0.9;
        $intent = 'emergency';
    }

    $classifier = fakeClassifier()->answer($intent, 0.95, $yesNo);
    $classifier->answerReading($intent, 0.95, $flags['person'] === 'none' ? $yesNo : [...$yesNo, 'asks_for_person' => 0.9]);

    if ($flags['jevFail']) {
        $classifier->failWith(INVARIANT_JEV_FAILURE);
    }
}

/**
 * Script the front desk agent for a combination, or make every model fail. The same reply answers a second call, so
 * a run that asks again for a draft covering both questions has something to read.
 *
 * Optional flags: `touches` is the agent's touches_waiting when no check-in sets it.
 *
 * @param  array<string, mixed>  $flags
 */
function invariantScriptAgent(array $flags): void
{
    if ($flags['agentFail']) {
        FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();

        return;
    }

    $reply = [
        'reply' => $flags['blank'] ? '' : 'Thanks, what is the system doing?',
        'label' => $flags['complaint'] ? 'complaint' : 'intake_question',
        'wants_person' => $flags['person'] === 'agent',
        'price_services' => $flags['price'] ? ['ac-repair'] : [],
        'possible_danger' => $flags['hazard'],
        'touches_waiting' => $flags['checkin'] ? true : ($flags['touches'] ?? null),
        'waiting_note' => $flags['checkin'] ? 'check_in' : null,
    ];

    scriptFrontDeskReplies($reply, $reply);
}

/**
 * Dana-style first text that the owner has to answer: a price question held as a draft, with its holding reply sent.
 */
function invariantWaitingDraft(string $number): void
{
    fakeClassifier()->answer('faq')->answerReading('faq');

    Queue::fake(INVARIANT_HELD_JOBS);
    invariantPost($number, 'How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);
    runQueuedFrontDeskJobs();

    $conversation = invariantConversation($number);

    expect($conversation->messages()->where('status', MessageStatus::Draft)->count())->toBe(1)
        ->and($conversation->messages()->where('kind', MessageKind::HoldingReply->value)->count())->toBe(1);
}

/**
 * Run one combination through the real front desk and return what the checks need: the flags, the customer's
 * conversation, where the combination's own messages and alerts begin, and whether an earlier wait was current.
 *
 * Setup comes first and is the real thing wherever it can be: a waiting draft is a real held run with its holding
 * reply sent, a paused conversation is paused through PauseConversation, and the reply cap is six agent texts in the
 * last hour. The text then arrives through the webhook and its queued job runs.
 *
 * @param  array<string, mixed>  $flags
 * @return array{flags: array<string, mixed>, number: string, conversation: ?Conversation, text: string, markerMessageId: int, markerAlertId: int, waitCurrent: bool, waitingDraftId: ?int, complaintRepliesBefore: int}
 */
function invariantRun(array $flags): array
{
    $flags = [...invariantFlags(), ...$flags];
    $number = invariantNextNumber();
    $text = invariantText($flags);

    expect(LifeSafety::hazardIn($text))->toBeNull()
        ->and(AsksForPerson::inText($text))->toBe($flags['person'] === 'keyword');

    fakeClassifier()->answer('faq')->answerReading('faq');

    if ($flags['waiting']) {
        invariantWaitingDraft($number);
    } elseif ($flags['paused'] || $flags['cap']) {
        // Not Contact::factory(): its unique-phone generator runs out of its 200 fictional numbers long before 1,440 combinations.
        Conversation::factory()->for(Contact::query()->forceCreate(['name' => 'Customer '.$number, 'phone_e164' => $number, 'status' => ContactStatus::Lead, 'lead_source_key' => 'text']))->create();
    }

    $before = invariantConversation($number);

    if ($flags['cap']) {
        foreach ([5, 10, 20, 30, 40, 55] as $minutesAgo) {
            Message::factory()->for($before)->outbound()->create([
                'kind' => MessageKind::AgentReply,
                'body' => 'Earlier automated text',
                'created_at' => now()->subMinutes($minutesAgo),
            ]);
        }
    }

    if ($flags['paused']) {
        app(PauseConversation::class)->handle($before, PausedReason::TakenOver);
    }

    $waitCurrent = $before !== null && app(SendFixedReply::class)->holdingAlreadySent($before);
    $waitingDraftId = $before?->messages()->where('status', MessageStatus::Draft)->value('id');
    $complaintRepliesBefore = $before === null ? 0 : invariantComplaintRepliesSent($before);
    $markerMessageId = (int) Message::query()->max('id');
    $markerAlertId = (int) OwnerAlert::query()->max('id');

    SafetyCheckOutage::query()->delete();
    invariantScriptClassifier($flags);
    invariantScriptAgent($flags);
    invariantPostAndRun($number, $text);

    return [
        'flags' => $flags,
        'number' => $number,
        'conversation' => invariantConversation($number),
        'text' => $text,
        'markerMessageId' => $markerMessageId,
        'markerAlertId' => $markerAlertId,
        'waitCurrent' => $waitCurrent,
        'waitingDraftId' => $waitingDraftId,
        'complaintRepliesBefore' => $complaintRepliesBefore,
    ];
}

/**
 * How many texts that carry the owner's complaint reply already went out to the customer before the run under test.
 */
function invariantComplaintRepliesSent(Conversation $conversation): int
{
    return $conversation->messages()
        ->where('direction', MessageDirection::Out)
        ->whereIn('status', [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered])
        ->get()
        ->filter(fn (Message $message): bool => invariantCarriesComplaintReply($message))
        ->count();
}

/**
 * The texts the front desk sent the customer in this combination's run, oldest first.
 *
 * @param  array{conversation: ?Conversation, markerMessageId: int}  $run
 * @return Collection<int, Message>
 */
function invariantSentTexts(array $run): Collection
{
    return Message::query()
        ->where('conversation_id', $run['conversation']->id)
        ->where('id', '>', $run['markerMessageId'])
        ->where('direction', MessageDirection::Out)
        ->whereIn('status', [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered])
        ->orderBy('id')
        ->get();
}

/**
 * How many of the owner's fixed lines a text carries (D-34): one for a holding, handoff, discount or complaint reply, and
 * one for each fixed line that rides in a combined text beside an answer, which meta.parts names.
 */
function invariantFixedLines(Message $message): int
{
    $riding = count(array_intersect($message->meta['parts'] ?? [], ['complaint_line', 'check_in_line', 'added_info_line', 'partial_holding_line']));

    if ($riding > 0) {
        return $riding;
    }

    $fixedKinds = [
        MessageKind::HoldingReply,
        MessageKind::ComplaintHolding,
        MessageKind::DiscountHolding,
        MessageKind::HandoffReply,
        MessageKind::CheckInReply,
        MessageKind::AddedInfoReply,
        MessageKind::PartialHolding,
    ];

    return in_array($message->kind, $fixedKinds, true) ? 1 : 0;
}

/**
 * Whether a text carries one of the owner's price or offer sentences, by what the front desk recorded and by any
 * dollar amount in the words.
 */
function invariantCarriesOwnerSentence(Message $message): bool
{
    $meta = $message->meta ?? [];

    return $message->kind === MessageKind::CodeAnswer
        || in_array('code_sentences', $meta['parts'] ?? [], true)
        || ($meta['price_sentences'] ?? []) !== []
        || ($meta['offers'] ?? []) !== []
        || str_contains($message->body, '$');
}

/**
 * Whether a text carries the owner's complaint reply, alone or leading an answer.
 */
function invariantCarriesComplaintReply(Message $message): bool
{
    return $message->kind === MessageKind::ComplaintHolding || in_array('complaint_line', $message->meta['parts'] ?? [], true);
}

/**
 * Whether a text carries the owner's check-in reply.
 */
function invariantCarriesCheckInReply(Message $message): bool
{
    return $message->kind === MessageKind::CheckInReply || in_array('check_in_line', $message->meta['parts'] ?? [], true);
}

/**
 * Whether an earlier wait stopped being current in this run because a text that is not itself a wait-starter answered
 * the waiting draft (D-21). Only then may a new holding reply follow.
 *
 * @param  array{waitingDraftId: ?int}  $run
 */
function invariantWaitEndedByAnswer(array $run): bool
{
    $draft = $run['waitingDraftId'] === null ? null : Message::query()->find($run['waitingDraftId']);

    if ($draft === null || $draft->resolution !== DraftResolution::Answered) {
        return false;
    }

    $answer = Message::query()->find($draft->meta['answered_by'] ?? 0);

    return $answer !== null && ! in_array($answer->kind, MessageKind::startsWait(), true);
}

/**
 * Every D-46 invariant, and the two ANSR-04 edges, checked on one finished combination. Returns one sentence per breach,
 * each starting with the combination's flags, or an empty list.
 *
 * The detectors a combination leaves are worked out from the flags. The agent is asked only when nothing earlier ends
 * the run: not paused, not capped, no keyword person phrase, and neither a hazard nor an ask for a person that Jev's
 * reading acts on first. A hazard or complaint is seen when Jev is up, or when the agent answers.
 *
 * @param  array<string, mixed>  $flags
 * @param  array<string, mixed>  $run  What invariantRun() returned.
 * @return list<string>
 */
function invariantBreaches(array $flags, array $run): array
{
    $flags = [...invariantFlags(), ...$flags];
    $label = invariantLabel($flags);
    $conversation = $run['conversation'];
    $breaches = [];

    $jevUp = ! $flags['jevFail'];
    $agentAsked = ! $flags['paused'] && ! $flags['cap'] && $flags['person'] !== 'keyword'
        && ! ($jevUp && ($flags['hazard'] || $flags['person'] !== 'none'));
    $agentAnswered = $agentAsked && ! $flags['agentFail'];
    $hazardSeen = $flags['hazard'] && ($jevUp || $agentAnswered);
    $complaintSeen = $flags['complaint'] && ($jevUp || $agentAnswered);

    if ($conversation === null) {
        return ["{$label}: the customer's text made no conversation"];
    }

    $sent = invariantSentTexts($run);
    $alerts = OwnerAlert::query()->where('id', '>', $run['markerAlertId'])->get();
    $alertReasons = $alerts->map(fn (OwnerAlert $alert): string => $alert->reason->value)->all();
    $lines = $sent->filter(fn (Message $message): bool => $message->kind === MessageKind::LifeSafety);

    /**
     * D-46: a hazard any detector can see gets the safety line. A combination with Jev and every model down has no
     * detector, which is the one case without a line; the customer then gets the holding reply and the owner the two
     * outage alerts, checked below (D-50, D-51).
     */
    if ($hazardSeen && $lines->isEmpty()) {
        $breaches[] = "{$label}: a hazard a detector could see got no safety line";
    }

    /**
     * D-46 and D-49: the safety line is never refused for lacking a reading, and no automated text the front desk
     * sends lacks one, so no safety_check_missing alert is ever raised and nothing is left as a failed send.
     */
    if (in_array(AlertReason::SafetyCheckMissing->value, $alertReasons, true)) {
        $breaches[] = "{$label}: raised safety_check_missing";
    }

    if ($hazardSeen && $lines->isNotEmpty() && Message::query()->where('conversation_id', $conversation->id)->where('id', '>', $run['markerMessageId'])->where('status', MessageStatus::Failed)->exists()) {
        $breaches[] = "{$label}: a text was left as a failed send";
    }

    /**
     * D-46 and D-43: a complaint is never answered by an owner price or offer sentence, and a complaint a detector
     * could see reaches the owner as a complaint alert.
     */
    if ($flags['complaint'] && $sent->contains(fn (Message $message): bool => invariantCarriesOwnerSentence($message))) {
        $breaches[] = "{$label}: an owner price or offer sentence went out beside a complaint";
    }

    $complaintAlerts = $alerts->filter(fn (OwnerAlert $alert): bool => $alert->reason === AlertReason::Complaint);

    if ($complaintSeen && $complaintAlerts->isEmpty()) {
        $breaches[] = "{$label}: a complaint a detector could see did not reach the owner as a complaint alert";
    }

    /**
     * D-46: the customer is never left silent, except in a paused or capped conversation, where the owner is alerted
     * instead. Every model down while the customer's wait is current sends one fixed line: the complaint reply for the first
     * complaint of the wait, otherwise the added-info reply (D-29, D-34, D-51).
     */
    if ($sent->isEmpty() && ! $flags['paused'] && ! $flags['cap']) {
        $breaches[] = "{$label}: the customer was left silent";
    }

    if ($sent->isEmpty() && ($flags['paused'] || $flags['cap']) && ! $alerts->contains(fn (OwnerAlert $alert): bool => $alert->conversation_id === $conversation->id)) {
        $breaches[] = "{$label}: nobody was told about a text nobody answered";
    }

    /**
     * D-46 and D-28: at most one live draft.
     */
    $liveDrafts = Message::query()->where('conversation_id', $conversation->id)->where('status', MessageStatus::Draft)->count();

    if ($liveDrafts > 1) {
        $breaches[] = "{$label}: {$liveDrafts} drafts are live";
    }

    /**
     * D-46 and D-34: never two holding replies. One owner-written fixed line per run, and no full holding_reply while
     * the customer's earlier wait is still current.
     */
    $fixedLines = $sent->sum(fn (Message $message): int => invariantFixedLines($message));
    $fullHoldings = $sent->filter(fn (Message $message): bool => $message->kind === MessageKind::HoldingReply)->count();

    if ($fixedLines > 1) {
        $breaches[] = "{$label}: {$fixedLines} owner-written fixed lines went out in one run";
    }

    if ($fullHoldings > 1 || ($fullHoldings === 1 && $run['waitCurrent'] && ! invariantWaitEndedByAnswer($run))) {
        $breaches[] = "{$label}: a full holding reply went out while the customer's earlier wait was current";
    }

    /**
     * D-46 and D-34 (Ian, 2026-10-17, G-02.1-1): never two complaint replies in one wait. The matrix setup never lets the
     * owner answer, so every complaint reply sent before the run belongs to the customer's current wait, and a run may
     * send one only when none went out before it, or when the run's own text ended that wait.
     */
    $complaintReplies = $sent->filter(fn (Message $message): bool => invariantCarriesComplaintReply($message))->count();

    if ($complaintReplies > 1 || ($complaintReplies === 1 && $run['complaintRepliesBefore'] > 0 && ! invariantWaitEndedByAnswer($run))) {
        $breaches[] = "{$label}: two complaint replies went out in one wait";
    }

    /**
     * ANSR-04 ordering (D-34): where a safety line goes out, it is the run's first text to the customer.
     */
    if ($lines->isNotEmpty() && $sent->first()->kind !== MessageKind::LifeSafety) {
        $breaches[] = "{$label}: the safety line was not the first text";
    }

    /**
     * ANSR-04 adjacency (D-34): the first complaint of a wait, carried by a check-in with no danger and no request for a
     * person, gets the complaint reply and not the check-in reply.
     */
    if ($flags['checkin'] && $flags['complaint'] && ! $flags['hazard'] && $flags['person'] === 'none' && $complaintSeen && $agentAnswered && ! $flags['paused'] && ! $flags['cap'] && $run['complaintRepliesBefore'] === 0) {
        if ($sent->contains(fn (Message $message): bool => invariantCarriesCheckInReply($message))) {
            $breaches[] = "{$label}: a complaint got the check-in reply";
        }

        if (! $sent->contains(fn (Message $message): bool => invariantCarriesComplaintReply($message))) {
            $breaches[] = "{$label}: a complaint inside a check-in got no complaint reply";
        }
    }

    /**
     * D-34 and D-51: when the agent was asked, every model failed and Jev saw a complaint, the first complaint of a wait
     * gets the complaint reply and never the added-info line. A later complaint in the same wait gets the added-info
     * reply, which the once-per-wait check above covers.
     */
    if ($agentAsked && $flags['agentFail'] && $complaintSeen && $run['complaintRepliesBefore'] === 0) {
        if (! $sent->contains(fn (Message $message): bool => invariantCarriesComplaintReply($message))) {
            $breaches[] = "{$label}: a complaint got no complaint reply when the front desk could not answer";
        }

        if ($sent->contains(fn (Message $message): bool => $message->kind === MessageKind::AddedInfoReply || in_array('added_info_line', $message->meta['parts'] ?? [], true))) {
            $breaches[] = "{$label}: a complaint got the added-info line when the front desk could not answer";
        }
    }

    /**
     * D-50 and D-51: when Jev is down the owner hears right away, and when the agent was asked and every model failed the
     * owner hears that too.
     */
    if ($flags['jevFail'] && ! in_array(AlertReason::SafetyCheckDown->value, $alertReasons, true)) {
        $breaches[] = "{$label}: Jev was down and the owner got no safety_check_down alert";
    }

    if ($agentAsked && $flags['agentFail'] && ! in_array(AlertReason::FrontDeskFailed->value, $alertReasons, true)) {
        $breaches[] = "{$label}: every model failed and the owner got no front_desk_failed alert";
    }

    return $breaches;
}

/**
 * Run a combination and assert every invariant on it. A failure names the combination's flags.
 *
 * @param  array<string, mixed>  $flags
 * @param  ?array<string, mixed>  $run  A run already made, or null to make one.
 */
function invariantCheck(array $flags, ?array $run = null): void
{
    $flags = [...invariantFlags(), ...$flags];
    $run ??= invariantRun($flags);
    $breaches = invariantBreaches($flags, $run);

    test()->assertSame([], $breaches, invariantLabel($flags).' breaks: '.implode('; ', $breaches));
}

it('gives a hidden hazard the safety line first, beside a price ask with blank words (D-46, ANSR-04)', function () {
    invariantCheck(invariantFlags(['hazard', 'price', 'blank']));
});

it('gives a hidden hazard the safety line in a paused conversation (D-46)', function () {
    invariantCheck(invariantFlags(['hazard', 'paused']));
});

it('keeps the owner price sentence away from a complaint with blank words and a price ask (D-46, D-43)', function () {
    invariantCheck(invariantFlags(['complaint', 'price', 'blank']));
});

it('tells the customer and the owner what is possible when Jev and every model are down (D-46, D-50, D-51)', function () {
    invariantCheck(invariantFlags(['jevFail', 'agentFail', 'hazard']));
});

it('answers a check-in that carries a complaint with the complaint reply, not the check-in reply (D-46, D-34)', function () {
    invariantCheck(invariantFlags(['waiting', 'checkin', 'complaint']));
});

it('answers a complaint that finds every model down during a current wait with the complaint reply, not the added-info line (D-46, D-34, item 6)', function () {
    invariantCheck(invariantFlags(['waiting', 'complaint', 'agentFail']));
});

it('alerts the owner to a complaint when the reply cap is reached (D-46, D-52)', function () {
    invariantCheck(invariantFlags(['cap', 'complaint']));
});

it('sends no second holding reply when a price ask and a keyword person phrase arrive beside a waiting draft (D-46)', function () {
    invariantCheck(invariantFlags(['waiting', 'price'], 'keyword'));
});

it('tells the owner of a complaint the agent labeled when the agent also asks for a person and Jev is down (D-46, D-50)', function () {
    invariantCheck(invariantFlags(['complaint', 'jevFail'], 'agent'));
});

it('tells the owner of a complaint the agent labeled when the agent also flags a danger and asks for a person with Jev down (D-46, D-45)', function () {
    invariantCheck(invariantFlags(['complaint', 'hazard', 'jevFail'], 'agent'));
});

/**
 * A customer whose price question waits with the owner writes again with a question that touches nothing waiting, and
 * the agent is asked twice: its first reply is held for its label, so the run asks once more for one draft that covers
 * both questions (D-35). The two replies are scripted apart, which no combination in the matrix does, because each
 * combination gives both calls the same fields.
 *
 * @param  array<string, mixed>  $first
 * @param  array<string, mixed>  $second
 * @return array{flags: array<string, mixed>, number: string, conversation: ?Conversation, text: string, markerMessageId: int, markerAlertId: int, waitCurrent: bool, waitingDraftId: ?int, complaintRepliesBefore: int}
 */
function invariantCoverBothRun(string $text, array $first, array $second): array
{
    $number = invariantNextNumber();
    invariantWaitingDraft($number);

    $before = invariantConversation($number);
    $run = [
        'flags' => invariantFlags(),
        'number' => $number,
        'text' => $text,
        'markerMessageId' => (int) Message::query()->max('id'),
        'markerAlertId' => (int) OwnerAlert::query()->max('id'),
        'waitCurrent' => app(SendFixedReply::class)->holdingAlreadySent($before),
        'waitingDraftId' => $before->messages()->where('status', MessageStatus::Draft)->value('id'),
        'complaintRepliesBefore' => 0,
    ];

    fakeClassifier()->answer('faq')->answerReading('faq');
    scriptFrontDeskReplies(
        ['reply' => 'Thanks, we will look at it.', 'label' => 'other', 'touches_waiting' => false, ...$first],
        ['reply' => 'Thanks, Mike will answer both.', 'label' => 'intake_question', ...$second],
    );
    invariantPostAndRun($number, $text);

    return [...$run, 'conversation' => invariantConversation($number)];
}

it('sends the safety line when only the second reply of a cover-both run flags a danger (D-46, D-45)', function () {
    $run = invariantCoverBothRun(
        'My head is pounding and the kids feel dizzy since the furnace came on. Also do you work Saturdays?',
        ['possible_danger' => false],
        ['possible_danger' => true],
    );

    $sent = invariantSentTexts($run);

    expect($sent->first()?->kind)->toBe(MessageKind::LifeSafety)
        ->and($sent->filter(fn (Message $message): bool => $message->kind === MessageKind::LifeSafety))->toHaveCount(1)
        ->and($run['conversation']->fresh()->paused_reason)->toBe(PausedReason::LifeSafety)
        ->and(OwnerAlert::query()->where('id', '>', $run['markerAlertId'])->where('reason', AlertReason::LifeSafety->value)->count())->toBe(1)
        ->and(Message::query()->where('conversation_id', $run['conversation']->id)->where('status', MessageStatus::Draft)->count())->toBe(1);
});

it('keeps the owner sentence out of the text when only the second reply of a cover-both run is a complaint (D-46, D-43)', function () {
    $secondIsComplaint = new class extends FakeClassifier
    {
        public function classify(string $customerMessage, string $draftReply, TriageQuestions $questions): ClassifierResult
        {
            $draftReply === 'Thanks, Mike will answer both.'
                ? $this->answer('complaint', 0.95, ['complaint' => 0.9])
                : $this->answer('faq');

            return parent::classify($customerMessage, $draftReply, $questions);
        }
    };
    app()->instance(Classifier::class, $secondIsComplaint);

    $run = invariantCoverBothRun(
        'How much is an AC repair? Also do you work Saturdays?',
        ['price_services' => ['ac-repair']],
        ['price_services' => ['ac-repair']],
    );

    $sent = invariantSentTexts($run);
    $draft = Message::query()->where('conversation_id', $run['conversation']->id)->where('status', MessageStatus::Draft)->sole();

    expect($sent->filter(fn (Message $message): bool => invariantCarriesOwnerSentence($message)))->toHaveCount(0)
        ->and($sent->filter(fn (Message $message): bool => invariantCarriesComplaintReply($message)))->toHaveCount(1)
        ->and(OwnerAlert::query()->where('id', '>', $run['markerAlertId'])->where('reason', AlertReason::Complaint->value)->count())->toBe(1)
        ->and($draft->meta['held_sentences'] ?? [])->toHaveCount(1)
        ->and($draft->body)->toContain('$89');
});

/**
 * Every combination of the D-46 dimensions, each on its own customer, each checked against every invariant. A breach
 * is collected rather than thrown, so one run lists every failing combination with its flags.
 *
 * Jev down with every model down and a hazard the keywords miss is the one case with no safety line: no detector can
 * see the hazard. What is possible there is checked by the same invariants: the customer gets the owner's holding reply
 * (D-51), and the owner gets the safety_check_down and front_desk_failed alerts at once (D-50).
 *
 * A reading that never ran (no live business folder) is outside this matrix by Ian's 2026-10-16 ruling; that path is
 * covered by ReadingRequiredToSendTest and JevDownTest, and here the folder is live, so safety_check_missing is never
 * expected (D-49).
 *
 * The combinations run in slices, each its own test and so its own database transaction. One transaction holding all
 * of them made PostgreSQL slower with every combination (its query plans assume the growing tables are empty), and
 * the run took over an hour; the slices keep each transaction small and the whole file to minutes.
 */
it('holds the D-46 invariants in every combination', function (int $slice) {
    $combinations = invariantAllCombinations();
    $breaches = [];

    foreach (array_chunk($combinations, INVARIANT_SLICE_SIZE)[$slice] as $flags) {
        array_push($breaches, ...invariantBreaches($flags, invariantRun($flags)));
    }

    expect($combinations)->toHaveCount(INVARIANT_SLICE_SIZE * INVARIANT_SLICES)
        ->and($breaches)->toBe([]);
})->with(array_map(fn (int $slice): array => [$slice], range(0, INVARIANT_SLICES - 1)));
