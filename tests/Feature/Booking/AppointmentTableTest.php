<?php

use App\Enums\AppointmentActor;
use App\Enums\AppointmentKind;
use App\Enums\AppointmentState;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\Project;
use App\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('stores an appointment for a job and reads its span back in UTC', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->for($contact)->create();

    Appointment::factory()->for($contact)->for($project)->create([
        'starts_at' => Carbon::parse('2026-11-03 16:00:00', 'UTC'),
        'service_slug' => 'ac-repair',
        'service_name' => 'AC repair',
    ]);

    $appointment = Appointment::sole();

    expect($appointment->kind)->toBe(AppointmentKind::Appointment)
        ->and($appointment->state)->toBe(AppointmentState::Booked)
        ->and($appointment->starts_at->utc()->toIso8601String())->toBe('2026-11-03T16:00:00+00:00')
        ->and($appointment->ends_at->utc()->toIso8601String())->toBe('2026-11-03T17:30:00+00:00')
        ->and($appointment->booked_by)->toBe(AppointmentActor::User);
});

it('keeps each kind to its own model', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->for($contact)->create();
    Appointment::factory()->for($contact)->for($project)->create();
    ScheduleBlock::factory()->create(['note' => 'Truck in the shop']);

    expect(Appointment::count())->toBe(1)
        ->and(ScheduleBlock::count())->toBe(1)
        ->and(DB::table('appointments')->count())->toBe(2)
        ->and(ScheduleBlock::sole()->kind)->toBe(AppointmentKind::Block)
        ->and($contact->appointments()->count())->toBe(1);
});

it('lists zero, one and two appointments per job', function (int $count) {
    $contact = Contact::factory()->create();
    $project = Project::factory()->for($contact)->create();
    Appointment::factory()->count($count)->for($contact)->for($project)->create();

    expect($project->appointments()->count())->toBe($count)
        ->and($project->contact->appointments()->count())->toBe($count);
})->with([0, 1, 2]);

it('finds a contact\'s booked appointments still ahead', function () {
    $this->travelTo(Carbon::parse('2026-11-03 12:00:00', 'UTC'));

    $contact = Contact::factory()->create();
    $project = Project::factory()->for($contact)->create();

    $ahead = Appointment::factory()->for($contact)->for($project)->at(Carbon::parse('2026-11-03 16:00:00', 'UTC'))->create();
    Appointment::factory()->for($contact)->for($project)->at(Carbon::parse('2026-11-02 16:00:00', 'UTC'))->create();
    Appointment::factory()->for($contact)->for($project)->at(Carbon::parse('2026-11-04 16:00:00', 'UTC'))->cancelled()->create();

    expect($contact->appointments()->booked()->ahead()->pluck('id')->all())->toBe([$ahead->id]);
});

it('keeps the token hash out of arrays', function () {
    $link = BookingLink::factory()->create();

    expect($link->toArray())->not->toHaveKey('token_hash')
        ->and(strlen($link->token_hash))->toBe(64);
});

/**
 * One valid row for the appointments table, built by hand so each case can change one thing and nothing else.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function appointmentTableRow(string $kind, array $changes = []): array
{
    $appointment = [
        'kind' => 'appointment',
        'contact_id' => Contact::factory()->create()->id,
        'project_id' => null,
        'booking_link_id' => null,
        'service_slug' => 'ac-repair',
        'service_name' => 'AC repair',
        'starts_at' => '2026-11-03 16:00:00',
        'ends_at' => '2026-11-03 17:30:00',
        'state' => 'booked',
        'booked_by' => 'user',
        'booked_at' => '2026-11-01 12:00:00',
    ];
    $appointment['project_id'] = Project::factory()->create(['contact_id' => $appointment['contact_id']])->id;

    $block = [
        'kind' => 'block',
        'contact_id' => null,
        'project_id' => null,
        'booking_link_id' => null,
        'service_slug' => null,
        'service_name' => null,
        'starts_at' => '2026-11-03 16:00:00',
        'ends_at' => '2026-11-03 18:00:00',
        'state' => 'booked',
        'booked_by' => 'user',
        'booked_at' => '2026-11-01 12:00:00',
    ];

    return array_merge($kind === 'block' ? $block : $appointment, $changes);
}

it('refuses a row of the wrong shape', function (string $kind, Closure $changes) {
    expect(fn () => DB::table('appointments')->insert(appointmentTableRow($kind, $changes())))
        ->toThrow(QueryException::class);
})->with([
    'an appointment without a project' => ['appointment', fn () => ['project_id' => null]],
    'an appointment without a contact' => ['appointment', fn () => ['contact_id' => null]],
    'an appointment without a service name' => ['appointment', fn () => ['service_name' => null]],
    'a block with a contact' => ['block', fn () => ['contact_id' => Contact::factory()->create()->id]],
    'a block with a service slug' => ['block', fn () => ['service_slug' => 'ac-repair']],
    'a block with a booking link' => ['block', fn () => ['booking_link_id' => BookingLink::factory()->create()->id]],
    'an unknown kind' => ['appointment', fn () => ['kind' => 'visit']],
    'an unknown state' => ['appointment', fn () => ['state' => 'pending']],
    'a block in state done' => ['block', fn () => ['state' => 'done']],
    'an end equal to the start' => ['appointment', fn () => ['ends_at' => '2026-11-03 16:00:00']],
]);

it('accepts a row of the right shape', function () {
    DB::table('appointments')->insert(appointmentTableRow('appointment'));
    DB::table('appointments')->insert(appointmentTableRow('block'));

    expect(Appointment::count())->toBe(1)
        ->and(ScheduleBlock::count())->toBe(1);
});

it('refuses a second appointment through the same booking link', function () {
    $link = BookingLink::factory()->create();

    DB::table('appointments')->insert(appointmentTableRow('appointment', ['booking_link_id' => $link->id]));

    expect(fn () => DB::table('appointments')->insert(appointmentTableRow('appointment', ['booking_link_id' => $link->id])))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('keeps one send per appointment and lead time', function () {
    $appointment = Appointment::factory()->create();

    BookingSend::factory()->for($appointment)->create(['lead_minutes' => 1440]);

    expect(fn () => BookingSend::factory()->for($appointment)->create(['lead_minutes' => 1440]))
        ->toThrow(UniqueConstraintViolationException::class);

    BookingSend::factory()->for($appointment)->confirmation()->create();

    expect($appointment->sends()->count())->toBe(2);
});

it('starts every contact with automatic texts off', function () {
    $contact = Contact::factory()->create()->refresh();
    $agreed = Contact::factory()->agreedToAutomaticTexts()->create()->refresh();

    expect($contact->automatic_texts_on_at)->toBeNull()
        ->and($contact->automatic_texts_on_by_user_id)->toBeNull()
        ->and($agreed->automatic_texts_on_at)->not->toBeNull()
        ->and($agreed->automaticTextsTurnedOnBy)->toBeInstanceOf(User::class);
});
