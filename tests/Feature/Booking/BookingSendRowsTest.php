<?php

use App\Actions\BookAppointment;
use App\Actions\OpenProject;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * A moment on the example business's wall clock (America/Chicago).
 */
function sendRowsChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Tuesday 2026-11-03 11:00 in the shop's zone, and Dana Whitfield, who texted and
 * has one open job.
 *
 * @return array{0: Contact, 1: int}
 */
function sendRowsSetUp(): array
{
    activateExampleFolder();
    test()->travelTo(sendRowsChicago('2026-11-03 11:00'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = $contact->conversations()->create();
    Message::factory()->create(['conversation_id' => $conversation->id]);
    $job = app(OpenProject::class)->byHand($contact, test()->owner);

    return [$contact, $job->id];
}

it('writes the confirmation and the reminder for a hand booking', function () {
    [$contact, $jobId] = sendRowsSetUp();

    $appointment = app(BookAppointment::class)->byHand(
        $contact,
        $this->owner,
        'ac-repair',
        sendRowsChicago('2026-11-05 10:00'),
        $jobId,
        null,
    );

    $sends = $appointment->sends()->orderBy('lead_minutes')->get();

    expect($sends)->toHaveCount(2)
        ->and($sends[0]->lead_minutes)->toBe(0)
        ->and($sends[0]->due_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-03 17:00')
        ->and($sends[0]->ignores_window)->toBeFalse()
        ->and($sends[1]->lead_minutes)->toBe(1440)
        ->and($sends[1]->due_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-04 16:00')
        ->and($sends[1]->ignores_window)->toBeFalse()
        ->and(BookingSend::query()->count())->toBe(2);
});
