<?php

namespace Database\Factories;

use App\Models\WalkRecording;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalkRecording>
 */
class WalkRecordingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * An empty recording of a walk, made against a fingerprint of its own.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'walk_path' => 'tests/'.fake()->unique()->slug(2).'.md',
            'script_hash' => hash('sha256', fake()->unique()->uuid()),
            'variant' => WalkRecording::AS_BUILT,
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'payload' => ['version' => 1, 'turns' => []],
            'recorded_at' => now(),
        ];
    }

    /**
     * A recording made against an older prompt or older triage questions than the candidate's.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'fingerprint' => hash('sha256', 'an older front desk prompt '.fake()->unique()->uuid()),
        ]);
    }

    /**
     * A recording of a measured wording, never the one the gate replays.
     */
    public function variant(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'variant' => $name,
        ]);
    }
}
