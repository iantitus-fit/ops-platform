<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use App\Icm\Walks\WalkCheck;
use App\Icm\Walks\WalkStep;
use DateTimeImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator as LaravelValidator;
use Illuminate\Validation\Rule;

/**
 * The checks on the walk-test files in tests/ (WALK-01). They work only from the files the Loader already read, never
 * from disk, and the Validator adds what they return to its report, so a broken walk file is refused the same way as any
 * other file: the folder is stored as rejected and the previous version stays live. Every sentence a walk file can
 * earn is written here so the wording can be changed in one place, and every owner value echoed in one goes through
 * PlainWords so it stays on one short line.
 */
class WalkFileChecks
{
    /**
     * The most walk files one sync will read, so a folder of walks cannot stall every sync (T-10-04).
     */
    private const MAX_WALKS = 50;

    private const MAX_SCRIPT_ITEMS = 30;

    private const MAX_SAYS_CHARACTERS = 1600;

    private const MAX_WAIT_DAYS = 14;

    private const EARLIEST_TIME_OFFERED = 'the earliest time offered';

    /**
     * Every problem in the folder's walk files, file by file in path order, then the folder-level cap.
     *
     * @param  array<int, IcmFile>|Collection<int, IcmFile>  $files  The Validator hands over its sorted collection; a test may hand over the Loader's list.
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function errors(array|Collection $files): array
    {
        $walks = collect($files)
            ->filter(fn (IcmFile $file) => $file->kind === IcmFileKind::Typed && $file->type === 'walk_test')
            ->sortBy('path', SORT_STRING)
            ->values();

        $errors = [];

        foreach ($walks as $walk) {
            array_push($errors, ...$this->fileErrors($walk));
        }

        if ($walks->count() > self::MAX_WALKS) {
            $errors[] = $this->error($walks[self::MAX_WALKS]->path, null, "the folder holds {$walks->count()} walk tests and a sync runs at most ".self::MAX_WALKS.'; remove one');
        }

        return $errors;
    }

    /**
     * What is wrong inside one walk file.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function fileErrors(IcmFile $file): array
    {
        $front = $file->frontMatter;
        $errors = [];
        $validator = LaravelValidator::make($front, [
            'name' => 'bail|required|string|max:80',
            'persona' => 'bail|required|string|max:200',
            'channel' => ['bail', 'required', Rule::in(['sms'])],
            'script' => 'bail|required|array',
            'expect' => 'bail|sometimes|array',
        ]);

        if ($validator->fails()) {
            foreach ($validator->failed() as $field => $failures) {
                foreach ($failures as $rule => $parameters) {
                    $errors[] = $this->error($file->path, $field, PlainWords::render($rule, $parameters, $field, Arr::get($front, $field)));
                }
            }
        }

        $hasStart = array_key_exists('start', $front);

        if ($hasStart && ! $this->isStartTime($front['start'])) {
            $errors[] = $this->error($file->path, 'start', 'start must be a date and time in quotes such as 2026-12-01 10:00 (you wrote '.PlainWords::value($front['start']).')');
        }

        $script = $front['script'] ?? null;
        $scriptUsable = is_array($script) && $script !== [] && array_is_list($script);

        if (is_array($script) && $script !== [] && ! array_is_list($script)) {
            $errors[] = $this->error($file->path, 'script', PlainWords::render('Array', [], 'script', $script));
        }

        if ($scriptUsable && count($script) > self::MAX_SCRIPT_ITEMS) {
            $errors[] = $this->error($file->path, 'script', 'script has '.count($script).' items; a walk can have at most '.self::MAX_SCRIPT_ITEMS);
            $scriptUsable = false;
        }

        if ($scriptUsable) {
            array_push($errors, ...$this->scriptErrors($file->path, $script, $hasStart));
        }

        if (isset($front['expect']) && is_array($front['expect'])) {
            array_push($errors, ...$this->phraseErrors($file->path, 'expect', 'expect', $front['expect'], false, $hasStart));
        }

        if ($scriptUsable && ! $this->statesACheck($front, $script)) {
            $errors[] = $this->error($file->path, 'expect', 'the walk states no check; add one under expect or after a turn');
        }

        return $errors;
    }

    /**
     * Every item of the script: exactly one of says, wait or books, a value that reads, and checks from the list.
     *
     * @param  list<mixed>  $script
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function scriptErrors(string $path, array $script, bool $hasStart): array
    {
        $errors = [];
        $hasSays = false;

        foreach ($script as $index => $item) {
            $label = 'script item '.($index + 1);
            $kinds = is_array($item) ? array_values(array_filter(['says', 'wait', 'books'], fn (string $key) => array_key_exists($key, $item))) : [];

            if (count($kinds) !== 1) {
                $errors[] = $this->error($path, 'script', "{$label} must have exactly one of says, wait or books");

                continue;
            }

            $kind = $kinds[0];
            $hasSays = $hasSays || $kind === 'says';
            $problem = match ($kind) {
                'says' => $this->saysProblem($label, $item['says']),
                'wait' => $this->waitProblem($label, $item['wait']),
                default => $this->booksProblem($label, $item['books'], $hasStart),
            };

            if ($problem !== null) {
                $errors[] = $this->error($path, 'script', $problem);
            }

            if (array_key_exists('expect', $item)) {
                array_push($errors, ...$this->phraseErrors($path, 'script', "{$label} expect", $item['expect'], $kind === 'says', $hasStart));
            }
        }

        if (! $hasSays) {
            $errors[] = $this->error($path, 'script', 'script must list at least one says item');
        }

        return $errors;
    }

    private function saysProblem(string $label, mixed $says): ?string
    {
        if ($says === null || (is_string($says) && trim($says) === '')) {
            return "{$label} says is missing, and it is required";
        }

        if (! is_string($says)) {
            return "{$label} says must be text; wrap it in quotes (you wrote ".PlainWords::value($says).')';
        }

        if (mb_strlen($says) > self::MAX_SAYS_CHARACTERS) {
            return "{$label} says is too long; a text can have at most ".self::MAX_SAYS_CHARACTERS.' characters';
        }

        return null;
    }

    private function waitProblem(string $label, mixed $wait): ?string
    {
        $minutes = is_string($wait) ? WalkStep::waitMinutes($wait) : null;

        if ($minutes === null) {
            return "{$label} wait must be a number of minutes, hours or days such as 2 hours (you wrote ".PlainWords::value($wait).')';
        }

        if ($minutes > self::MAX_WAIT_DAYS * 1440) {
            return "{$label} wait must be ".self::MAX_WAIT_DAYS.' days or less (you wrote '.PlainWords::value($wait).')';
        }

        return null;
    }

    private function booksProblem(string $label, mixed $books, bool $hasStart): ?string
    {
        if (is_string($books) && mb_strtolower(trim($books)) === self::EARLIEST_TIME_OFFERED) {
            return null;
        }

        if (! $hasStart && is_string($books) && WalkCheck::namedTime($books) !== null) {
            return "{$label} books names a time (".PlainWords::oneLine($books).') '.$this->noStartTail();
        }

        if ($this->isStartTime($books)) {
            return null;
        }

        return "{$label} books must be the earliest time offered or a date and time such as 2026-12-02 11:00 (you wrote ".PlainWords::value($books).')';
    }

    /**
     * The checks under one script item or at the end of the walk. A phrase that names a time is refused first when the
     * walk has no start (D-10), so the owner sees that line and not the list; any other phrase must be on the list for
     * where it sits (D-09).
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function phraseErrors(string $path, string $field, string $prefix, mixed $phrases, bool $afterSays, bool $hasStart): array
    {
        if (! is_array($phrases) || ! array_is_list($phrases)) {
            return [$this->error($path, $field, "{$prefix} must be a list (you wrote ".PlainWords::value($phrases).')')];
        }

        $errors = [];

        foreach ($phrases as $index => $phrase) {
            $check = is_string($phrase) ? WalkCheck::parse($phrase) : null;

            if ($check !== null && ($afterSays ? $check->kind->afterSays() : $check->kind->afterOutcome())) {
                continue;
            }

            $time = $check === null && ! $hasStart && is_string($phrase) ? WalkCheck::namedTime($phrase) : null;

            $errors[] = $this->error($path, $field, $time !== null
                ? "{$prefix} names a time ({$time}) ".$this->noStartTail()
                : "{$prefix} item ".($index + 1).' must be one of '.implode(', ', WalkCheck::vocabulary($afterSays)).' (you wrote '.PlainWords::value($phrase).')');
        }

        return $errors;
    }

    /**
     * Whether the walk writes at least one check, under any item or at the end.
     *
     * @param  array<string, mixed>  $front
     * @param  list<mixed>  $script
     */
    private function statesACheck(array $front, array $script): bool
    {
        if (is_array($front['expect'] ?? null) && $front['expect'] !== []) {
            return true;
        }

        foreach ($script as $item) {
            if (is_array($item) && is_array($item['expect'] ?? null) && $item['expect'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * A date and time in the business's zone, written "YYYY-MM-DD HH:MM", that is a real moment.
     */
    private function isStartTime(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value);

        return $time !== false && $time->format('Y-m-d H:i') === $value;
    }

    private function noStartTail(): string
    {
        return 'but the walk has no start. Name a start for the walk, or word the check as the earliest time offered.';
    }

    /**
     * @return array{file: string, field: ?string, message: string}
     */
    private function error(string $file, ?string $field, string $message): array
    {
        return ['file' => $file, 'field' => $field, 'message' => $message];
    }
}
