<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The phone is a reserved fictional 555-01XX line in a real area code, which
     * libphonenumber accepts as valid. Never use 555 as the area code.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $areaCode = fake()->randomElement(['202', '312']);

        return [
            'name' => fake()->name(),
            'phone_e164' => fake()->unique()->numerify('+1'.$areaCode.'55501##'),
            'email' => strtolower(fake()->unique()->safeEmail()),
            'status' => ContactStatus::Lead,
            'lead_source_key' => 'text',
            'acquisition_cost_cents' => null,
            'last_activity_at' => null,
        ];
    }

    /**
     * A contact who only gave an email address.
     */
    public function withoutPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_e164' => null,
        ]);
    }

    /**
     * A contact who has become a customer.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactStatus::Customer,
        ]);
    }

    /**
     * A contact whose first touch came from the given lead source.
     */
    public function fromSource(string $key, ?int $costCents = null): static
    {
        return $this->state(fn (array $attributes) => [
            'lead_source_key' => $key,
            'acquisition_cost_cents' => $costCents,
        ]);
    }
}
