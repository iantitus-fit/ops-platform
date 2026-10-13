<?php

namespace Database\Factories;

use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
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
            'channel' => MessageChannel::Sms,
            'direction' => MessageDirection::In,
            'author' => MessageAuthor::Customer,
            'body' => fake()->sentence(),
            'status' => MessageStatus::Received,
            'provider' => null,
            'provider_message_id' => null,
            'meta' => null,
        ];
    }

    /**
     * A message the front desk sent to the customer.
     */
    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Agent,
            'status' => MessageStatus::Sent,
        ]);
    }

    /**
     * A reply the front desk wrote and the send rule kept for the owner. A conversation has at most one.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Agent,
            'status' => MessageStatus::Draft,
            'kind' => MessageKind::AgentReply,
            'gate_outcome' => GateOutcome::Hold,
            'gate_reason' => 'code:price',
            'gate' => [
                'version' => 1,
                'reason' => ['code' => 'code:price', 'plain' => 'Held: asks about price'],
            ],
        ]);
    }

    /**
     * A draft that never went out because a newer one replaced it.
     */
    public function withdrawn(): static
    {
        return $this->draft()->state(fn (array $attributes) => [
            'status' => MessageStatus::Withdrawn,
            'resolution' => DraftResolution::Replaced,
            'resolved_at' => now(),
        ]);
    }

    /**
     * A message the owner typed and sent to the customer.
     */
    public function fromOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Owner,
            'status' => MessageStatus::Sent,
        ]);
    }

    /**
     * A message that arrived through the website form.
     */
    public function web(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => MessageChannel::Web,
        ]);
    }
}
