<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The phone is a fictional 555-02XX line in a real area code, which libphonenumber
     * accepts as valid. Never use 555 as the area code. The 555-01XX block is left free for
     * the numbers tests write by hand, so a generated phone can never collide with one.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $areaCode = fake()->randomElement(['202', '312']);

        return [
            'name' => fake()->name(),
            'phone_e164' => fake()->unique()->numerify('+1'.$areaCode.'55502##'),
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
     * A contact who texted STOP and must not be texted.
     */
    public function optedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'sms_opted_out_at' => now(),
        ]);
    }

    /**
     * A contact the owner has switched automatic texts on for (D-20).
     */
    public function agreedToAutomaticTexts(): static
    {
        return $this->state(fn (array $attributes) => [
            'automatic_texts_on_at' => now(),
            'automatic_texts_on_by_user_id' => User::factory(),
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
