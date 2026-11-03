<?php

namespace Database\Factories;

use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleBlock>
 */
class ScheduleBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A block holds no contact, project or service; the database refuses one that does. It is a two-hour span two days
     * ahead, set by a signed-in user.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(2)->setTime(16, 0);

        return [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'state' => AppointmentState::Booked,
            'booked_by' => AppointmentActor::User,
            'booked_by_user_id' => User::factory(),
            'booked_at' => now(),
            'note' => 'Truck in the shop',
        ];
    }
}
