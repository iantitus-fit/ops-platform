<?php

namespace App\Ai\Agents;

use App\Enums\ReplyLabel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The model that writes the front desk's reply. It proposes words and a label, nothing more.
 *
 * It declares no tools on purpose: with no tool to call, the model has no way to send a
 * text, so what it says is only ever a proposal that code checks before anything goes out.
 */
class FrontDeskAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<Message>  $history  The thread so far, as alternating user and assistant turns.
     */
    public function __construct(
        private readonly string $instructions,
        private readonly array $history,
    ) {}

    /**
     * What the model is told: the fixed front-desk rules, the business files, and the customer's card.
     */
    public function instructions(): Stringable|string
    {
        return $this->instructions;
    }

    /**
     * The earlier turns of the conversation, before the prompt.
     *
     * @return list<Message>
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * The named fields the model answers in, instead of free text.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reply' => $schema->string()->required(),
            'label' => $schema->string()->enum(ReplyLabel::values())->required(),
            'wants_person' => $schema->boolean()->required(),
            'service_slug' => $schema->string()->nullable()->required(),
            'intake_answers' => $schema->array()->items(
                $schema->object([
                    'question_key' => $schema->string()->required(),
                    'option' => $schema->string()->nullable()->required(),
                    'raw_text' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
            'customer_name' => $schema->string()->nullable()->required(),
            'price_services' => $schema->array()->items($schema->string())->required(),
            'asks_discount' => $schema->boolean()->required(),
            'touches_waiting' => $schema->boolean()->nullable()->required(),
            'waiting_note' => $schema->string()->nullable()->required(),
            'easy_reply' => $schema->string()->nullable()->required(),
            'easy_label' => $schema->string()->nullable()->required(),
        ];
    }
}
