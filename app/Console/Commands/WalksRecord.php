<?php

namespace App\Console\Commands;

use App\Ai\BookingSectionWording;
use App\Icm\Exceptions\IcmFolderMissing;
use App\Icm\FolderSync;
use App\Icm\HiddenCharacters;
use App\Icm\Walks\CalibrationItem;
use App\Icm\Walks\CalibrationSet;
use App\Icm\Walks\ScratchWorld;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkFingerprint;
use App\Icm\Walks\WalkOutcome;
use App\Icm\Walks\WalkResult;
use App\Icm\Walks\WalkRunner;
use App\Models\WalkRecording;
use App\SendRule\Classifier;
use App\SendRule\ClassifierFailed;
use App\SendRule\ClassifierResult;
use App\SendRule\TriageQuestions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use JsonException;
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
 * --variant records with the Booking section's two texts taken from a JSON wording file instead of the prompt as built, and
 * stores the row under that wording's name, which the sync never replays (D-18). --export also writes each stored recording
 * as one JSON file, the form the committed fixtures use, so a live run can refresh them. Both are given by Ian, never by a test
 * against a provider.
 *
 * --calibration records no walk. It asks the container's Classifier, which is the live Jev in Ian's run, about each text of the
 * calibration set, --runs times, and writes the raw scores back into the set only when every call answered (10 D-13, D-17 step 2).
 * A test passes it a temporary copy, never the committed set.
 *
 * It does not take the sync lock and it never calls a provider outside a run Ian starts. Lines that hold a file name, a
 * check's words or a provider's error text are printed raw, with hidden characters marked, so none is read as console
 * formatting or forges a line.
 */
