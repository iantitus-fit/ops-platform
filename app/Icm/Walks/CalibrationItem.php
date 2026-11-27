<?php

namespace App\Icm\Walks;

use App\SendRule\ClassifierResult;

/**
 * One text of the calibration set (10 D-13) with the raw scores Jev gave it, one result for each time it was asked.
 *
 * The item says which side it is on: plain texts must send on their own, risky ones must hold or hand off. A risky item
 * names the check it tests. The role says what Jev read: the customer's text alone (a reading, no draft reply) or the
 * model's words with it (a words check). The source says where the scores came from, and `measured` lists the score keys
 * that were measured, so a placeholder is never read as a measurement (T-10-33).
 */
readonly class CalibrationItem
{
    public const string PLAIN = 'plain';

    public const string RISKY = 'risky';

    public const string READING = 'reading';

    public const string WORDS = 'words';

    public const string PLACEHOLDER = 'placeholder';

    public const string LIVE = 'live';

    /**
     * The source of an item made from a call in a walk's recording, never from the calibration file.
     */
    public const string WALK = 'walk recording';

    /**
     * The checks a risky item may test.
     *
     * @var list<string>
     */
    public const array CHECKS = ['complaint', 'promise', 'price', 'discount', 'asks_for_person'];

    /**
     * Every score key a result holds, the names `measured` may use.
     *
     * @var list<string>
     */
    public const array SCORE_KEYS = ['intent', 'intent_confidence', 'intent_probabilities', 'price', 'promise', 'discount', 'complaint', 'life_safety', 'asks_for_person'];

    /**
     * @param  list<string>  $measured  The score keys that were measured; empty for a placeholder.
     * @param  list<ClassifierResult>  $results  One for each time Jev was asked.
     */
    public function __construct(
        public string $id,
        public string $side,
        public ?string $check,
        public string $role,
        public string $customerMessage,
        public string $draftReply,
        public string $source,
        public array $measured,
        public array $results,
    ) {}

    /**
     * The same item with new results, a new source and every score marked measured.
     *
     * @param  list<ClassifierResult>  $results
     */
    public function withResults(array $results, string $source): self
    {
        return new self($this->id, $this->side, $this->check, $this->role, $this->customerMessage, $this->draftReply, $source, self::SCORE_KEYS, $results);
    }

    /**
     * @return array{id: string, side: string, check: ?string, role: string, customer_message: string, draft_reply: string, source: string, measured: list<string>, results: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'side' => $this->side,
            'check' => $this->check,
            'role' => $this->role,
            'customer_message' => $this->customerMessage,
            'draft_reply' => $this->draftReply,
            'source' => $this->source,
            'measured' => $this->measured,
            'results' => array_map(fn (ClassifierResult $result): array => $result->toArray(), $this->results),
        ];
    }
}
