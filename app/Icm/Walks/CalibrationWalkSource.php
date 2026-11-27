<?php

namespace App\Icm\Walks;

use App\Enums\ReplyLabel;
use App\Models\WalkRecording;
use App\SendRule\ClassifierResult;
use InvalidArgumentException;

/**
 * The walk recordings as the report's evidence (10 D-13, D-18): every Jev call in the as_built recordings as one item
 * labeled plain or risky by what its walk expects of that turn, and each recording of a booking walk replayed for the
 * Booking wording block. It only reads: recordings, the candidate's walk files and a replay in a scratch world.
 *
 * A turn the walk expects sent on its own is plain; one it expects held for the owner or handed to the owner is risky, and
 * the risky side wins when a turn says both (D-14). A turn that expects neither is left out. A recording whose script no
 * longer matches its walk is left out too, because its turns no longer line up with the walk's.
 */
class CalibrationWalkSource
{
    public function __construct(private readonly WalkRunner $runner) {}

    /**
     * One item for each recorded call, with the walk's path and turn as its id and one result.
     *
     * @param  array<string, mixed>  $compiled  The candidate's compiled copy.
     * @return list<CalibrationItem>
     */
    public function items(array $compiled): array
    {
        $items = [];

        foreach (WalkRecording::query()->where('variant', WalkRecording::AS_BUILT)->orderBy('walk_path')->get() as $row) {
            $walk = $this->walk($compiled, $row->walk_path);

            if ($walk === null || WalkFingerprint::scriptHash($walk) !== $row->script_hash) {
                continue;
            }

            $turns = (array) ($row->payload['turns'] ?? []);

            ksort($turns, SORT_NUMERIC);

            foreach ($turns as $number => $entries) {
                $step = collect($walk->steps)->firstWhere('turn', (int) $number);
                $side = $step === null ? null : $this->side($step->checks);

                if ($side === null) {
                    continue;
                }

                $vetoes = $this->vetoes((array) ($entries['agent'] ?? []));

                foreach ((array) ($entries['readings'] ?? []) as $reading) {
                    $this->add($items, $row->walk_path, (int) $number, $side, CalibrationItem::READING, (string) ($reading['customer_message'] ?? '') ?: (string) $step->says, '', $reading['result'] ?? null, $vetoes);
                }

                foreach ((array) ($entries['words'] ?? []) as $words) {
                    $this->add($items, $row->walk_path, (int) $number, $side, CalibrationItem::WORDS, (string) ($words['customer_message'] ?? '') ?: (string) $step->says, (string) ($words['draft_reply'] ?? ''), $words['result'] ?? null, $vetoes);
                }
            }
        }

        return $items;
    }

    /**
     * Each recording of a walk with a turn expecting a scheduling label, as_built first, replayed under the bars given. The
     * bars are put in config for the replay and put back after, whatever happens.
     *
     * @param  array<string, mixed>  $compiled  The candidate's compiled copy.
     * @param  array<string, float>  $bars
     * @return list<BookingWordingRun>
     */
    public function bookingRuns(array $compiled, array $bars): array
    {
        $runs = [];
        $before = config('gate.classifier');

        config(['gate.classifier' => $bars]);

        try {
            foreach (WalkRecording::query()->orderBy('walk_path')->orderByRaw("variant = 'as_built' desc")->orderBy('variant')->get() as $row) {
                $walk = $this->walk($compiled, $row->walk_path);
                $booking = $walk === null ? [] : $this->schedulingTurns($walk);

                if ($walk === null || $booking === [] || WalkFingerprint::scriptHash($walk) !== $row->script_hash) {
                    continue;
                }

                $played = $this->runner->replay($walk, $compiled, WalkTape::fromPayload((array) $row->payload));
                $summary = match ($played->outcome) {
                    WalkOutcome::Passed => 'the walk passed.',
                    WalkOutcome::Failed => 'the walk did not pass: '.rtrim($played->failure?->words ?? 'a check did not hold', '.').'.',
                    WalkOutcome::NeedsRecording => 'the recording does not answer every call the front desk made under these bars, so the walk needs a new recording.',
                    WalkOutcome::Errored => 'the walk could not be played.',
                };

                $runs[] = new BookingWordingRun(
                    $row->walk_path,
                    $row->variant,
                    $summary,
                    array_values(array_filter($played->turns ?? [], fn (WalkTurnObservation $seen): bool => in_array($seen->turn, $booking, true))),
                );
            }
        } finally {
            config(['gate.classifier' => $before]);
        }

        return $runs;
    }

