<?php

namespace App\Icm;

use App\Enums\VersionStatus;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\IcmVersion;

/**
 * The only way application code reads business configuration.
 *
 * It answers from the compiled copy stored with the live version, never from the folder on disk.
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
     * The business's time zone, such as "America/Chicago".
     */
    public function timezone(): string
    {
        $compiled = $this->version()->compiled;

        return $compiled['files'][$compiled['index']['business']]['front_matter']['timezone'];
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
}
