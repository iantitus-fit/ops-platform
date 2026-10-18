<?php

namespace Database\Factories;

use App\Models\SafetyCheckOutage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SafetyCheckOutage>
 */
class SafetyCheckOutageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'started_at' => now(),
            'ended_at' => null,
            'down_alerted_at' => null,
            'up_alerted_at' => null,
            'last_error' => 'Jev could not be reached or took too long to answer.',
        ];
    }

    /**
     * An outage that is over, ending the given number of minutes after it began.
     */
    public function ended(int $afterMinutes = 30): static
    {
        return $this->state(fn (array $attributes): array => [
            'ended_at' => Carbon::parse($attributes['started_at'])->addMinutes($afterMinutes),
        ]);
    }
}
