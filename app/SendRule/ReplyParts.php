<?php

namespace App\SendRule;

use App\Enums\MessageKind;

/**
 * The parts of one outbound text and the kind of message they make (D-07, D-30).
 *
 * The body joins the non-empty parts with one space, in this order: the complaint line, the owner's sentences written
 * by code, the model's words that passed the checks, one fixed line, then the booking line. The disclosure and sign-off
 * are added later by ComposeAutomatedText.
 */
readonly class ReplyParts
{
    /**
     * @param  list<string>  $codeSentences  Owner-written price or offer sentences chosen by code.
     * @param  ?MessageKind  $lineKind  The kind the fixed line makes when nothing else decides it.
     * @param  ?string  $bookingLine  The owner's link line, added by code to a scheduling reply and placed last (D-01). It
     *                                is kept out of codeSentences so a draft pre-fill never carries a minted link.
     */
    public function __construct(
        public ?string $complaintLine = null,
        public array $codeSentences = [],
        public ?string $modelWords = null,
        public ?MessageKind $lineKind = null,
        public ?string $line = null,
        public ?string $bookingLine = null,
    ) {}

    public function body(): string
    {
        $parts = [$this->complaintLine, ...$this->codeSentences, $this->modelWords, $this->line, $this->bookingLine];

        return implode(' ', array_values(array_filter(
            array_map(fn (?string $part): string => trim((string) $part), $parts),
            fn (string $part): bool => $part !== '',
        )));
    }

    /**
     * The kind the whole text makes: a complaint line wins, then the partial holding line, then the model's words, then
     * a check-in or added-info line, so one sent with the owner's sentences is recorded as that reply (IN-01), then
     * code sentences, and last whatever kind the fixed line carries.
     */
    public function kind(): MessageKind
    {
        return match (true) {
            $this->has($this->complaintLine) => MessageKind::ComplaintHolding,
            $this->lineKind === MessageKind::PartialHolding && $this->has($this->line) => MessageKind::PartialHolding,
            $this->hasModelWords() => MessageKind::AgentReply,
            in_array($this->lineKind, [MessageKind::CheckInReply, MessageKind::AddedInfoReply], true) && $this->has($this->line) => $this->lineKind,
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

        if ($this->has($this->bookingLine)) {
            $names[] = 'booking_link';
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
