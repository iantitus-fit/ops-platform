<?php

use App\Ai\Agents\FrontDeskAgent;
use App\Enums\AppointmentState;
use App\Enums\PausedReason;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Tuesday 2026-11-03 at 11:00 in the business zone (open), the owner with a phone, and Dana on file. A visit is
 * booked for her on Thursday 2026-11-05 at 10:00 in the business zone unless the test says otherwise (D-34). Both
 * moments are set in UTC, because the app stores and compares UTC.
 */
function changeRequestBookedDana(string $now = '2026-11-03 11:00', ?string $visitStarts = '2026-11-05 10:00', string $state = 'booked'): Contact
{
    test()->travelTo(Carbon::parse($now, 'America/Chicago')->utc());

    User::factory()->withPhone('+12025550199')->create();
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);

    if ($visitStarts !== null) {
        $project = Project::factory()->for($dana)->create();
        $appointment = Appointment::factory()->for($dana)->for($project)->at(Carbon::parse($visitStarts, 'America/Chicago')->utc())->create([
            'service_slug' => 'ac-repair',
            'service_name' => 'AC repair',
        ]);

        if ($state !== 'booked') {
            $appointment->update(['state' => AppointmentState::from($state)]);
        }
    }

    return $dana;
}

/**
 * Dana texts, the front desk model answers with the given reply, and the queued run happens.
 *
 * @param  array<string, mixed>  $reply
 */
function changeRequestDanaTexts(string $body, array $reply, string $intent = 'faq'): void
{
    frontDeskCustomerTexts($body);
    fakeClassifier()->answerReading($intent);
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The alerts of one reason, per stored row.
 *
 * @return list<OwnerAlert>
 */
function changeRequestAlerts(string $reason): array
{
    return OwnerAlert::query()->where('reason', $reason)->orderBy('id')->get()->all();
}

/**
 * The state, start and end of every appointment, to prove no text moved one (D-04).
 *
 * @return list<array{id: int, state: string, starts_at: string, ends_at: string}>
 */
function changeRequestAppointmentRows(): array
{
    return Appointment::query()->orderBy('id')->get()->map(fn (Appointment $appointment): array => [
        'id' => $appointment->id,
        'state' => $appointment->state->value,
        'starts_at' => $appointment->starts_at->toIso8601String(),
        'ends_at' => $appointment->ends_at->toIso8601String(),
    ])->all();
}

it('alerts the owner once when a booked customer asks to move their visit (D-34, D-04)', function () {
    changeRequestBookedDana();
    $before = changeRequestAppointmentRows();

    changeRequestDanaTexts('Can you come Wednesday instead?', ['reply' => 'I can ask about Wednesday for you.', 'label' => 'scheduling']);

    $inbound = Message::query()->where('direction', 'in')->sole();
    $alerts = OwnerAlert::query()->get();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->reason->value)->toBe('change_request')
        ->and($alerts[0]->message_id)->toBe($inbound->id)
        ->and($alerts[0]->body)->toContain('AC repair, Thursday, November 5 at 10:00 AM')
        ->and(textsSentForAgentReplies()[0]['body'] ?? '')->toContain('I can ask about Wednesday for you.')
        ->and(changeRequestAppointmentRows())->toBe($before);
});

it('alerts on the reading\'s scheduling intent too (D-34)', function () {
    changeRequestBookedDana();

    changeRequestDanaTexts('Can you come Wednesday instead?', ['reply' => 'Thanks for letting us know.', 'label' => 'faq_answer'], intent: 'scheduling');

    expect(changeRequestAlerts('change_request'))->toHaveCount(1);
});

it('raises nothing new for a customer with no booked visit ahead (D-34)', function (?string $visitStarts, string $state) {
    changeRequestBookedDana(visitStarts: $visitStarts, state: $state);

    changeRequestDanaTexts('Can you come Wednesday instead?', ['reply' => 'I can ask about Wednesday for you.', 'label' => 'scheduling']);

    expect(changeRequestAlerts('change_request'))->toBe([])
        ->and(OwnerAlert::query()->count())->toBe(0);
})->with([
    'no visit' => [null, 'booked'],
    'cancelled' => ['2026-11-05 10:00', 'cancelled'],
    'done' => ['2026-11-05 10:00', 'done'],
    'started a minute ago' => ['2026-11-03 10:59', 'booked'],
]);

/**
 * Dana texts once more a minute later, the front desk model answers with the given reply, and the queued run happens.
 *
 * @param  array<string, mixed>  $reply
 */
function changeRequestDanaTextsAgain(string $body, array $reply, string $intent = 'faq'): void
{
    static $count = 0;
    $count++;

    test()->travel(1)->minutes();

    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_pad((string) $count, 32, 'e', STR_PAD_LEFT),
        'Body' => $body,
    ]))->assertOk();
    fakeClassifier()->answerReading($intent);
    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * Take one reason off the folder's right_away list. Call it before Dana's text arrives, because the folder is
 * activated when the text does.
 */
function changeRequestTakeOffRightAway(string $reason): void
{
    breakFile('rules/sending.md', fn (string $text): string => str_replace($reason.', ', '', $text));
}

it('swaps draft-waiting for the change request when a held reply answers a booked customer (D-34)', function () {
    changeRequestBookedDana();

    changeRequestDanaTexts('Can you come Wednesday instead?', ['reply' => 'We will move you to Wednesday.', 'label' => 'promise'], intent: 'scheduling');

    $inbound = Message::query()->where('direction', 'in')->sole();
    $alerts = OwnerAlert::query()->get();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->reason->value)->toBe('change_request')
        ->and($alerts[0]->message_id)->toBe($inbound->id)
        ->and(changeRequestAlerts('draft_waiting'))->toBe([])
        ->and(theAgentMessage()->status->value)->toBe('draft')
        ->and(theAgentMessage()->body)->toBe('We will move you to Wednesday.');
});

