<?php

namespace App\Actions;

use App\Ai\AgentReply;
use App\Enums\ActivityType;
use App\Events\CustomerRecordChanged;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\IntakeAnswer;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stores the intake answers the agent found in a customer's texts.
 *
 * The model's answer is untrusted, so each one is checked against the live service file: a question key the service
 * does not ask is dropped, an option that is not one of the question's options is kept as null with the customer's
 * own words, and a service the folder does not have stores nothing. Each stored answer is a new row, tied to the
 * folder version in force, and adds an intake_answered entry to the customer's timeline. A correction is simply a
 * later row; the card shows the latest per question and the earlier rows stay as history (D-33, D-34).
 */
class RecordIntakeAnswers
{
    public function __construct(private readonly ActiveConfig $activeConfig) {}

    /**
     * @param  Message  $source  The customer text the answers came from.
     * @return Collection<int, IntakeAnswer> The rows stored, in the order the agent gave them.
     */
    public function handle(Conversation $conversation, AgentReply $reply, Message $source): Collection
    {
        $slug = $reply->serviceSlug;
        $service = $slug === null ? null : $this->activeConfig->service($slug);

        if ($slug === null || $service === null || $reply->intakeAnswers === []) {
            return new Collection;
        }

        $questions = $this->questionsByKey($service);

        $stored = DB::transaction(function () use ($conversation, $reply, $source, $slug, $questions): Collection {
            $stored = new Collection;
            $versionId = $this->activeConfig->versionId();

            foreach ($reply->intakeAnswers as $answer) {
                $question = $questions[$answer['question_key']] ?? null;

                if ($question === null) {
                    continue;
                }

                $option = $this->matchedOption($question['options'], $answer['option']);

                $row = $conversation->intakeAnswers()->create([
                    'message_id' => $source->id,
                    'icm_version_id' => $versionId,
                    'service_slug' => $slug,
                    'question_key' => $answer['question_key'],
                    'option' => $option,
                    'raw_text' => $answer['raw_text'],
                ]);

                $conversation->contact->activities()->create([
                    'type' => ActivityType::IntakeAnswered,
                    'summary' => 'Intake answer: '.$question['ask'].' '.($option ?? 'not matched'),
                    'subject_type' => $row->getMorphClass(),
                    'subject_id' => $row->id,
                    'occurred_at' => now(),
                ]);

                $stored->push($row);
            }

            return $stored;
        });

        if ($stored->isNotEmpty()) {
            CustomerRecordChanged::dispatch($conversation->contact_id);
        }

        return $stored;
    }

    /**
     * The customer's current answer to each of a service's intake questions: the newest row per question, keyed by
     * question_key. A correction is a new row, so the later row wins and the earlier ones stay as history (Phase 2
     * D-34). Rows are read in id order, the same rule the customer card uses.
     *
     * @return Collection<string, IntakeAnswer>
     */
    public function currentFor(Conversation $conversation, string $slug): Collection
    {
        return $conversation->intakeAnswers()
            ->where('service_slug', $slug)
            ->reorder('id')
            ->get()
            ->keyBy('question_key');
    }

    /**
     * The service's intake questions by key.
     *
     * @param  array<string, mixed>  $service
     * @return array<string, array{ask: string, options: list<string>}>
     */
    private function questionsByKey(array $service): array
    {
        $questions = [];

        foreach (is_array($service['intake'] ?? null) ? $service['intake'] : [] as $question) {
            if (! is_array($question) || ! is_string($question['key'] ?? null)) {
                continue;
            }

            $questions[$question['key']] = [
                'ask' => (string) ($question['ask'] ?? $question['key']),
                'options' => array_values(array_map('strval', is_array($question['options'] ?? null) ? $question['options'] : [])),
            ];
        }

        return $questions;
    }

    /**
     * The question's own spelling of the option the agent gave, or null when none of its options matches.
     *
     * @param  list<string>  $options
     */
    private function matchedOption(array $options, ?string $given): ?string
    {
        $wanted = mb_strtolower(trim((string) $given));

        if ($wanted === '') {
            return null;
        }

        foreach ($options as $option) {
            if (mb_strtolower(trim($option)) === $wanted) {
                return $option;
            }
        }

        return null;
    }
}
