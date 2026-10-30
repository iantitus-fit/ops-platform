<?php

namespace Database\Factories;

use App\Enums\ProjectActor;
use App\Models\Contact;
use App\Models\Project;
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
}