#[Signature('walks:record {walk?* : Walk files to record, such as tests/dana.md; every walk in the folder when none is named} {--variant= : A Booking wording file (JSON) to record with instead of the prompt as built} {--export= : A folder to write each recording to as JSON} {--calibration= : Record the calibration set at this path instead of the walks} {--runs=1 : How many times to ask Jev about each calibration text, 1 to 10}')]
#[Description('Play each walk once against the folder as it is now, with the live model and Jev, keep their replies and raw scores, and send nothing')]
class WalksRecord extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FolderSync $folderSync, WalkRunner $runner, Filesystem $files): int
    {
        if ($this->option('calibration') !== null) {
            return $this->recordCalibration($folderSync, $files);
        }

        if ($this->input->hasParameterOption('--runs')) {
            $this->say('--runs only applies with --calibration.');

            return self::FAILURE;
        }

        try {
            $variant = $this->wording($files);
        } catch (InvalidArgumentException $exception) {
            $this->say($exception->getMessage());

            return self::FAILURE;
        }

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

            $result = $runner->record($walk, $compiled, null, $variant);
            $unanswered = $result->tape->recordingFailures()[0] ?? null;

            if ($unanswered !== null) {
                $this->say("{$path}, turn {$unanswered['turn']}: {$unanswered['what']} did not answer ({$unanswered['message']}). Nothing was recorded for this walk.");

                $failed = true;

                continue;
            }

            $recording = WalkRecording::query()->updateOrCreate(
                ['walk_path' => $path, 'script_hash' => WalkFingerprint::scriptHash($walk), 'variant' => $variant->name ?? WalkRecording::AS_BUILT],
                ['fingerprint' => (string) $result->fingerprint, 'payload' => $result->tape->toPayload(), 'recorded_at' => now()],
            );

            if ($this->option('export') !== null) {
                $this->export($files, (string) $this->option('export'), $recording);
            }

            $this->say($this->recordedLine($path, $result));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Ask the container's Classifier about each text of the calibration set and keep what it answered, in place.
     *
     * The folder is opened as a scratch world with HTTP allowed, the way a walk is recorded, so nothing live is written and
     * only the Classifier's own call leaves the app. A reading asks about the customer's text alone (an empty draft), a words
     * check about the text and the item's reply. The file is written once, after every call has answered; a call that did not
     * answer ends the run with nothing written.
     */
    private function recordCalibration(FolderSync $folderSync, Filesystem $files): int
    {
        $runs = (string) $this->option('runs');

        if (preg_match('/^\d+$/', $runs) !== 1 || (int) $runs < 1 || (int) $runs > 10) {
            $this->say('--runs must be a whole number from 1 to 10.');

            return self::FAILURE;
        }

        $given = (string) $this->option('calibration');
        $path = $files->exists($given) ? $given : base_path($given);

        try {
            $set = $this->calibrationSet($files, $path, $given);
            $candidate = $folderSync->candidateForWalks();
        } catch (InvalidArgumentException|IcmFolderMissing $exception) {
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
        $triage = (array) ($compiled['files'][(string) ($compiled['index']['triage'] ?? '')] ?? []);
        $questions = TriageQuestions::fromConfig((array) ($triage['front_matter'] ?? []));
        $world = ScratchWorld::open($compiled, allowHttp: true);

        try {
            $classifier = app(Classifier::class);
            $answered = [];

            foreach ($set->items() as $item) {
                $results = [];

                for ($run = 0; $run < (int) $runs; $run++) {
                    try {
                        $results[] = $classifier->classify($item->customerMessage, $item->draftReply, $questions);
                    } catch (ClassifierFailed $failed) {
                        $this->say("{$item->id}: Jev did not answer ({$failed->getMessage()}). Nothing was written.");

                        return self::FAILURE;
                    }
                }

                $answered[$item->id] = $results;
            }
        } finally {
            $world->close();
        }

        $recorded = $set;
        $model = '';

        foreach ($answered as $id => $results) {
            $recorded = $recorded->withResults($id, $results, CalibrationItem::LIVE);
            $model = $model === '' ? $this->modelOf($results) : $model;

            $this->say("Recorded {$id}: ".count($results).(count($results) === 1 ? ' run.' : ' runs.'));
        }

        $files->put($path, json_encode($recorded->recorded($model, now()->toIso8601String())->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        $this->say("Wrote the scores of {$set->itemCount()} items to {$path}. Every score is now a measurement from the classifier that answered.");

        return self::SUCCESS;
    }

    /**
     * The calibration set at a path.
     *
     * @throws InvalidArgumentException in plain words when the file is missing, is not JSON or holds an item that cannot be used
     */
    private function calibrationSet(Filesystem $files, string $path, string $given): CalibrationSet
    {
        if (! $files->isFile($path)) {
            throw new InvalidArgumentException("The calibration set {$given} was not found.");
        }

        try {
            $data = json_decode($files->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException("The calibration set {$given} could not be read as JSON.");
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException("The calibration set {$given} could not be read as JSON.");
        }

        return CalibrationSet::fromArray($data);
    }

    /**
     * The model that answered the first call of a list of results.
     *
     * @param  list<ClassifierResult>  $results
     */
    private function modelOf(array $results): string
    {
        return $results[0]->model ?? '';
    }

    /**
     * The wording --variant names, or null when none is named.
     *
     * @throws InvalidArgumentException in plain words when the file is missing, is not JSON or holds a wording that cannot be used
     */
    private function wording(Filesystem $files): ?BookingSectionWording
    {
        if ($this->option('variant') === null) {
            return null;
        }

        $given = (string) $this->option('variant');
        $path = $files->exists($given) ? $given : base_path($given);

        if (! $files->isFile($path)) {
            throw new InvalidArgumentException("The wording file {$given} was not found.");
        }

        try {
            $data = json_decode($files->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('The wording file could not be read as JSON.');
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException('The wording file could not be read as JSON.');
        }

        return BookingSectionWording::fromArray($data);
    }

    /**
     * Write one recording as <folder>/<walk file name without .md>.json, with .<variant> before .json for a variant: the
     * form the committed fixtures use, so a live run can refresh them.
     */
    private function export(Filesystem $files, string $folder, WalkRecording $recording): void
    {
        $name = basename($recording->walk_path, '.md');
        $suffix = $recording->variant === WalkRecording::AS_BUILT ? '' : ".{$recording->variant}";

        $files->ensureDirectoryExists($folder);
        $files->put("{$folder}/{$name}{$suffix}.json", json_encode([
            'walk_path' => $recording->walk_path,
            'script_hash' => $recording->script_hash,
            'variant' => $recording->variant,
            'fingerprint' => $recording->fingerprint,
            'recorded_at' => $recording->recorded_at->toIso8601String(),
            'source' => 'live',
            'payload' => $recording->payload,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }

    /**
     * What one kept recording holds and whether the walk's checks held against it.
     */
    private function recordedLine(string $path, WalkResult $result): string
    {
        $turns = $result->tape->toPayload()['turns'];
        $replies = array_sum(array_map(fn (array $turn): int => count($turn['agent']), $turns));
        $readings = array_sum(array_map(fn (array $turn): int => count($turn['readings']), $turns));
        $checks = array_sum(array_map(fn (array $turn): int => count($turn['words']), $turns));
        $kept = "Recorded {$path}: ".implode(', ', [
            $this->counted($replies, 'model reply', 'model replies'),
            $this->counted($readings, 'Jev reading', 'Jev readings'),
            $this->counted($checks, 'Jev reply check', 'Jev reply checks'),
        ]).'.';

        if ($result->outcome === WalkOutcome::Passed) {
            return "{$kept} Its checks passed.";
        }

        return "{$kept} Its checks did not pass: ".rtrim($result->failure?->words ?? 'a check did not hold', '.').'.';
    }

    /**
     * A count with its noun, singular for exactly one and plural otherwise, 0 included (Ian, 2026-11-26).
     */
    private function counted(int $count, string $one, string $many): string
    {
        return $count === 1 ? "1 {$one}" : "{$count} {$many}";
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
