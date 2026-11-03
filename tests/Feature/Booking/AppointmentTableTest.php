<?php

use App\Enums\AppointmentActor;
use App\Enums\AppointmentKind;
use App\Enums\AppointmentState;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Project;
use App\Models\ScheduleBlock;
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
