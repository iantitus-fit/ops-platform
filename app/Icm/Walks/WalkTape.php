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
 * ClassifierResult::toArray() of Jev's raw scores.
 *
 * The same class is the recording side (10 D-03). A walk played with the real model and Jev keeps each reply and each
 * result here, in the order they were asked for, under the customer turn under way; a provider that did not answer is
 * noted instead, so the command can refuse to store a half-made recording. The stored shape then also holds
 * recorded_with: the provider and model that wrote the first reply and the Jev model that scored the first call.
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
     * @var list<array{turn: int, what: string, message: string}>
     */
    private array $recordingFailures = [];

    /**
     * @var array{agent: array{provider: string, model: string}|null, jev: string|null}
     */
    private array $recordedWith = ['agent' => null, 'jev' => null];

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
     * @return array{version: int, turns: array<string, array{agent: list<array<string, mixed>>, readings: list<array<string, mixed>>, words: list<array<string, mixed>>}>, recorded_with?: array{agent: array{provider: string, model: string}|null, jev: string|null}}
     */
    public function toPayload(): array
    {
        $turns = [];

        foreach ($this->turns as $turn => $entries) {
            $turns[(string) $turn] = $entries;
        }

        $payload = ['version' => self::VERSION, 'turns' => $turns];

        if ($this->recordedWith['agent'] !== null || $this->recordedWith['jev'] !== null) {
            $payload['recorded_with'] = $this->recordedWith;
        }

        return $payload;
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
     * Keep the front desk model's reply for the turn under way, with the provider and model that wrote it.
     *
     * @param  array<string, mixed>  $reply  AgentReply::toArray().
     */
    public function recordAgentReply(array $reply, string $provider, string $model): void
    {
        $this->openTurn();
        $this->turns[$this->turn]['agent'][] = $reply;
        $this->recordedWith['agent'] ??= ['provider' => $provider, 'model' => $model];
    }

    /**
     * Keep Jev's reading of the customer's text, as the raw scores it gave.
     *
     * @param  array<string, mixed>  $result  ClassifierResult::toArray().
     */
    public function recordReading(string $customerMessage, array $result): void
    {
        $this->openTurn();
        $this->turns[$this->turn]['readings'][] = ['customer_message' => $customerMessage, 'result' => $result];
        $this->recordedWith['jev'] ??= (string) ($result['model'] ?? '');
    }

    /**
     * Keep Jev's check of the model's words, as the raw scores it gave.
     *
     * @param  array<string, mixed>  $result  ClassifierResult::toArray().
     */
    public function recordWordsCheck(string $customerMessage, string $draftReply, array $result): void
    {
        $this->openTurn();
        $this->turns[$this->turn]['words'][] = ['customer_message' => $customerMessage, 'draft_reply' => $draftReply, 'result' => $result];
        $this->recordedWith['jev'] ??= (string) ($result['model'] ?? '');
    }

    /**
     * Note that the model or Jev did not answer in the turn under way. A tape that holds one is not stored.
     *
     * @param  string  $what  "the model" or "Jev".
     */
    public function noteRecordingFailure(string $what, string $message): void
    {
        $this->recordingFailures[] = ['turn' => $this->turn, 'what' => $what, 'message' => $message];
    }

    /**
     * Every call the model or Jev did not answer while this tape was being made, in the order they happened.
     *
     * @return list<array{turn: int, what: string, message: string}>
     */
    public function recordingFailures(): array
    {
        return $this->recordingFailures;
    }

    /**
     * Make the lists of the turn under way when the turn first records something.
     */
    private function openTurn(): void
    {
        $this->turns[$this->turn] ??= ['agent' => [], 'readings' => [], 'words' => []];
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
