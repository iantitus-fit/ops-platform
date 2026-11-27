<?php

namespace App\Icm\Walks;

use App\SendRule\ClassifierResult;
use InvalidArgumentException;

/**
 * The calibration set (10 D-12, D-13): a committed file of invented texts with the raw scores Jev gave them, outside the
 * owner's folder. The report judges these scores by the bars of the moment (Ian's D-02 ruling), so changing a bar changes
 * the verdict on the same item, and Ian's live walks:record --calibration run replaces the scores in place.
 *
 * Every score is a number from 0 to 1. A stored zero or one arrives from JSON as an integer, so an integer or a float is
 * accepted for every score and read as a float, as JevClassifier does (R5).
 */
readonly class CalibrationSet
{
    private const int VERSION = 1;

    /**
     * @param  list<CalibrationItem>  $items
     * @param  array{source: string, jev_model: ?string, recorded_at: ?string}  $recordedWith
     */
    private function __construct(
        private string $note,
        private array $recordedWith,
        private array $items,
    ) {}

    /**
     * Read the set from its decoded JSON.
     *
     * @param  array<array-key, mixed>  $data
     *
     * @throws InvalidArgumentException in plain words naming the item and the field that cannot be used
     */
    public static function fromArray(array $data): self
    {
        if (($data['version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('The calibration set: version must be '.self::VERSION.'.');
        }

        if (! is_array($data['items'] ?? null) || $data['items'] === []) {
            throw new InvalidArgumentException('The calibration set: items must be a list with at least one item.');
        }

        $with = is_array($data['recorded_with'] ?? null) ? $data['recorded_with'] : [];
        $items = [];
        $seen = [];

        foreach (array_values($data['items']) as $position => $item) {
            $parsed = self::item(is_array($item) ? $item : [], $position + 1);

            if (isset($seen[$parsed->id])) {
                throw new InvalidArgumentException("The calibration set, item \"{$parsed->id}\": id is used by another item.");
            }

            $seen[$parsed->id] = true;
            $items[] = $parsed;
        }

        return new self(
            is_string($data['note'] ?? null) ? $data['note'] : '',
            [
                'source' => is_string($with['source'] ?? null) ? $with['source'] : '',
                'jev_model' => is_string($with['jev_model'] ?? null) ? $with['jev_model'] : null,
                'recorded_at' => is_string($with['recorded_at'] ?? null) ? $with['recorded_at'] : null,
            ],
            $items,
        );
    }

    /**
     * @return list<CalibrationItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * How many items the set holds.
     */
    public function itemCount(): int
    {
        return count($this->items);
    }

    /**
     * How many items still hold placeholder scores.
     */
    public function placeholderCount(): int
    {
        return count(array_filter($this->items, fn (CalibrationItem $item): bool => $item->source === CalibrationItem::PLACEHOLDER));
    }

    /**
     * The set with one item's results replaced. Nothing else about the item changes.
     *
     * @param  list<ClassifierResult>  $results
     *
     * @throws InvalidArgumentException when no item has the id
     */
    public function withResults(string $id, array $results, string $source): self
    {
        $found = false;
        $items = [];

        foreach ($this->items as $item) {
            if ($item->id === $id) {
                $found = true;
                $item = $item->withResults($results, $source);
            }

            $items[] = $item;
        }

        if (! $found) {
            throw new InvalidArgumentException("The calibration set has no item \"{$id}\".");
        }

        return new self($this->note, $this->recordedWith, $items);
    }

    /**
     * The set after a live run: which Jev model answered and when. Every score is then a measurement, so the note says so.
     */
    public function recorded(string $model, string $recordedAt): self
    {
        return new self(
            'Recorded by walks:record --calibration: every score is a measurement from the live Jev. Every text here is invented.',
            ['source' => CalibrationItem::LIVE, 'jev_model' => $model, 'recorded_at' => $recordedAt],
            $this->items,
        );
    }

    /**
     * @return array{version: int, note: string, recorded_with: array{source: string, jev_model: ?string, recorded_at: ?string}, items: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'note' => $this->note,
            'recorded_with' => $this->recordedWith,
            'items' => array_map(fn (CalibrationItem $item): array => $item->toArray(), $this->items),
        ];
    }

    /**
     * One recorded result, such as a walk recording holds, read the same way a result of the set is.
     *
     * @param  array<array-key, mixed>  $result
     *
     * @throws InvalidArgumentException in plain words when the result cannot be used
     */
    public static function resultFromArray(array $result): ClassifierResult
    {
        return self::result($result, fn (string $field, string $what): InvalidArgumentException => new InvalidArgumentException("A recorded result: {$field} {$what}."), 'result');
    }

    /**
     * @param  array<array-key, mixed>  $item
     */
    private static function item(array $item, int $position): CalibrationItem
    {
        $id = $item['id'] ?? null;

        if (! is_string($id) || trim($id) === '') {
            throw new InvalidArgumentException("The calibration set, item {$position}: id must be some text.");
        }

        $fail = fn (string $field, string $what): InvalidArgumentException => new InvalidArgumentException("The calibration set, item \"{$id}\": {$field} {$what}.");

        $side = $item['side'] ?? null;

        if (! in_array($side, [CalibrationItem::PLAIN, CalibrationItem::RISKY], true)) {
            throw $fail('side', 'must be plain or risky');
        }

        $check = $item['check'] ?? null;

        if ($side === CalibrationItem::PLAIN && $check !== null) {
            throw $fail('check', 'must be empty for a plain item');
        }

        if ($side === CalibrationItem::RISKY && ! in_array($check, CalibrationItem::CHECKS, true)) {
            throw $fail('check', 'must be one of '.implode(', ', CalibrationItem::CHECKS).' for a risky item');
        }

        $role = $item['role'] ?? null;

        if (! in_array($role, [CalibrationItem::READING, CalibrationItem::WORDS], true)) {
            throw $fail('role', 'must be reading or words');
        }

        $message = $item['customer_message'] ?? null;

        if (! is_string($message) || trim($message) === '') {
            throw $fail('customer_message', 'must be some text');
        }

        $reply = $item['draft_reply'] ?? null;

        if (! is_string($reply) || ($role === CalibrationItem::READING) !== ($reply === '')) {
            throw $fail('draft_reply', 'must be empty for a reading and some text for a words check');
        }

        $source = $item['source'] ?? null;

        if (! is_string($source) || trim($source) === '') {
            throw $fail('source', 'must be some text');
        }

        $measured = $item['measured'] ?? null;

        if (! is_array($measured) || array_diff($measured, CalibrationItem::SCORE_KEYS) !== []) {
            throw $fail('measured', 'must be a list of score keys: '.implode(', ', CalibrationItem::SCORE_KEYS));
        }

        if (! is_array($item['results'] ?? null) || $item['results'] === []) {
            throw $fail('results', 'must hold at least one result');
        }

        $results = [];

        foreach (array_values($item['results']) as $index => $result) {
            $results[] = self::result(is_array($result) ? $result : [], $fail, 'results '.($index + 1));
        }

        return new CalibrationItem($id, $side, $check, $role, $message, $reply, $source, array_values($measured), $results);
    }

    /**
     * @param  array<array-key, mixed>  $result
     * @param  callable(string, string): InvalidArgumentException  $fail
     */
    private static function result(array $result, callable $fail, string $where): ClassifierResult
    {
        if (! is_string($result['model'] ?? null) || ! is_string($result['intent'] ?? null)) {
            throw $fail($where, 'must name a model and an intent');
        }

        $confidence = self::score($result['intent_confidence'] ?? null);

        if ($confidence === null) {
            throw $fail("{$where}, intent_confidence", 'must be a number from 0 to 1');
        }

        $probabilities = self::scores($result['intent_probabilities'] ?? null);

        if ($probabilities === null) {
            throw $fail("{$where}, intent_probabilities", 'must be numbers from 0 to 1, one for each intent');
        }

        $yesNo = self::scores($result['yes_no'] ?? null);

        if ($yesNo === null) {
            throw $fail("{$where}, yes_no", 'must be numbers from 0 to 1, one for each question');
        }

        return new ClassifierResult($result['model'], $result['intent'], $confidence, $probabilities, $yesNo);
    }

    /**
     * @return array<string, float>|null
     */
    private static function scores(mixed $values): ?array
    {
        if (! is_array($values) || $values === []) {
            return null;
        }

        $scores = [];

        foreach ($values as $key => $value) {
            $score = self::score($value);

            if ($score === null) {
                return null;
            }

            $scores[(string) $key] = $score;
        }

        return $scores;
    }

    /**
     * A number from 0 to 1 as a float, whether it arrived as an integer or a float; anything else is null.
     */
    private static function score(mixed $value): ?float
    {
        if ((! is_int($value) && ! is_float($value)) || $value < 0 || $value > 1) {
            return null;
        }

        return (float) $value;
    }
}