    /**
     * The walk file at a path of the candidate, or null when the candidate holds none there.
     *
     * @param  array<string, mixed>  $compiled
     */
    private function walk(array $compiled, string $path): ?WalkFile
    {
        $file = $compiled['files'][$path] ?? null;

        if (! is_array($file) || ! in_array($path, (array) ($compiled['index']['walk_test'] ?? []), true)) {
            return null;
        }

        return WalkFile::fromCompiled([...(array) ($file['front_matter'] ?? []), 'body' => (string) ($file['body'] ?? ''), 'path' => $path]);
    }

    /**
     * The turns of a walk that expect the reply labeled scheduling.
     *
     * @return list<int>
     */
    private function schedulingTurns(WalkFile $walk): array
    {
        $turns = [];

        foreach ($walk->steps as $step) {
            foreach ($step->checks as $check) {
                if ($check->kind === WalkCheckKind::Labeled && $check->argument === 'scheduling') {
                    $turns[] = $step->turn;
                }
            }
        }

        return array_values(array_unique($turns));
    }

    /**
     * Plain when a check expects the turn sent on its own, risky when one expects it held or handed over, and risky when
     * both; null when the turn expects neither.
     *
     * @param  list<WalkCheck>  $checks
     */
    private function side(array $checks): ?string
    {
        $kinds = array_map(fn (WalkCheck $check): WalkCheckKind => $check->kind, $checks);

        return match (true) {
            in_array(WalkCheckKind::HeldForTheOwner, $kinds, true), in_array(WalkCheckKind::HandedToTheOwner, $kinds, true) => CalibrationItem::RISKY,
            in_array(WalkCheckKind::SentOnItsOwn, $kinds, true) => CalibrationItem::PLAIN,
            default => null,
        };
    }

    /**
     * What holds a turn whatever Jev scored: the model's own label when it is one that never sends on its own (price,
     * promise, discount, complaint), the first check DecideSendOrDraft makes and a floor no stored rule can lower. The code
     * checks on the words are not replayed here; the walk gate covers them.
     *
     * @param  array<array-key, mixed>  $replies  The turn's recorded model replies.
     * @return list<string>
     */
    private function vetoes(array $replies): array
    {
        $vetoes = [];

        foreach ($replies as $reply) {
            $label = is_array($reply) ? ReplyLabel::tryFrom((string) ($reply['label'] ?? '')) : null;

            if ($label !== null && in_array($label, ReplyLabel::neverOnItsOwn(), true)) {
                $vetoes[] = "label:{$label->value}";
            }
        }

        return array_values(array_unique($vetoes));
    }

    /**
     * @param  list<CalibrationItem>  $items
     * @param  list<string>  $vetoes
     */
    private function add(array &$items, string $path, int $turn, string $side, string $role, string $message, string $reply, mixed $result, array $vetoes): void
    {
        try {
            $parsed = is_array($result) ? CalibrationSet::resultFromArray($result) : null;
        } catch (InvalidArgumentException) {
            $parsed = null;
        }

        if ($parsed instanceof ClassifierResult && $message !== '') {
            $items[] = new CalibrationItem("{$path} turn {$turn}", $side, null, $role, $message, $reply, CalibrationItem::WALK, [], [$parsed], $vetoes);
        }
    }
}
