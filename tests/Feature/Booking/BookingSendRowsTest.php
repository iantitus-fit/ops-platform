<?php

use App\Actions\BookAppointment;
use App\Actions\MintBookingLink;
use App\Actions\OpenProject;
use App\Models\Appointment;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

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
 * has one open job. $changeFolder may change the folder before it goes live.
 *
 * @return array{0: Contact, 1: int}
 */
function sendRowsSetUp(?Closure $changeFolder = null): array
{
    if ($changeFolder !== null) {
        $changeFolder();
    }

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

it('writes a page booking\'s rows', function () {
    [$contact, $jobId] = sendRowsSetUp();
    test()->travelTo(sendRowsChicago('2026-11-03 22:00'));

    $conversation = $contact->conversations()->sole();
    [$link] = app(MintBookingLink::class)->handle($conversation, 'ac-repair', CarbonImmutable::now());
    $message = Message::factory()->for($conversation)->outbound()->create([
        'body' => 'Here is the link to book.',
        'meta' => ['booking_link_id' => $link->id],
    ]);
    app(MintBookingLink::class)->attachMessage($link, $message);

    $appointment = app(BookAppointment::class)->fromLink($link->fresh(), sendRowsChicago('2026-11-05 10:00'), CarbonImmutable::now());

    $sends = $appointment->sends()->orderBy('lead_minutes')->get();

    expect($appointment->project_id)->toBe($jobId)
        ->and($sends)->toHaveCount(2)
        ->and($sends[0]->lead_minutes)->toBe(0)
        ->and($sends[0]->due_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-04 04:00')
        ->and($sends[0]->ignores_window)->toBeTrue()
        ->and($sends[1]->lead_minutes)->toBe(1440)
        ->and($sends[1]->due_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-04 16:00')
        ->and($sends[1]->ignores_window)->toBeFalse();
});

it('writes no rows with no booking file (D-03)', function () {
    [$contact, $jobId] = sendRowsSetUp(fn () => File::delete(icmPath('rooms/schedule_and_booking/booking.md')));

    $appointment = app(BookAppointment::class)->byHand(
        $contact,
        $this->owner,
        'ac-repair',
        sendRowsChicago('2026-11-05 10:00'),
        $jobId,
        null,
    );

    expect($appointment->exists)->toBeTrue()
        ->and(Appointment::query()->count())->toBe(1)
        ->and(BookingSend::query()->count())->toBe(0);
});

it('writes rows in the booking\'s transaction, so a refused booking leaves none', function () {
    [$contact, $jobId] = sendRowsSetUp();
    Appointment::factory()->at(sendRowsChicago('2026-11-05 10:00'), 90)->create();

    expect(fn () => app(BookAppointment::class)->byHand($contact, $this->owner, 'ac-repair', sendRowsChicago('2026-11-05 10:30'), $jobId, null))
        ->toThrow(BookingRefused::class, 'That time is taken by another booking or blocked time. Pick another time.')
        ->and(Appointment::query()->count())->toBe(1)
        ->and(BookingSend::query()->count())->toBe(0);
});

it('keeps the confirmation of a hand booking made after hours until the window opens', function () {
    [$contact, $jobId] = sendRowsSetUp();
    test()->travelTo(sendRowsChicago('2026-11-03 22:00'));

    $appointment = app(BookAppointment::class)->byHand($contact, $this->owner, 'ac-repair', sendRowsChicago('2026-11-05 10:00'), $jobId, null);

    $confirmation = $appointment->sends()->where('lead_minutes', 0)->sole();

    expect($confirmation->due_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-04 14:00')
        ->and($confirmation->ignores_window)->toBeFalse();
});
