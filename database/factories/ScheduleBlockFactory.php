<?php

namespace Database\Factories;

use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonInterface;
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

    /**
     * A block that starts at the given moment and lasts the given number of minutes.
     *
     * The moment is converted to UTC before it is stored: Eloquent saves a date as its wall time and drops the zone, so a
     * business-zone start would otherwise be stored hours off. The caller's own date is left unchanged.
     */
    public function at(CarbonInterface $start, int $minutes): static
    {
        $startsAt = $start->copy()->utc();

        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($minutes),
        ]);
    }
}
