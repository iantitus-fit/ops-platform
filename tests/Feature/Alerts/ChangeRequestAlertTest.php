<?php

use App\Enums\AppointmentState;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Tuesday 2026-11-03 at 11:00 in the business zone (open), the owner with a phone, and Dana on file. A visit is
 * booked for her on Thursday 2026-11-05 at 10:00 in the business zone unless the test says otherwise (D-34).
 */
function changeRequestBookedDana(string $now = '2026-11-03 11:00', ?string $visitStarts = '2026-11-05 10:00', string $state = 'booked'): Contact
{
    test()->travelTo(Carbon::parse($now, 'America/Chicago'));

    User::factory()->withPhone('+12025550199')->create();
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);

    if ($visitStarts !== null) {
        $project = Project::factory()->for($dana)->create();
        $appointment = Appointment::factory()->for($dana)->for($project)->at(Carbon::parse($visitStarts, 'America/Chicago'))->create([
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
