<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator as LaravelValidator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Ai\Ai;
use LogicException;

/**
 * Checks every file of a loaded folder and describes each problem in plain words.
 */
class Validator
{
    /**
     * Types the folder must hold exactly once, with the path the owner is told about when one is missing.
     *
     * @var array<string, string>
     */
    private const SINGLETONS = [
        'business' => 'business/profile.md',
        'hours' => 'business/hours.md',
        'lead_sources' => 'business/lead-sources.md',
        'voice' => 'voice/how-we-talk.md',
        'sending_rules' => 'rules/sending.md',
    ];

    /**
     * The largest money amount an owner may write, in cents ($1,000,000). The database stores
     * these values in a 32-bit column on PostgreSQL, so a larger number would pass here and then
     * fail every inbound from that source or service.
     */
    private const int MAX_CENTS = 100_000_000;

    /**
     * @var list<string>
     */
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * The providers an agent file may name, worked out once per validation run.
     *
     * @var list<string>|null
     */
    private ?array $usableProviders = null;

    /**
     * Every problem in the folder. A folder with no problems may go live.
     */
    public function validate(LoadedFolder $folder): ValidationReport
    {
        $files = collect($folder->files)->sortBy('path', SORT_STRING)->values();
        $errors = [];
        $this->usableProviders = null;

        foreach ($files as $file) {
            if ($file->kind === IcmFileKind::Unrecognized) {
                $errors[] = $this->error($file->path, null, $this->problemMessage((string) $file->problem));
            }

            if ($file->kind === IcmFileKind::Typed) {
                array_push($errors, ...$this->fileErrors($file));
            }
        }

        array_push($errors, ...$this->duplicateSlugErrors($files), ...$this->singletonErrors($files), ...$this->agentChannelErrors($files), ...$this->agentLoadsErrors($files));

        return new ValidationReport($errors);
    }

