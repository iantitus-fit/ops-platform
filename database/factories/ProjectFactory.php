<?php

namespace Database\Factories;

use App\Enums\ProjectActor;
use App\Models\Contact;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Opened by hand by default, because the partial unique index allows only one project opened by a new lead for
     * each contact.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'conversation_id' => null,
            'name' => null,
            'opened_by' => ProjectActor::User,
            'opened_by_user_id' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'closed_by' => null,
            'closed_by_user_id' => null,
        ];
    }

    /**
     * A project a new lead opened by itself (the automatic open).
     */
    public function openedByLead(): static
    {
        return $this->state(fn (array $attributes) => [
            'opened_by' => ProjectActor::Lead,
            'opened_by_user_id' => null,
        ]);
    }

    /**
     * A project a signed-in user opened by hand.
     */
    public function openedByUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'opened_by' => ProjectActor::User,
            'opened_by_user_id' => $user->id,
        ]);
    }

    /**
     * A project that has been closed, by the given user when there is one.
     */
    public function closed(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now(),
            'closed_by' => $user === null ? null : ProjectActor::User,
            'closed_by_user_id' => $user?->id,
        ]);
    }
}
