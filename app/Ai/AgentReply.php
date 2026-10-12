<?php

namespace App\Ai;

use App\Enums\ReplyLabel;
use UnexpectedValueException;

/**
 * What the model proposed, checked field by field. The model's answer is untrusted input: anything
 * that does not fit throws, and the provider chain moves on to the next entry.
 */
readonly class AgentReply
{
    /**
     * @param  list<array{question_key: string, option: ?string, raw_text: string}>  $intakeAnswers
     */
    public function __construct(
        public string $reply,
        public ReplyLabel $label,
        public bool $wantsPerson,
        public ?string $serviceSlug,
        public array $intakeAnswers,
        public ?string $customerName,
    ) {}

    /**
     * Build a reply from the structured answer, or throw when it is empty or malformed.
     *
     * The reply's length is not checked here: the hard limit belongs to the send rule.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws UnexpectedValueException
     */
    public static function fromStructured(array $data): self
    {
        $reply = $data['reply'] ?? null;

        if (! is_string($reply) || trim($reply) === '') {
            throw new UnexpectedValueException('The answer has no reply text.');
        }

        $label = is_string($data['label'] ?? null) ? ReplyLabel::tryFrom($data['label']) : null;

        if ($label === null) {
            throw new UnexpectedValueException('The answer has no label from the fixed vocabulary.');
        }

        if (! is_bool($data['wants_person'] ?? null)) {
            throw new UnexpectedValueException('The answer has no true or false for wants_person.');
        }

        return new self(
            reply: trim($reply),
            label: $label,
            wantsPerson: $data['wants_person'],
            serviceSlug: self::optionalText($data, 'service_slug'),
            intakeAnswers: self::intakeAnswers($data['intake_answers'] ?? []),
            customerName: self::optionalText($data, 'customer_name'),
        );
    }

    /**
     * @return array{reply: string, label: string, wants_person: bool, service_slug: ?string, intake_answers: list<array{question_key: string, option: ?string, raw_text: string}>, customer_name: ?string}
     */
    public function toArray(): array
    {
        return [
            'reply' => $this->reply,
            'label' => $this->label->value,
            'wants_person' => $this->wantsPerson,
            'service_slug' => $this->serviceSlug,
            'intake_answers' => $this->intakeAnswers,
            'customer_name' => $this->customerName,
        ];
    }

    /**
     * A text field that may be null or empty, trimmed; any other kind of value is malformed.
     *
     * @param  array<string, mixed>  $data
     */
    private static function optionalText(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new UnexpectedValueException("The answer's {$key} is not text.");
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Keep each intake answer that has a text question_key and raw_text, and an option that is text or null.
     *
     * @return list<array{question_key: string, option: ?string, raw_text: string}>
     */
    private static function intakeAnswers(mixed $answers): array
    {
        if (! is_array($answers)) {
            throw new UnexpectedValueException('The answer has intake_answers that is not a list.');
        }

        $kept = [];

        foreach ($answers as $answer) {
            if (! is_array($answer)
                || ! is_string($answer['question_key'] ?? null)
                || ! is_string($answer['raw_text'] ?? null)
                || ! (is_string($answer['option'] ?? null) || ($answer['option'] ?? null) === null)) {
                continue;
            }

            $kept[] = [
                'question_key' => $answer['question_key'],
                'option' => $answer['option'] ?? null,
                'raw_text' => $answer['raw_text'],
            ];
        }

        return $kept;
    }
}
