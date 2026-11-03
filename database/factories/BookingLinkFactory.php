<?php

namespace Database\Factories;

use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingLink>
 */
class BookingLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Only the hash of a random token is stored, as in production (D-12); the plain token is never kept. The
     * conversation belongs to the same contact.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'conversation_id' => fn (array $attributes): int => Conversation::factory()->create(['contact_id' => $attributes['contact_id']])->id,
            'service_slug' => 'ac-repair',
            'message_id' => null,
            'token_hash' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addDays(7),
            'superseded_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * A link whose time has run out.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * A link replaced by a newer one.
     */
    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => [
            'superseded_at' => now(),
        ]);
    }

    /**
     * A link the owner or the platform took back.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
