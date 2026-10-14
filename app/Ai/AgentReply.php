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
     * @param  list<string>  $priceServices  Slugs of the services whose price the customer asked about.
     * @param  ?bool  $touchesWaiting  Whether the text touches the question already with the owner; null when unknown.
     * @param  ?string  $waitingNote  check_in or added_info; null when unknown.
     */
    public function __construct(
        public string $reply,
        public ReplyLabel $label,
        public bool $wantsPerson,
        public ?string $serviceSlug,
        public array $intakeAnswers,
        public ?string $customerName,
        public array $priceServices = [],
        public bool $asksDiscount = false,
        public ?bool $touchesWaiting = null,
        public ?string $waitingNote = null,
        public ?string $easyReply = null,
        public ?ReplyLabel $easyLabel = null,
    ) {}

    /**
     * Build a reply from the structured answer, or throw when it is empty or malformed.
     *
     * The reply's length is not checked here: the hard limit belongs to the send rule.
     *
     * A blank reply is accepted only when the model also names a service whose price the customer asked about: a pure
     * price ask has no other words, and asking Jev about filler words would hold them on the customer's price question.
     * Anything else with a blank reply is still malformed.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws UnexpectedValueException
     */
    public static function fromStructured(array $data): self
    {
        $reply = $data['reply'] ?? null;
        $priceServices = self::priceServices($data['price_services'] ?? null);

        if (! is_string($reply) || (trim($reply) === '' && $priceServices === [])) {
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
            priceServices: $priceServices,
            asksDiscount: ($data['asks_discount'] ?? null) === true,
            touchesWaiting: is_bool($data['touches_waiting'] ?? null) ? $data['touches_waiting'] : null,
            waitingNote: in_array($data['waiting_note'] ?? null, self::WAITING_NOTES, true) ? $data['waiting_note'] : null,
            easyReply: self::tolerantText($data['easy_reply'] ?? null),
            easyLabel: is_string($data['easy_label'] ?? null) ? ReplyLabel::tryFrom($data['easy_label']) : null,
        );
    }

    /**
     * @return array{reply: string, label: string, wants_person: bool, service_slug: ?string, intake_answers: list<array{question_key: string, option: ?string, raw_text: string}>, customer_name: ?string, price_services: list<string>, asks_discount: bool, touches_waiting: ?bool, waiting_note: ?string, easy_reply: ?string, easy_label: ?string}
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
            'price_services' => $this->priceServices,
            'asks_discount' => $this->asksDiscount,
            'touches_waiting' => $this->touchesWaiting,
            'waiting_note' => $this->waitingNote,
            'easy_reply' => $this->easyReply,
            'easy_label' => $this->easyLabel?->value,
        ];
    }

    /**
     * The two things the model may say about a text that arrives while a question is with the owner.
     */
    private const array WAITING_NOTES = ['check_in', 'added_info'];

    /**
     * The service slugs the customer asked a price for: text entries only, trimmed, non-empty, each once, in order.
     * Anything that is not a list gives an empty one, which is a held price draft, today's behavior.
     *
     * @return list<string>
     */
    private static function priceServices(mixed $services): array
    {
        if (! is_array($services)) {
            return [];
        }

        $kept = [];

        foreach ($services as $service) {
            $slug = is_string($service) ? trim($service) : '';

            if ($slug !== '' && ! in_array($slug, $kept, true)) {
                $kept[] = $slug;
            }
        }

        return $kept;
    }

    /**
     * A new field's text, trimmed; anything that is not non-empty text gives null instead of throwing, so a
     * malformed value never costs a provider attempt.
     */
    private static function tolerantText(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
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
