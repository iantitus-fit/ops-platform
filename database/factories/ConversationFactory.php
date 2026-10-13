<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Enums\PausedReason;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
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
            'status' => ConversationStatus::Open,
            'last_message_at' => null,
            'closed_at' => null,
        ];
    }

    /**
     * A thread the owner has closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    /**
     * A thread the assistant has stepped back from.
     */
    public function paused(PausedReason $reason = PausedReason::TakenOver): static
    {
        return $this->state(fn (array $attributes) => [
            'paused_at' => now(),
            'paused_reason' => $reason,
        ]);
    }
}
