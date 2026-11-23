<?php

namespace App\Icm\Walks;

use App\Icm\PlainWords;
use App\Models\IcmVersion;
use App\Models\WalkRecording;
use Throwable;

/**
 * Runs every walk of a candidate before it can go live (10 WALK-02, D-11).
 *
 * Each walk file is matched with its recording by path and a hash of its customer turns (D-05), read on the live
 * connection before the walk's world opens, then replayed against the candidate by WalkRunner. The gate decides nothing
 * about wording: it collects what each walk came to, and WalkGateResult says it in plain words.
 *
 * It fails closed. A walk that cannot run at all, for any reason, is an entry that refuses activation and is reported,
 * never a walk that is skipped. A stale recording is replayed and reported, never refused (D-04).
 */
class WalkGate
{
    /**
     * Replay every walk of the candidate, in walk file path order, so the same folder gives the same lines on every sync.
     * A candidate with no walk files opens no world.
     *
     * @param  array<string, mixed>  $compiled  The candidate's compiled copy.
     * @param  IcmVersion|null  $live  The version live now, or null when nothing is. A walk's result never depends on it: the lines name the live version when they are asked for, with its label.
     */
    public function run(array $compiled, ?IcmVersion $live): WalkGateResult
    {
        $paths = array_values((array) ($compiled['index']['walk_test'] ?? []));

        if ($paths === []) {
            return WalkGateResult::none();
        }

        sort($paths, SORT_STRING);

        return new WalkGateResult(array_map(fn (string $path): WalkGateEntry => $this->play($path, $compiled), $paths));
    }

    /**
     * One walk: its recording, then its replay.
     *
     * @param  array<string, mixed>  $compiled
     */
    private function play(string $path, array $compiled): WalkGateEntry
    {
        try {
            $file = (array) ($compiled['files'][$path] ?? []);
            $walk = WalkFile::fromCompiled([...(array) ($file['front_matter'] ?? []), 'body' => (string) ($file['body'] ?? ''), 'path' => $path]);

            $recording = WalkRecording::query()
                ->where('walk_path', $path)
                ->where('script_hash', WalkFingerprint::scriptHash($walk))
                ->where('variant', WalkRecording::AS_BUILT)
                ->first();

            if ($recording === null) {
                return new WalkGateEntry($path, false, null, null, null, false, null);
            }

            $result = app(WalkRunner::class)->replay($walk, $compiled, WalkTape::fromPayload($recording->payload));

            return new WalkGateEntry($path, true, $result->outcome, $result->failure, $result->exhausted, $recording->fingerprint !== $result->fingerprint, null);
        } catch (Throwable $exception) {
            report($exception);

            $message = PlainWords::oneLine($exception->getMessage());

            return new WalkGateEntry($path, true, WalkOutcome::Errored, null, null, false, $message !== '' ? $message : class_basename($exception));
        }
    }
}
