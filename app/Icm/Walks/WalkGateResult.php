<?php

namespace App\Icm\Walks;

use App\Icm\HiddenCharacters;

/**
 * What the gate found across a candidate's walks, and every line it can say about it. The lines are built here and only
 * here, from plain values, so the words are pinned with no app running.
 *
 * There are two forms of a line. The owner form goes on the Business Folder page and in the Sync Now notification, so it
 * never holds a command, console wording or the word stale (10 D-04, D-05). Ian's form goes to changelog.md only and
 * carries the command and the file name. A stale walk has no owner form at all: it is reported to Ian and never refused.
 *
 * Every line passes through HiddenCharacters::marked(), so a path or a service name from the owner's folder can never
 * print a line break or an invisible mark, and no line holds an exclamation point or an em dash.
 */
readonly class WalkGateResult
{
    /**
     * @param  list<WalkGateEntry>  $entries  In walk path order.
     */
    public function __construct(private array $entries = []) {}

    /**
     * The result for a candidate with no walk files: nothing ran and nothing is refused.
     */
    public static function none(): self
    {
        return new self;
    }

    /**
     * @return list<WalkGateEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Whether any walk keeps the candidate from going live: one with no recording, one that failed, one the recording no
     * longer answers and one that stopped with an error.
     */
    public function refused(): bool
    {
        foreach ($this->entries as $entry) {
            if ($this->refuses($entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One line per refused walk for the page and the notification, in walk order, with no command and no stale wording.
     * The label is the live version's, such as "v3", or null when nothing is live yet.
     *
     * @return list<string>
     */
    public function ownerLines(?string $liveLabel): array
    {
        $live = $this->liveClause($liveLabel);
        $lines = [];

        foreach ($this->entries as $entry) {
            if (! $this->refuses($entry)) {
                continue;
            }

            $lines[] = match (true) {
                ! $entry->recorded => "{$entry->path}: this walk has no recording yet. Recording it is Ian's step. {$live}",
                $entry->outcome === WalkOutcome::NeedsRecording => "{$entry->path}: this walk needs a new recording. Recording it is Ian's step. {$live}",
                $entry->outcome === WalkOutcome::Errored => "{$entry->path}: this walk stopped with an error before it finished. {$live}",
                default => $this->failedLine($entry, $live),
            };
        }

        return array_map($this->plain(...), $lines);
    }

    /**
     * The same refused walks as Ian reads them in changelog.md, with the command and the file name.
     *
     * @return list<string>
     */
    public function ianLines(?string $liveLabel): array
    {
        $live = $this->liveClause($liveLabel);
        $lines = [];

        foreach ($this->entries as $entry) {
            if (! $this->refuses($entry)) {
                continue;
            }

            $lines[] = match (true) {
                ! $entry->recorded => "{$entry->path}: no recording. Run `walks:record {$entry->path}`. {$live}",
                $entry->outcome === WalkOutcome::NeedsRecording => "{$entry->path}: the recording no longer answers this walk (turn {$this->exhaustedTurn($entry)} needed a {$this->exhaustedNeed($entry)} it does not hold). Run `walks:record {$entry->path}`. {$live}",
                $entry->outcome === WalkOutcome::Errored => "{$entry->path}: the walk stopped with an error ({$this->errorWords($entry)}). {$live}",
                default => $this->failedLine($entry, $live),
            };
        }

        return array_map($this->plain(...), $lines);
    }

    /**
     * One line per stale walk, for changelog.md only: a walk whose recording was made against an older front desk prompt or
     * older triage questions than the candidate's. A walk that was refused drops the sentence saying it passed.
     *
     * @return list<string>
     */
    public function staleNotes(): array
    {
        $notes = [];

        foreach ($this->entries as $entry) {
            if (! $entry->stale) {
                continue;
            }

            $passed = $entry->outcome === WalkOutcome::Passed ? ' It still ran and passed its checks.' : '';

            $notes[] = $this->plain("{$entry->path} is stale: its recording is older than the front desk prompt or the triage questions.{$passed} Run `walks:record {$entry->path}` to refresh it.");
        }

        return $notes;
    }

    /**
     * A walk refuses the candidate when it has no recording, failed, needs a new recording or stopped with an error.
     */
    private function refuses(WalkGateEntry $entry): bool
    {
        return ! $entry->recorded || $entry->outcome !== WalkOutcome::Passed;
    }

    /**
     * "v3 stays live." or, with nothing live, "Nothing is live yet." (10 UI-SPEC ruling 3).
     */
    private function liveClause(?string $liveLabel): string
    {
        return $liveLabel === null ? 'Nothing is live yet.' : "{$liveLabel} stays live.";
    }

    /**
     * "tests/dana.md, turn 3: the reply did not carry the price sentence for AC repair. v3 stays live." (D-11), or "at the
     * end" for a check made after the last turn.
     */
    private function failedLine(WalkGateEntry $entry, string $live): string
    {
        $where = $entry->failure?->turn === null ? 'at the end' : "turn {$entry->failure->turn}";

        return "{$entry->path}, {$where}: ".rtrim($entry->failure?->words ?? 'a check did not hold', '.').". {$live}";
    }

    private function exhaustedTurn(WalkGateEntry $entry): int
    {
        return $entry->exhausted['turn'] ?? 1;
    }

    private function exhaustedNeed(WalkGateEntry $entry): string
    {
        return $entry->exhausted['needed'] ?? 'model reply';
    }

    private function errorWords(WalkGateEntry $entry): string
    {
        return rtrim((string) $entry->error, '.');
    }

    /**
     * A finished line made safe to print: hidden characters marked, no exclamation point and no em dash, whatever the
     * folder or an error message put in it.
     */
    private function plain(string $line): string
    {
        return str_replace(['!', '—'], ['', '-'], HiddenCharacters::marked($line));
    }
}
