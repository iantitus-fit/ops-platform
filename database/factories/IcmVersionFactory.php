<?php

namespace Database\Factories;

use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Models\IcmVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IcmVersion>
 */
class IcmVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'git_sha' => null,
            'compiled' => ['files' => [], 'index' => []],
            'errors' => null,
            'notices' => null,
            'file_count' => 0,
            'status' => VersionStatus::Superseded,
            'activated_at' => null,
            'last_checked_at' => null,
            'last_result' => null,
        ];
    }

    /**
     * A version that is currently live.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VersionStatus::Active,
            'activated_at' => now(),
            'last_checked_at' => now(),
            'last_result' => SyncOutcome::Activated,
        ]);
    }

    /**
     * A version that failed validation and never went live.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VersionStatus::Rejected,
            'compiled' => null,
            'errors' => ['business/profile.md: name is missing, and it is required'],
            'last_result' => SyncOutcome::Rejected,
        ]);
    }

    /**
     * A version that was live and has been replaced.
     */
    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VersionStatus::Superseded,
            'activated_at' => now()->subDay(),
        ]);
    }
}
