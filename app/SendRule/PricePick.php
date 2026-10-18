<?php

namespace App\SendRule;

/**
 * What code decided about one service's price question: the owner's sentence to send, the answers still missing, or
 * that the question must reach the owner (D-04, D-08).
 *
 * The sentence is the owner's own words read from the live version. The model only ever names the service.
 */
readonly class PricePick
{
    public const string SEND = 'send';

    public const string NEEDS_ANSWERS = 'needs_answers';

    public const string HOLD = 'hold';

    public const string NOTHING = 'nothing';

    /**
     * @param  list<int>  $amountsCents  Every dollar amount in the sentence, as cents.
     * @param  list<string>  $missingKeys  The intake questions that still need an answer before a sentence can be picked.
     */
    public function __construct(
        public string $outcome,
        public string $slug,
        public ?string $sentenceKey = null,
        public ?string $sentence = null,
        public array $amountsCents = [],
        public ?string $lead = null,
        public array $missingKeys = [],
    ) {}

    /**
     * @param  list<int>  $amounts
     */
    public static function send(string $slug, string $key, string $sentence, array $amounts, ?string $lead = null): self
    {
        return new self(self::SEND, $slug, $key, $sentence, $amounts, $lead);
    }

    /**
     * @param  list<string>  $missingKeys
     */
    public static function needsAnswers(string $slug, array $missingKeys): self
    {
        return new self(self::NEEDS_ANSWERS, $slug, missingKeys: $missingKeys);
    }

    /**
     * The question cannot be answered by code and must reach the owner.
     */
    public static function hold(string $slug): self
    {
        return new self(self::HOLD, $slug);
    }

    /**
     * There is nothing to send for this service right now, and nothing to hold either.
     */
    public static function nothing(string $slug): self
    {
        return new self(self::NOTHING, $slug);
    }

    public function isSend(): bool
    {
        return $this->outcome === self::SEND;
    }
}
