<?php

namespace App\Icm\Walks;

/**
 * The recording a walk is played from: for each customer turn, the replies the front desk model gave, the readings Jev
 * made of the customer's text and the checks Jev made of the model's words, each in the order they were asked for.
 *
 * Nothing here guesses. The tape hands out only the current turn's next entry, in order, and when none is left it says
 * so and notes the first call it could not answer: which turn needed what (10 D-27). The two replay seams turn that
 * into a refusal, and the walk then needs a new recording.
 *
 * The stored shape is {version: 1, turns: {"<n>": {agent, readings, words}}}: agent holds AgentReply::toArray() arrays,
 * readings holds {customer_message, result} and words holds {customer_message, draft_reply, result}, where result is a
 * ClassifierResult::toArray() of Jev's raw scores. Plan 10-06 adds the recording side to this same class.
 */
class WalkTape
{
    private const int VERSION = 1;

    /**
     * @var array<int, array{agent: list<array<string, mixed>>, readings: list<array<string, mixed>>, words: list<array<string, mixed>>}>
     */
    private array $turns;

    /**
     * Where each list is up to in the current turn.
     *
     * @var array{agent: int, readings: int, words: int}
     */
    private array $position = ['agent' => 0, 'readings' => 0, 'words' => 0];

    private int $turn = 0;

    /**
     * @var array{turn: int, needed: string}|null
     */
    private ?array $exhausted = null;

    /**
     * @param  array<int, array{agent: list<array<string, mixed>>, readings: list<array<string, mixed>>, words: list<array<string, mixed>>}>  $turns
     */
    public function __construct(array $turns = [])
    {
        $this->turns = $turns;
    }

    /**
     * Build a tape from its stored shape.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $turns = [];

        foreach ((array) ($payload['turns'] ?? []) as $turn => $entries) {
            $entries = (array) $entries;

            $turns[(int) $turn] = [
                'agent' => array_values((array) ($entries['agent'] ?? [])),
                'readings' => array_values((array) ($entries['readings'] ?? [])),
                'words' => array_values((array) ($entries['words'] ?? [])),
            ];
        }

        return new self($turns);
    }

    /**
     * The stored shape of the tape.
     *
     * @return array{version: int, turns: array<string, array{agent: list<array<string, mixed>>, readings: list<array<string, mixed>>, words: list<array<string, mixed>>}>}
     */
    public function toPayload(): array
    {
        $turns = [];

        foreach ($this->turns as $turn => $entries) {
            $turns[(string) $turn] = $entries;
        }

        return ['version' => self::VERSION, 'turns' => $turns];
    }

    /**
     * Begin handing out the entries of one customer turn, from its first.
     */
    public function startTurn(int $turn): void
    {
        $this->turn = $turn;
        $this->position = ['agent' => 0, 'readings' => 0, 'words' => 0];
    }

    /**
     * The model's next recorded reply in this turn, or null when the recording holds no more.
     *
     * @return array<string, mixed>|null
     */
    public function nextAgentReply(): ?array
    {
        return $this->next('agent', ['needed' => 'model reply']);
    }

    /**
     * Jev's next recorded reading of the customer's text in this turn, or null when the recording holds no more.
     *
     * @return array<string, mixed>|null
     */
    public function nextReading(): ?array
    {
        return $this->next('readings', ['needed' => 'Jev reading']);
    }

    /**
     * Jev's next recorded check of the model's words in this turn, or null when the recording holds no more. A words
     * check is a Jev call too, so running out of them is noted as needing a Jev reading.
     *
     * @return array<string, mixed>|null
     */
    public function nextWordsCheck(): ?array
    {
        return $this->next('words', ['needed' => 'Jev reading']);
    }

    /**
     * The first turn and call the recording could not answer, or null while it has answered everything asked.
     *
     * @return array{turn: int, needed: string}|null
     */
    public function exhausted(): ?array
    {
        return $this->exhausted;
    }

    /**
     * @param  'agent'|'readings'|'words'  $list
     * @param  array{needed: string}  $exhaustion  What to note when this list has nothing left.
     * @return array<string, mixed>|null
     */
    private function next(string $list, array $exhaustion): ?array
    {
        $entry = $this->turns[$this->turn][$list][$this->position[$list]] ?? null;

        if ($entry === null) {
            $this->exhausted ??= ['turn' => $this->turn, ...$exhaustion];

            return null;
        }

        $this->position[$list]++;

        return $entry;
    }
}
