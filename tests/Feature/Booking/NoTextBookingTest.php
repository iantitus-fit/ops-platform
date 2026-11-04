<?php

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Tuesday 2026-11-03 at 11:00 in the business zone, the owner with a phone, and Dana on file, with a visit booked for
 * her on Thursday at 10:00 unless asked not to (D-04).
 */
function noTextBookingDana(bool $booked): Contact
{
    test()->travelTo(Carbon::parse('2026-11-03 11:00', 'America/Chicago')->utc());

    User::factory()->withPhone('+12025550199')->create();
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);

    if ($booked) {
        $project = Project::factory()->for($dana)->create();
        Appointment::factory()->for($dana)->for($project)->at(Carbon::parse('2026-11-05 10:00', 'America/Chicago')->utc())->create();
    }

    return $dana;
}

/**
 * Dana texts the given words, the front desk model answers as a scheduling reply, and the queued run happens.
 */
function noTextBookingDanaTexts(string $body): void
{
    static $count = 0;
    $count++;

    if (Message::query()->count() === 0) {
        frontDeskCustomerTexts($body);
    } else {
        test()->travel(1)->minutes();
        test()->post(route('webhooks.text.fake'), fakeTextPayload([
            'MessageSid' => 'SM'.str_pad((string) $count, 32, 'f', STR_PAD_LEFT),
            'Body' => $body,
        ]))->assertOk();
    }

    fakeClassifier()->answerReading('scheduling');
    scriptFrontDeskReplies(['reply' => 'I can ask about that for you.', 'label' => 'scheduling']);

    runQueuedFrontDeskJobs();
}

/**
 * Every appointment as it is stored: id, state, start, end and project.
 *
 * @return list<array<string, mixed>>
 */
function noTextBookingRows(): array
{
    return Appointment::query()->orderBy('id')->get()->map(fn (Appointment $appointment): array => $appointment->only(['id', 'contact_id', 'project_id', 'state', 'starts_at', 'ends_at']))->all();
}

it('never books, moves or cancels a visit from a text (D-04)', function () {
    noTextBookingDana(booked: true);
    $before = noTextBookingRows();

    expect($before)->toHaveCount(1);

    foreach (['Yes Thursday works', 'Please cancel my visit', 'Book me for Friday at 9'] as $body) {
        noTextBookingDanaTexts($body);

        expect(noTextBookingRows())->toEqual($before);
    }
});

it('leaves a contact who only texted with no appointment (D-04)', function () {
    noTextBookingDana(booked: false);

    noTextBookingDanaTexts('Book me for Friday at 9');

    expect(Appointment::query()->count())->toBe(0);
});
