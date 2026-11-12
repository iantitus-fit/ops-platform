<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\BookingSend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingSend>
 */
class BookingSendFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A reminder a day ahead, due a day from now.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'lead_minutes' => 1440,
            'due_at' => now()->addDay(),
            'ignores_window' => false,
            'message_id' => null,
        ];
    }

    /**
     * The confirmation sent at booking: lead 0, due now.
     */
    public function confirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'lead_minutes' => 0,
            'due_at' => now(),
        ]);
    }

    /**
     * Another sender took the send a moment ago and is texting it now.
     */
    public function claimed(): static
    {
        return $this->state(fn (array $attributes) => [
            'send_claimed_at' => now(),
        ]);
    }
}
