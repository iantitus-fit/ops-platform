<?php

namespace App\Icm\Walks;

/**
 * A walk file the sync has already accepted, as a value the gate's runner can play: who the customer is, the optional
 * start time in the business's zone, the script as numbered steps, and the checks that apply at the end.
 */
readonly class WalkFile
{
    /**
     * @param  list<WalkStep>  $steps
     * @param  list<WalkCheck>  $endChecks
     */
    public function __construct(
        public string $path,
        public string $name,
        public string $persona,
        public string $channel,
        public ?string $start,
        public array $steps,
        public array $endChecks,
    ) {}

    /**
     * Build one from an entry of ActiveConfig::filesOfType('walk_test'): the front matter plus "body" and "path". The
     * file is assumed to have passed the sync's checks already, so nothing here refuses; an item that cannot be read
     * is skipped.
     *
     * @param  array<string, mixed>  $file
     */
    public static function fromCompiled(array $file): self
    {
        $steps = [];

        foreach (array_values((array) ($file['script'] ?? [])) as $index => $item) {
            if (is_array($item) && ($step = self::step($index + 1, $item)) !== null) {
                $steps[] = $step;
            }
        }

        return new self(
            path: (string) ($file['path'] ?? ''),
            name: (string) ($file['name'] ?? ''),
            persona: (string) ($file['persona'] ?? ''),
            channel: (string) ($file['channel'] ?? 'sms'),
            start: isset($file['start']) ? (string) $file['start'] : null,
            steps: $steps,
            endChecks: self::checks($file['expect'] ?? []),
        );
    }

    /**
     * The customer's texts in the order the script sends them.
     *
     * @return list<string>
     */
    public function customerTurns(): array
    {
        $texts = [];

        foreach ($this->steps as $step) {
            if ($step->kind === WalkStepKind::Says) {
                $texts[] = (string) $step->says;
            }
        }

        return $texts;
    }

    /**
     * @param  array<array-key, mixed>  $item
     */
    private static function step(int $turn, array $item): ?WalkStep
    {
        $checks = self::checks($item['expect'] ?? []);

        if (isset($item['says'])) {
            return new WalkStep($turn, WalkStepKind::Says, (string) $item['says'], null, null, $checks);
        }

        if (isset($item['wait'])) {
            return new WalkStep($turn, WalkStepKind::Wait, null, WalkStep::waitMinutes((string) $item['wait']), null, $checks);
        }

        if (isset($item['books'])) {
            $books = trim((string) $item['books']);

            return new WalkStep($turn, WalkStepKind::Books, null, null, mb_strtolower($books) === 'the earliest time offered' ? null : $books, $checks);
        }

        return null;
    }

    /**
     * @return list<WalkCheck>
     */
    private static function checks(mixed $phrases): array
    {
        $checks = [];

        foreach (is_array($phrases) ? $phrases : [] as $phrase) {
            if (is_string($phrase) && ($check = WalkCheck::parse($phrase)) !== null) {
                $checks[] = $check;
            }
        }

        return $checks;
    }
}
