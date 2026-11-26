<?php

namespace App\Console\Commands;

use App\Icm\Exceptions\IcmFolderMissing;
use App\Icm\FolderSync;
use App\Icm\HiddenCharacters;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkFingerprint;
use App\Icm\Walks\WalkOutcome;
use App\Icm\Walks\WalkResult;
use App\Icm\Walks\WalkRunner;
use App\Models\WalkRecording;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Plays each walk once against the folder as it is now, with the live model and Jev, and keeps what they answered (10 D-03).
 *
 * The candidate is the business folder on disk, loaded, validated and compiled exactly as a sync would, and never made
 * live. Each walk is played by WalkRunner::record() in a world of its own, so every text goes to a private fake channel,
 * no live row is written and the owner is not alerted. The model's replies and Jev's raw scores are kept together, one
 * recording for each walk, and stored by walk path, script hash and variant, so recording a walk again replaces the row:
 * it never changes the business folder and never mints a version (D-06). The only live write is that row.
 *
 * A recording is stored only after its walk has finished, in one row update, so a sync that reads recordings while this
 * runs sees either the old row or the new one. A walk where the model or Jev did not answer is not stored, and the command
 * ends with a failure after the rest have been recorded. A recording is kept even when the walk's checks did not pass under
 * today's bars, because the bars are approved from these recordings; the sync decides.
 *
 * It does not take the sync lock and it never calls a provider outside a run Ian starts. Lines that hold a file name, a
 * check's words or a provider's error text are printed raw, with hidden characters marked, so none is read as console
 * formatting or forges a line.
 */
#[Signature('walks:record {walk?* : Walk files to record, such as tests/dana.md; every walk in the folder when none is named}')]
#[Description('Play each walk once against the folder as it is now, with the live model and Jev, keep their replies and raw scores, and send nothing')]
class WalksRecord extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FolderSync $folderSync, WalkRunner $runner): int
    {
        try {
            $candidate = $folderSync->candidateForWalks();
        } catch (IcmFolderMissing $exception) {
            $this->say($exception->getMessage());

            return self::FAILURE;
        }

        if ($candidate->errors !== [] || $candidate->compiled === null) {
            foreach ($candidate->errors as $error) {
                $this->say($error);
            }

            $this->say('The folder does not pass its checks, so nothing was recorded.');

            return self::FAILURE;
        }

        $compiled = $candidate->compiled;
        $inFolder = array_values((array) ($compiled['index']['walk_test'] ?? []));

        sort($inFolder, SORT_STRING);

        $named = array_values(array_unique(array_map('strval', (array) $this->argument('walk'))));

        foreach ($named as $path) {
            if (! in_array($path, $inFolder, true)) {
                $this->say("{$path} is not a walk test in the folder.");

                return self::FAILURE;
            }
        }

        $paths = $named === [] ? $inFolder : array_values(array_filter($inFolder, fn (string $path): bool => in_array($path, $named, true)));
        $failed = false;

        foreach ($paths as $path) {
            $file = (array) ($compiled['files'][$path] ?? []);
            $walk = WalkFile::fromCompiled([...(array) ($file['front_matter'] ?? []), 'body' => (string) ($file['body'] ?? ''), 'path' => $path]);

            $result = $runner->record($walk, $compiled);
            $unanswered = $result->tape->recordingFailures()[0] ?? null;

            if ($unanswered !== null) {
                $this->say("{$path}, turn {$unanswered['turn']}: {$unanswered['what']} did not answer ({$unanswered['message']}). Nothing was recorded for this walk.");

                $failed = true;

                continue;
            }

            WalkRecording::query()->updateOrCreate(
                ['walk_path' => $path, 'script_hash' => WalkFingerprint::scriptHash($walk), 'variant' => WalkRecording::AS_BUILT],
                ['fingerprint' => (string) $result->fingerprint, 'payload' => $result->tape->toPayload(), 'recorded_at' => now()],
            );

            $this->say($this->recordedLine($path, $result));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * What one kept recording holds and whether the walk's checks held against it.
     */
    private function recordedLine(string $path, WalkResult $result): string
    {
        $turns = $result->tape->toPayload()['turns'];
        $replies = array_sum(array_map(fn (array $turn): int => count($turn['agent']), $turns));
        $readings = array_sum(array_map(fn (array $turn): int => count($turn['readings']) + count($turn['words']), $turns));
        $kept = "Recorded {$path}: {$replies} model replies, {$readings} Jev readings.";

        if ($result->outcome === WalkOutcome::Passed) {
            return "{$kept} Its checks passed.";
        }

        return "{$kept} Its checks did not pass: ".rtrim($result->failure?->words ?? 'a check did not hold', '.').'.';
    }

    /**
     * One line, raw, with hidden characters marked: a file name, a check's words or a provider's error text is shown as
     * written and never read as console formatting.
     */
    private function say(string $line): void
    {
        $this->getOutput()->write(HiddenCharacters::marked($line).PHP_EOL, false, OutputInterface::OUTPUT_RAW);
    }
}
