<?php

namespace App\Console\Commands;

use App\Icm\Exceptions\IcmFolderMissing;
use App\Icm\FolderSync;
use App\Icm\HiddenCharacters;
use App\Icm\Walks\CalibrationReport;
use App\Icm\Walks\CalibrationSet;
use App\SendRule\TriageQuestions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints, for Ian, how each safety bar Phase 10 lets move would treat the plain texts and the risky texts recorded so far
 * (10 D-13): at each candidate number, which plain texts would be held and which risky texts would send. The bars judged
 * are the ones in config/gate.php when the report runs (Ian's D-02 ruling), or the numbers given with --bar.
 *
 * It only reads: the calibration set, the walk recordings and the business folder as it is on disk. It writes nothing, asks
 * no provider and chooses no number; a bar and a wording are approved by Ian after reading this (D-17). Every line that holds
 * a customer's words or a reply's words is printed raw, with hidden characters marked, so none is read as console formatting.
 */
#[Signature('walks:report {--calibration= : The calibration set to read, tests/Fixtures/calibration/calibration-set.json when not given} {--bar=* : A bar to judge by instead of config/gate.php, such as complaint_max_yes=0.25}')]
#[Description('Print how each safety bar would treat the plain and the risky texts recorded so far, and the Booking wording results; it changes nothing')]
class WalksReport extends Command
{
    /**
     * Why each bar that does not move is kept, by the decision that keeps it.
     */
    private const array KEPT_BY = [
        'price_max_yes' => '02 D-45, D-56',
        'discount_max_yes' => '02 D-45, D-56',
        'life_safety_min_yes' => '02 D-46',
    ];

    /**
     * Execute the console command.
     */
    public function handle(FolderSync $folderSync, Filesystem $files, CalibrationReport $report): int
    {
        $shipped = array_map(floatval(...), (array) config('gate.classifier'));

        try {
            $given = $this->given($shipped);
            $set = $this->set($files);
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

        $compiled = $candidate->compiled;

        if ($candidate->errors !== [] || $compiled === null) {
            foreach ($candidate->errors as $error) {
                $this->say($error);
            }

            $this->say('The folder does not pass its checks, so there are no triage questions to judge by.');

            return self::FAILURE;
        }

        $triage = (array) ($compiled['files'][(string) ($compiled['index']['triage'] ?? '')] ?? []);
        $questions = TriageQuestions::fromConfig((array) ($triage['front_matter'] ?? []));

        foreach ($report->lines($set, [], $questions, [...$shipped, ...$given], $given) as $line) {
            $this->say($line);
        }

        return self::SUCCESS;
    }

    /**
     * The bars given with --bar, each checked: only the five that may move, each a number from 0 to 1.
     *
     * @param  array<string, float>  $shipped  The bars in config/gate.php.
     * @return array<string, float>
     *
     * @throws InvalidArgumentException in plain words when a value cannot be used
     */
    private function given(array $shipped): array
    {
        $given = [];

        foreach ((array) $this->option('bar') as $entry) {
            $parts = explode('=', (string) $entry, 2);

            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new InvalidArgumentException("--bar must look like complaint_max_yes=0.25, not {$entry}.");
            }

            [$name, $text] = [trim($parts[0]), trim($parts[1])];

            if (isset(self::KEPT_BY[$name])) {
                throw new InvalidArgumentException("{$name} does not move (".self::KEPT_BY[$name].'); the report judges it at '.CalibrationReport::numberText($shipped[$name]).'.');
            }

            if (! array_key_exists($name, $shipped)) {
                throw new InvalidArgumentException("{$name} is not a bar in config/gate.php.");
            }

            if (! is_numeric($text) || (float) $text < 0 || (float) $text > 1) {
                throw new InvalidArgumentException("{$name} must be a number from 0 to 1, not {$text}.");
            }

            $given[$name] = (float) $text;
        }

        return $given;
    }

    /**
     * The calibration set --calibration names, or the committed one.
     *
     * @throws InvalidArgumentException in plain words when the file is missing, is not JSON or holds an item that cannot be used
     */
    private function set(Filesystem $files): CalibrationSet
    {
        $given = $this->option('calibration') === null ? 'tests/Fixtures/calibration/calibration-set.json' : (string) $this->option('calibration');
        $path = $files->exists($given) ? $given : base_path($given);

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
     * One line, raw, with hidden characters marked: a customer's or a reply's words are shown as written and never read
     * as console formatting (GateExport's precedent).
     */
    private function say(string $line): void
    {
        $this->getOutput()->write(HiddenCharacters::marked($line).PHP_EOL, false, OutputInterface::OUTPUT_RAW);
    }
}
