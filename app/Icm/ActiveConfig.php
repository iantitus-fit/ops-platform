<?php

namespace App\Icm;

use App\Enums\VersionStatus;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\IcmVersion;

/**
 * The only way application code reads business configuration.
 *
 * It answers from the compiled copy stored with the live version, never from the folder on disk.
 * Every accessor throws NoActiveVersion when nothing is live yet, except the ones that say they never throw:
 * displayTimezone(), hasRoom() and roomKeys().
 */
class ActiveConfig
{
    private ?IcmVersion $version = null;

    /**
     * The live version.
     *
     * @throws NoActiveVersion
     */
    public function version(): IcmVersion
    {
        return $this->version ??= IcmVersion::query()
            ->where('status', VersionStatus::Active->value)
            ->latest('id')
            ->first() ?? throw new NoActiveVersion;
    }

    /**
     * The id of the live version, for a record that must say which folder version it was made under.
     *
     * @throws NoActiveVersion
     */
    public function versionId(): int
    {
        return $this->version()->id;
    }

    /**
     * The business's time zone, such as "America/Chicago".
     */
    public function timezone(): string
    {
        return $this->business()['timezone'];
    }

    /**
     * The time zone to show times in: the business's, or the app's own when no version is live.
     * Unlike timezone(), this never throws, so a customer card can render before the first sync.
     */
    public function displayTimezone(): string
    {
        try {
            return $this->timezone();
        } catch (NoActiveVersion) {
            return config('app.timezone');
        }
    }

    /**
     * Whether the live version has this room: a CONTEXT.md directly inside rooms/<key>/ (D-01, D-03).
     * It never throws. Before the first sync, or with a version compiled before rooms existed, the answer is false.
     */
    public function hasRoom(string $key): bool
    {
        try {
            return isset($this->version()->compiled['index']['room'][$key]);
        } catch (NoActiveVersion) {
            return false;
        }
    }

    /**
     * The keys of the rooms the live version has, sorted. It never throws: an empty list before the first sync.
     *
     * @return list<string>
     */
    public function roomKeys(): array
    {
        try {
            $keys = array_keys($this->version()->compiled['index']['room'] ?? []);
        } catch (NoActiveVersion) {
            return [];
        }

        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * The business profile: its front matter plus the markdown body under "body".
     *
     * @return array<string, mixed>
     */
    public function business(): array
    {
        return $this->singleton('business');
    }

    /**
     * Weekly hours, closures and booking notice, plus the markdown body under "body".
     *
     * @return array<string, mixed>
     */
    public function hours(): array
    {
        return $this->singleton('hours');
    }

    /**
     * Tone and disclosure rules, plus the markdown body under "body".
     *
     * @return array<string, mixed>
     */
    public function voice(): array
    {
        return $this->singleton('voice');
    }

    /**
     * What may be sent on its own and what waits for the owner, plus the markdown body under "body".
     *
     * @return array<string, mixed>
     */
    public function sendingRules(): array
    {
        return $this->singleton('sending_rules');
    }

    /**
     * The owner-written fixed replies and the owner's name, plus the markdown body under "body".
     *
     * @return array<string, mixed>
     */
    public function replies(): array
    {
        return $this->singleton('set_replies');
    }

    /**
     * The safety check's questions: the intent list, the intent question and the six yes or no
     * questions, plus the markdown body under "body".
     *
     * @return array<string, mixed>
     */
    public function triage(): array
    {
        return $this->singleton('triage');
    }

    /**
     * Every lead source with the cost of one lead in integer cents.
     *
     * @return list<array{key: string, name: string, cost_cents_per_lead: int}>
     */
    public function leadSources(): array
    {
        $sources = $this->singleton('lead_sources')['sources'] ?? [];

        return array_values(array_map(fn (array $source) => [
            'key' => $source['key'],
            'name' => $source['name'],
            'cost_cents_per_lead' => $source['cost_cents_per_lead'],
        ], $sources));
    }

    /**
     * One lead source by its key, or null when the key is not in lead-sources.md.
     *
     * @return array{key: string, name: string, cost_cents_per_lead: int}|null
     */
    public function leadSource(string $key): ?array
    {
        foreach ($this->leadSources() as $source) {
            if ($source['key'] === $key) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Every service: its front matter plus the markdown body under "body".
     *
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        return array_values(array_map(
            fn (string $path) => $this->typedFile($path),
            $this->version()->compiled['index']['service'] ?? [],
        ));
    }

    /**
     * One service by its slug, or null when there is no such service.
     *
     * @return array<string, mixed>|null
     */
    public function service(string $slug): ?array
    {
        $path = $this->version()->compiled['index']['service'][$slug] ?? null;

        return $path === null ? null : $this->typedFile($path);
    }

    /**
     * Every offer: its front matter plus the markdown body under "body" and its folder path under "path",
     * in path order. A version compiled before offers existed has none.
     *
     * @return list<array<string, mixed>>
     */
    public function offers(): array
    {
        $paths = $this->version()->compiled['index']['offer'] ?? [];
        $paths = array_values($paths);
        sort($paths, SORT_STRING);

        return array_map(fn (string $path) => [...$this->typedFile($path), 'path' => $path], $paths);
    }

    /**
     * One offer by its slug, or null when there is no such offer.
     *
     * @return array<string, mixed>|null
     */
    public function offer(string $slug): ?array
    {
        $path = $this->version()->compiled['index']['offer'][$slug] ?? null;

        return $path === null ? null : [...$this->typedFile($path), 'path' => $path];
    }

    /**
     * Every file of a type a room defines: its front matter plus the markdown body under "body" and its folder path
     * under "path", in path order. A version compiled before the type existed has none.
     *
     * @return list<array<string, mixed>>
     */
    public function filesOfType(string $type): array
    {
        $paths = array_values($this->version()->compiled['index'][$type] ?? []);
        sort($paths, SORT_STRING);

        return array_map(fn (string $path) => [...$this->typedFile($path), 'path' => $path], $paths);
    }

    /**
     * The agent that serves one channel such as "sms": its front matter plus the markdown body
     * under "body", or null when no agent file serves that channel.
     *
     * @return array<string, mixed>|null
     */
    public function agent(string $channel): ?array
    {
        $path = $this->version()->compiled['index']['agent'][$channel] ?? null;

        return $path === null ? null : $this->typedFile($path);
    }

    /**
     * Every compiled file in the live version. Router files have no type.
     *
     * @return list<array{path: string, type: ?string}>
     */
    public function files(): array
    {
        $files = [];

        foreach ($this->version()->compiled['files'] as $path => $file) {
            $files[] = ['path' => $path, 'type' => $file['type']];
        }

        return $files;
    }

    /**
     * One compiled file by its path in the folder, or null when it is not part of the live version.
     *
     * @return array{type: ?string, front_matter: array<string, mixed>, body: string}|null
     */
    public function file(string $path): ?array
    {
        $file = $this->version()->compiled['files'][$path] ?? null;

        if ($file === null) {
            return null;
        }

        return [
            'type' => $file['type'],
            'front_matter' => $file['front_matter'],
            'body' => $file['body'],
        ];
    }

    /**
     * Forget the remembered version so the next read looks it up again.
     */
    public function refresh(): void
    {
        $this->version = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function singleton(string $type): array
    {
        return $this->typedFile($this->version()->compiled['index'][$type]);
    }

    /**
     * @return array<string, mixed>
     */
    private function typedFile(string $path): array
    {
        $file = $this->version()->compiled['files'][$path];

        return [...$file['front_matter'], 'body' => $file['body']];
    }
}
