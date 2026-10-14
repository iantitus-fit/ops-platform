<?php

namespace App\SendRule;

use App\Enums\MessageKind;

/**
 * The parts of one outbound text and the kind of message they make (D-07, D-30).
 *
 * The body joins the non-empty parts with one space, in this order: the complaint line, the owner's sentences written
 * by code, the model's words that passed the checks, then one fixed line. The disclosure and sign-off are added later
 * by ComposeAutomatedText.
 */
readonly class ReplyParts
{
    /**
     * @param  list<string>  $codeSentences  Owner-written price or offer sentences chosen by code.
     * @param  ?MessageKind  $lineKind  The kind the fixed line makes when nothing else decides it.
     */
    public function __construct(
        public ?string $complaintLine = null,
        public array $codeSentences = [],
        public ?string $modelWords = null,
        public ?MessageKind $lineKind = null,
        public ?string $line = null,
    ) {}

    public function body(): string
    {
        $parts = [$this->complaintLine, ...$this->codeSentences, $this->modelWords, $this->line];

        return implode(' ', array_values(array_filter(
            array_map(fn (?string $part): string => trim((string) $part), $parts),
            fn (string $part): bool => $part !== '',
        )));
    }

    /**
     * The kind the whole text makes: a complaint line wins, then the partial holding line, then the model's words, then
     * code sentences, and last whatever kind the fixed line carries.
     */
    public function kind(): MessageKind
    {
        return match (true) {
            $this->has($this->complaintLine) => MessageKind::ComplaintHolding,
            $this->lineKind === MessageKind::PartialHolding && $this->has($this->line) => MessageKind::PartialHolding,
            $this->hasModelWords() => MessageKind::AgentReply,
            $this->codeSentences !== [] => MessageKind::CodeAnswer,
            default => $this->lineKind ?? MessageKind::AgentReply,
        };
    }

    /**
     * The names of the parts present, as stored in the message's meta.parts.
     *
     * @return list<string>
     */
    public function partNames(): array
    {
        $names = [];

        if ($this->has($this->complaintLine)) {
            $names[] = 'complaint_line';
        }

        if ($this->codeSentences !== []) {
            $names[] = 'code_sentences';
        }

        if ($this->hasModelWords()) {
            $names[] = 'model_words';
        }

        if ($this->has($this->line)) {
            $names[] = match ($this->lineKind) {
                MessageKind::CheckInReply => 'check_in_line',
                MessageKind::AddedInfoReply => 'added_info_line',
                default => 'partial_holding_line',
            };
        }

        return $names;
    }

    public function hasModelWords(): bool
    {
        return $this->has($this->modelWords);
    }

    private function has(?string $text): bool
    {
        return trim((string) $text) !== '';
    }
}
