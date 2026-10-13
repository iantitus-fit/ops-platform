<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\IcmVersion;
use App\Models\IntakeAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntakeAnswer>
 */
class IntakeAnswerFactory extends Factory
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
            'icm_version_id' => IcmVersion::factory(),
            'service_slug' => 'ac-repair',
            'question_key' => 'symptom',
            'option' => 'Not cooling',
            'raw_text' => "It's not cooling",
        ];
    }
}
