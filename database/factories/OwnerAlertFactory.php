<?php

namespace Database\Factories;

use App\Actions\SendOwnerAlert;
use App\Enums\AlertReason;
use App\Models\Conversation;
use App\Models\OwnerAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerAlert>
 */
class OwnerAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'message_id' => null,
            'user_id' => null,
            'reason' => AlertReason::DraftWaiting,
            'urgent' => false,
            'due_at' => now(),
            'to_phone' => null,
            'body' => fake()->sentence(),
            'sent_at' => null,
            'skipped_at' => null,
            'failed_at' => null,
            'failure' => null,
            'provider_message_id' => null,
            'send_claimed_at' => null,
        ];
    }

    /**
     * Another sender took the alert a moment ago and is texting it now.
     */
    public function claimed(): static
    {
        return $this->state(fn (array $attributes): array => ['send_claimed_at' => now()]);
    }

    /**
     * A sender took the alert and died: the claim is old enough to be taken again.
     */
    public function staleClaim(): static
    {
        return $this->state(fn (array $attributes): array => [
            'send_claimed_at' => now()->subMinutes(SendOwnerAlert::CLAIM_WAIT_MINUTES),
        ]);
    }
}
