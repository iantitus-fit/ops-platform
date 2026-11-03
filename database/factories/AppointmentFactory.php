<?php

namespace Database\Factories;

use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The project is made for the same contact, so contact_id and project_id agree. It is a booked ac-repair visit two
     * days ahead at 16:00 UTC, ninety minutes long, booked by a signed-in user. The kind is set by the model itself.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'project_id' => fn (array $attributes): int => Project::factory()->create(['contact_id' => $attributes['contact_id']])->id,
            'booking_link_id' => null,
            'service_slug' => 'ac-repair',
            'service_name' => 'AC repair',
            'starts_at' => now()->addDays(2)->setTime(16, 0),
            'ends_at' => fn (array $attributes): CarbonInterface => Carbon::parse($attributes['starts_at'])->addMinutes(90),
            'state' => AppointmentState::Booked,
            'booked_by' => AppointmentActor::User,
            'booked_by_user_id' => User::factory(),
            'booked_at' => now(),
        ];
    }

    /**
     * An appointment that was cancelled by a user.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => AppointmentState::Cancelled,
            'cancelled_by' => AppointmentActor::User,
            'cancelled_by_user_id' => User::factory(),
            'cancelled_at' => now(),
        ]);
    }

    /**
     * An appointment a user marked done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => AppointmentState::Done,
            'done_by_user_id' => User::factory(),
            'done_at' => now(),
        ]);
    }

    /**
     * An appointment the customer booked themselves through a link.
     */
    public function byCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'booked_by' => AppointmentActor::Customer,
            'booked_by_user_id' => null,
        ]);
    }

    /**
     * An appointment that starts at the given moment and lasts the given number of minutes.
     */
    public function at(CarbonInterface $start, int $minutes = 90): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($minutes),
        ]);
    }
}