it('swaps a check-in for the change request and still answers the check-in as today (D-34)', function () {
    changeRequestBookedDana();
    changeRequestDanaTexts('How much for a new unit?', ['reply' => 'Mike will text you a price.', 'label' => 'price']);

    expect(changeRequestAlerts('draft_waiting'))->toHaveCount(1);

    changeRequestDanaTextsAgain('Just checking in, what time is my visit?', ['reply' => 'Mike still has your question.', 'label' => 'other', 'waiting_note' => 'check_in', 'touches_waiting' => true], intent: 'scheduling');

    $newest = Message::query()->where('direction', 'in')->orderByDesc('id')->first();

    expect(changeRequestAlerts('check_in'))->toBe([])
        ->and(changeRequestAlerts('change_request'))->toHaveCount(1)
        ->and(changeRequestAlerts('change_request')[0]->message_id)->toBe($newest->id)
        ->and(changeRequestAlerts('draft_waiting'))->toHaveCount(1)
        ->and(Message::query()->where('kind', 'check_in_reply')->count())->toBe(1);
});

it('gives way to a higher alert and leaves no change request beside it (D-34)', function (string $higher, callable $arrange) {
    changeRequestBookedDana();

    $arrange();

    $reasons = OwnerAlert::query()->pluck('reason')->map->value->unique()->values()->all();

    expect($reasons)->toBe([$higher])
        ->and(changeRequestAlerts('change_request'))->toBe([]);
})->with([
    'a complaint' => ['complaint', function () {
        frontDeskCustomerTexts('You never showed up for the last visit, can you come Wednesday?');
        fakeClassifier()->answerReading('scheduling', 0.95, ['complaint' => 0.9]);
        scriptFrontDeskReplies(['reply' => 'I am sorry about the visit.', 'label' => 'scheduling']);
        runQueuedFrontDeskJobs();
    }],
    'a request for a person' => ['asked_for_person', function () {
        frontDeskCustomerTexts('Can I talk to a person about moving my visit?');
        fakeClassifier()->answerReading('scheduling');
        scriptFrontDeskReplies(['reply' => 'I can help with that myself.', 'label' => 'scheduling', 'wants_person' => true]);
        runQueuedFrontDeskJobs();
    }],
    'a safety message' => ['life_safety', function () {
        frontDeskCustomerTexts('I smell gas, can you come Wednesday?');
        fakeClassifier()->answerReading('scheduling', 0.95, ['life_safety' => 0.9]);
        scriptFrontDeskReplies(['reply' => 'We can come Wednesday.', 'label' => 'scheduling']);
        runQueuedFrontDeskJobs();
    }],
    'a paused thread' => ['paused_message', function () {
        frontDeskCustomerTexts('Can you come Wednesday instead?');
        Conversation::query()->sole()->update(['paused_at' => now(), 'paused_reason' => PausedReason::TakenOver]);
        fakeClassifier()->answerReading('scheduling');
        scriptFrontDeskReplies(['reply' => 'We can come Wednesday.', 'label' => 'scheduling']);
        runQueuedFrontDeskJobs();
    }],
    'a front desk failure' => ['front_desk_failed', function () {
        frontDeskCustomerTexts('Can you come Wednesday instead?');
        fakeClassifier()->answerReading('scheduling');
        FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();
        runQueuedFrontDeskJobs();
    }],
]);

it('raises one change request for a burst of two texts, on the newer one (D-34)', function () {
    changeRequestBookedDana();

    frontDeskCustomerTexts('Can you come Wednesday instead?');
    test()->post(route('webhooks.text.fake'), fakeTextPayload([
        'MessageSid' => 'SM'.str_repeat('b', 32),
        'Body' => 'Or Friday would work too.',
    ]))->assertOk();
    fakeClassifier()->answerReading('scheduling');
    scriptFrontDeskReplies(['reply' => 'I can ask about that for you.', 'label' => 'scheduling']);
    runQueuedFrontDeskJobs();

    $newest = Message::query()->where('direction', 'in')->orderByDesc('id')->first();

    expect(Message::query()->where('direction', 'in')->count())->toBe(2)
        ->and(changeRequestAlerts('change_request'))->toHaveCount(1)
        ->and(changeRequestAlerts('change_request')[0]->message_id)->toBe($newest->id);
});

it('counts a visit one minute ahead as ahead, and one that started a minute ago as not (D-34)', function () {
    changeRequestBookedDana(visitStarts: '2026-11-03 11:01');

    changeRequestDanaTexts('Can you come Wednesday instead?', ['reply' => 'I can ask about Wednesday for you.', 'label' => 'scheduling']);

    expect(changeRequestAlerts('change_request'))->toHaveCount(1);
});

it('goes at once after hours because the example lists it right away, and waits for opening when the folder does not (D-34)', function (bool $listed, bool $urgent, string $dueLocal) {
    if (! $listed) {
        changeRequestBookedDana('2026-11-03 22:00');
        changeRequestTakeOffRightAway('change_request');
    } else {
        changeRequestBookedDana('2026-11-03 22:00');
    }

    changeRequestDanaTexts('Can you come Wednesday instead?', ['reply' => 'I can ask about Wednesday for you.', 'label' => 'scheduling']);

    $alert = changeRequestAlerts('change_request')[0];

    expect($alert->urgent)->toBe($urgent)
        ->and($alert->due_at->setTimezone('America/Chicago')->format('Y-m-d H:i'))->toBe($dueLocal);
})->with([
    'listed right away' => [true, true, '2026-11-03 22:00'],
    'taken off right_away' => [false, false, '2026-11-04 08:00'],
]);
