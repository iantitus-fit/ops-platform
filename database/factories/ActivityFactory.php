<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'type' => ActivityType::TextReceived,
            'summary' => 'Text received',
            'lead_source_key' => null,
            'cost_cents' => null,
            'occurred_at' => now(),
        ];
    }
}