    /**
     * What is wrong inside one typed file.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function fileErrors(IcmFile $file): array
    {
        $errors = [];
        $validator = LaravelValidator::make($file->frontMatter, $this->rulesFor($file));

        if ($validator->fails()) {
            foreach ($validator->failed() as $field => $failures) {
                foreach ($failures as $rule => $parameters) {
                    $errors[] = $this->error($file->path, $field, PlainWords::render($rule, $parameters, $field, Arr::get($file->frontMatter, $field)));
                }
            }
        }

        array_push($errors, ...match ($file->type) {
            'hours' => $this->hoursErrors($file),
            'lead_sources' => $this->repeatedKeyErrors($file, 'sources', 'sources has more than one entry with key'),
            'service' => $this->repeatedKeyErrors($file, 'intake', 'intake has more than one question with key'),
            'agent' => $this->agentErrors($file),
            default => [],
        });

        return $errors;
    }

    /**
     * The rules for one file. `bail` keeps a single problem from being reported twice, and
     * `present|array` (not `required`) lets a list be empty.
     *
     * @return array<string, mixed>
     */
    private function rulesFor(IcmFile $file): array
    {
        return match ($file->type) {
            'business' => [
                'name' => 'bail|required|string',
                'phone' => 'bail|required|string',
                'timezone' => 'bail|required|string|timezone:all',
                'service_area' => 'bail|required|array|min:1',
                'service_area.*' => 'bail|string',
            ],
            'hours' => [
                'weekly' => 'bail|required|array',
                ...$this->dayRules($file->frontMatter['weekly'] ?? null),
                'closures' => 'bail|present|array',
                'closures.*' => 'bail|date_format:Y-m-d',
                'booking_lead_minutes' => 'bail|required|integer:strict|min:0',
            ],
            'lead_sources' => [
                'sources' => 'bail|required|array|min:1',
                'sources.*.key' => ['bail', 'required', 'string', 'regex:/^[a-z0-9]+([_-][a-z0-9]+)*$/'],
                'sources.*.name' => 'bail|required|string',
                'sources.*.cost_cents_per_lead' => 'bail|required|integer:strict|min:0|max:'.self::MAX_CENTS,
            ],
            'service' => [
                'name' => 'bail|required|string',
                'slug' => ['bail', 'required', 'string', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
                'duration_minutes' => 'bail|required|integer:strict|min:1',
                'book_online' => 'bail|required|boolean:strict',
                'price_policy' => ['bail', 'required', Rule::in(['none', 'starting_at', 'fixed'])],
                'price_cents' => 'bail|required_unless:price_policy,none|integer:strict|min:0|max:'.self::MAX_CENTS,
                'intake' => 'bail|required|array|min:1',
                'intake.*.key' => ['bail', 'required', 'string', 'regex:/^[a-z0-9]+(_[a-z0-9]+)*$/'],
                'intake.*.ask' => 'bail|required|string',
                'intake.*.options' => 'bail|sometimes|array',
                'intake.*.options.*' => 'bail|string',
            ],
            'voice' => [
                'ai_disclosure' => 'bail|required|string',
                'sign_off' => 'bail|required|string',
                'never_say' => 'bail|present|array',
                'never_say.*' => 'bail|string',
            ],
            'sending_rules' => [
                'send_on_own' => 'bail|present|array',
                'send_on_own.*' => 'bail|string',
                'draft_for_owner' => 'bail|present|array',
                'draft_for_owner.*' => 'bail|string',
                'owner_alert' => 'bail|required',
            ],
            'agent' => [
                'name' => 'bail|required|string',
                'channel' => ['bail', 'required', Rule::in(['sms'])],
                'provider' => 'bail|required|string',
                'model' => 'bail|required|string',
                'fallback' => 'bail|present|array',
                'fallback.*.provider' => 'bail|required|string',
                'fallback.*.model' => 'bail|required|string',
                'loads' => 'bail|present|array|min:1',
                'loads.*' => 'bail|string',
                'escalate_to' => ['bail', 'required', Rule::in(['owner'])],
            ],
            default => [],
        };
    }

    /**
     * A day is either the word "closed" or a map with open and close times, so the times are
     * only checked for days written as a map.
     *
     * @return array<string, string>
     */
    private function dayRules(mixed $weekly): array
    {
        if (! is_array($weekly)) {
            return [];
        }

        $rules = [];

        foreach (self::DAYS as $day) {
            if (! array_key_exists($day, $weekly)) {
                $rules["weekly.{$day}"] = 'required';
            } elseif (is_array($weekly[$day])) {
                $rules["weekly.{$day}.open"] = 'bail|required|date_format:H:i';
                $rules["weekly.{$day}.close"] = 'bail|required|date_format:H:i';
            }
        }

        return $rules;
    }

    /**
     * The opening hours checks a rule string cannot express.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function hoursErrors(IcmFile $file): array
    {
        $weekly = $file->frontMatter['weekly'] ?? null;
        $errors = [];

        if (! is_array($weekly)) {
            return $errors;
        }

        foreach (self::DAYS as $day) {
            if (! array_key_exists($day, $weekly) || $weekly[$day] === 'closed') {
                continue;
            }

            $hours = $weekly[$day];

            if (! is_array($hours)) {
                $errors[] = $this->error($file->path, "weekly.{$day}", "weekly {$day} must be closed or have open and close times (you wrote ".PlainWords::value($hours).')');

                continue;
            }

            $open = $hours['open'] ?? null;
            $close = $hours['close'] ?? null;

            if ($this->isTime($open) && $this->isTime($close) && strcmp($close, $open) <= 0) {
                $errors[] = $this->error($file->path, "weekly.{$day}", "weekly {$day}: close must be later than open (you wrote {$open} to {$close})");
            }
        }

        return $errors;
    }

    private function isTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    /**
     * One error for each key written more than once in a list of entries.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function repeatedKeyErrors(IcmFile $file, string $field, string $sentence): array
    {
        $entries = $file->frontMatter[$field] ?? null;

        if (! is_array($entries)) {
            return [];
        }

        $seen = [];
        $reported = [];
        $errors = [];

        foreach ($entries as $entry) {
            $key = is_array($entry) ? ($entry['key'] ?? null) : null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            if (isset($seen[$key]) && ! isset($reported[$key])) {
                $reported[$key] = true;
                $errors[] = $this->error($file->path, $field, "{$sentence} ".PlainWords::oneLine($key));
            }

            $seen[$key] = true;
        }

        return $errors;
    }

    /**
     * What is wrong with the models an agent file names: a provider this app cannot use or that cannot
     * write replies, and a chain entry that repeats an earlier one.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function agentErrors(IcmFile $file): array
    {
        $errors = [];
        $chain = [['field' => 'provider', 'prefix' => '', 'provider' => $file->frontMatter['provider'] ?? null, 'model' => $file->frontMatter['model'] ?? null]];
        $fallback = $file->frontMatter['fallback'] ?? null;

        foreach (is_array($fallback) ? array_values($fallback) : [] as $index => $entry) {
            $number = $index + 1;
            $chain[] = [
                'field' => "fallback.{$index}.provider",
                'prefix' => "fallback item {$number}: ",
                'provider' => is_array($entry) ? ($entry['provider'] ?? null) : null,
                'model' => is_array($entry) ? ($entry['model'] ?? null) : null,
                'number' => $number,
            ];
        }

        $seen = [];

        foreach ($chain as $position => $entry) {
            $provider = $entry['provider'];
            $model = $entry['model'];

            if (is_string($provider) && $provider !== '') {
                $problem = $this->providerProblem($provider);

                if ($problem !== null) {
                    $errors[] = $this->error($file->path, $entry['field'], $entry['prefix'].$problem);
                }
            }

            if (! is_string($provider) || $provider === '' || ! is_string($model) || $model === '') {
                continue;
            }

            $key = $provider."\0".$model;

            if ($position > 0 && isset($seen[$key])) {
                $errors[] = $this->error($file->path, "fallback.{$entry['number']}", "fallback item {$entry['number']} repeats ".PlainWords::oneLine($provider).' '.PlainWords::oneLine($model).'; each entry must be different');
            }

            $seen[$key] ??= true;
        }

        return $errors;
    }

    /**
     * Why a provider name cannot answer texts, or null when it can. The name must match a key of
     * config/ai.php exactly, and Ai::textProvider() builds the provider without calling it, so no key
     * or network is needed.
     */
    private function providerProblem(string $name): ?string
    {
        if (! array_key_exists($name, (array) config('ai.providers'))) {
            return 'provider '.PlainWords::oneLine($name).' is not a provider this app can use; use one of '.implode(', ', $this->usableProviders());
        }

        try {
            Ai::textProvider($name);
        } catch (InvalidArgumentException) {
            return 'provider '.PlainWords::oneLine($name).' is not a provider this app can use; use one of '.implode(', ', $this->usableProviders());
        } catch (LogicException) {
            return 'provider '.PlainWords::oneLine($name).' cannot write replies; use one of '.implode(', ', $this->usableProviders());
        }

        return null;
    }

    /**
     * Every provider in config/ai.php that can write text, sorted.
     *
     * @return list<string>
     */
    private function usableProviders(): array
    {
        if ($this->usableProviders !== null) {
            return $this->usableProviders;
        }

        $usable = [];

        foreach (array_keys((array) config('ai.providers')) as $name) {
            try {
                Ai::textProvider((string) $name);
                $usable[] = (string) $name;
            } catch (InvalidArgumentException|LogicException) {
                continue;
            }
        }

        sort($usable);

        return $this->usableProviders = $usable;
    }

    /**
     * Two agent files may not serve the same channel; the file later in path order gets the error.
     *
     * @param  Collection<int, IcmFile>  $files  Sorted by path.
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function agentChannelErrors(Collection $files): array
    {
        $owners = [];
        $errors = [];

        foreach ($files as $file) {
            $channel = $file->frontMatter['channel'] ?? null;

            if ($file->kind !== IcmFileKind::Typed || $file->type !== 'agent' || ! is_string($channel) || $channel === '') {
                continue;
            }

            if (isset($owners[$channel])) {
                $errors[] = $this->error($file->path, 'channel', 'channel '.PlainWords::oneLine($channel)." already has an agent in {$owners[$channel]}; the folder can have one agent per channel");
            } else {
                $owners[$channel] = $file->path;
            }
        }

        return $errors;
    }

    /**
     * Every loads entry must name a file, or a folder ending in / that holds a typed file, in the folder.
     * A file that cannot be read still counts as present, because its own error is already reported.
     * records/ is never loaded: the customer card is added by code, not by a path in the file.
     *
     * @param  Collection<int, IcmFile>  $files  Sorted by path.
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function agentLoadsErrors(Collection $files): array
    {
        $typedPaths = $files->filter(fn (IcmFile $file) => $file->kind === IcmFileKind::Typed)->map(fn (IcmFile $file) => $file->path)->all();
        $loadablePaths = $files->filter(fn (IcmFile $file) => $file->kind !== IcmFileKind::LaterSprint)->map(fn (IcmFile $file) => $file->path)->all();
        $errors = [];

        foreach ($files as $file) {
            $loads = $file->frontMatter['loads'] ?? null;

            if ($file->kind !== IcmFileKind::Typed || $file->type !== 'agent' || ! is_array($loads)) {
                continue;
            }

            foreach (array_values($loads) as $index => $entry) {
                if (! is_string($entry)) {
                    continue;
                }

                $number = $index + 1;
                $shown = PlainWords::oneLine($entry);

                if (preg_match('#^(\./)?records(/|$)#', $entry) === 1) {
                    $errors[] = $this->error($file->path, "loads.{$index}", "loads item {$number}: {$shown} is never loaded; the customer card is added on its own");

                    continue;
                }

                $found = str_ends_with($entry, '/')
                    ? collect($typedPaths)->contains(fn (string $path) => str_starts_with($path, $entry))
                    : in_array($entry, $loadablePaths, true);

                if (! $found) {
                    $errors[] = $this->error($file->path, "loads.{$index}", "loads item {$number}: {$shown} matches no file in the folder");
                }
            }
        }

        return $errors;
    }

    /**
     * Two service files may not share a slug; the file later in path order gets the error.
     *
     * @param  Collection<int, IcmFile>  $files  Sorted by path.
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function duplicateSlugErrors(Collection $files): array
    {
        $owners = [];
        $errors = [];

        foreach ($files as $file) {
            $slug = $file->frontMatter['slug'] ?? null;

            if ($file->kind !== IcmFileKind::Typed || $file->type !== 'service' || ! is_string($slug) || $slug === '') {
                continue;
            }

            if (isset($owners[$slug])) {
                $errors[] = $this->error($file->path, 'slug', 'slug '.PlainWords::oneLine($slug)." is already used by {$owners[$slug]}");
            } else {
                $owners[$slug] = $file->path;
            }
        }

        return $errors;
    }

    /**
     * Business, hours, lead sources, voice and sending rules must each exist exactly once.
     * The file at the usual path owns the type; any other file with that type gets the error.
     *
     * @param  Collection<int, IcmFile>  $files  Sorted by path.
     * @return list<array{file: string, field: ?string, message: string}>
     */
    private function singletonErrors(Collection $files): array
    {
        $errors = [];

        foreach (self::SINGLETONS as $type => $defaultPath) {
            $matching = $files->filter(fn (IcmFile $file) => $file->kind === IcmFileKind::Typed && $file->type === $type)->values();

            if ($matching->isEmpty()) {
                $unreadableAtDefaultPath = $files->contains(fn (IcmFile $file) => $file->path === $defaultPath && $file->kind === IcmFileKind::Unrecognized);

                if (! $unreadableAtDefaultPath) {
                    $errors[] = $this->error($defaultPath, null, "no file has type: {$type}, and the folder needs exactly one");
                }

                continue;
            }

            $owner = $matching->firstWhere('path', $defaultPath) ?? $matching->first();

            foreach ($matching->reject(fn (IcmFile $file) => $file === $owner) as $extra) {
                $errors[] = $this->error($extra->path, 'type', "type {$type} is already used by {$owner->path}; the folder needs exactly one");
            }
        }

        return $errors;
    }

    /**
     * The sentence for a file the Loader could not use.
     */
    private function problemMessage(string $problem): string
    {
        if (str_starts_with($problem, 'unknown_type:')) {
            return PlainWords::render('In', Loader::TYPED, 'type', substr($problem, strlen('unknown_type:')));
        }

        if (str_starts_with($problem, 'unreadable:')) {
            return $this->unreadableMessage(substr($problem, strlen('unreadable:')));
        }

        return match ($problem) {
            'no_front_matter' => 'the file must start with front matter (a --- line, then type: and its fields, then another --- line)',
            'empty' => 'the front matter is empty; add type: and the fields for that type',
            'missing_type' => PlainWords::render('Required', [], 'type', null),
            default => 'the file could not be used',
        };
    }

    /**
     * The YAML parser's own words, kept to the problem and the file line.
     */
    private function unreadableMessage(string $parserMessage): string
    {
        if (preg_match('/^(.*?) at line (\d+)/s', $parserMessage, $matches) === 1) {
            return "the front matter could not be read at line {$matches[2]}: ".PlainWords::oneLine(rtrim($matches[1], '.'));
        }

        return 'the front matter could not be read: '.PlainWords::oneLine(rtrim($parserMessage, '.'));
    }

    /**
     * @return array{file: string, field: ?string, message: string}
     */
    private function error(string $file, ?string $field, string $message): array
    {
        return ['file' => $file, 'field' => $field, 'message' => $message];
    }
}
