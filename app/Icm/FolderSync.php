<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Turns the business folder into the live version of the business.
 */
class FolderSync
{
    public function __construct(private Loader $loader) {}

    /**
     * Read the folder, compile it and make it the active version, unless another sync is running.
     */
    public function run(): SyncResult
    {
        $lock = Cache::lock('icm:sync', 120);

        if (! $lock->get()) {
            return new SyncResult(SyncOutcome::Busy, null, [], [], 0, 'A sync is already running. Try again in a moment.');
        }

        try {
            return $this->sync();
        } finally {
            $lock->release();
        }
    }

    private function sync(): SyncResult
    {
        $root = config('icm.path');
        $folder = $this->loader->load($root);

        $compiledFiles = collect($folder->files)
            ->filter(fn (IcmFile $file) => in_array($file->kind, [IcmFileKind::Typed, IcmFileKind::Router], true))
            ->sortBy('path', SORT_STRING)
            ->values();

        $fileCount = $compiledFiles->where('kind', IcmFileKind::Typed)->count();
        $fingerprint = $this->fingerprint($compiledFiles->all());

        $active = IcmVersion::query()->where('status', VersionStatus::Active->value)->latest('id')->first();

        if ($active?->fingerprint === $fingerprint) {
            $active->update(['last_checked_at' => now(), 'last_result' => SyncOutcome::Unchanged]);

            return new SyncResult(
                SyncOutcome::Unchanged,
                $active,
                [],
                $folder->notices,
                $active->file_count,
                "Already active: {$active->label()}, no changes",
            );
        }

        $version = DB::transaction(function () use ($compiledFiles, $fileCount, $fingerprint, $folder, $root) {
            IcmVersion::query()
                ->where('status', VersionStatus::Active->value)
                ->update(['status' => VersionStatus::Superseded->value]);

            return IcmVersion::create([
                'fingerprint' => $fingerprint,
                'git_sha' => $this->gitSha($root),
                'compiled' => $this->compile($compiledFiles->all()),
                'notices' => $folder->notices,
                'file_count' => $fileCount,
                'status' => VersionStatus::Active,
                'activated_at' => now(),
                'last_checked_at' => now(),
                'last_result' => SyncOutcome::Activated,
            ]);
        });

        app()->forgetInstance(ActiveConfig::class);

        return new SyncResult(
            SyncOutcome::Activated,
            $version,
            [],
            $folder->notices,
            $fileCount,
            "Live as {$version->label()} ({$fileCount} files)",
        );
    }

    /**
     * Hash exactly the files that go into the compiled version, so anything the app writes
     * into the folder (changelog.md, records/) can never mint a version.
     *
     * @param  list<IcmFile>  $files  Already sorted by path.
     */
    private function fingerprint(array $files): string
    {
        $content = '';

        foreach ($files as $file) {
            $content .= $file->path."\0".$file->raw."\0";
        }

        return hash('sha256', $content);
    }

    /**
     * @param  list<IcmFile>  $files
     * @return array{files: array<string, array{type: ?string, front_matter: array<string, mixed>, body: string, router: bool}>, index: array<string, mixed>}
     */
    private function compile(array $files): array
    {
        $compiled = ['files' => [], 'index' => ['service' => [], 'knowledge' => []]];

        foreach ($files as $file) {
            $compiled['files'][$file->path] = [
                'type' => $file->type,
                'front_matter' => $file->frontMatter,
                'body' => $file->body,
                'router' => $file->kind === IcmFileKind::Router,
            ];

            match ($file->type) {
                'service' => $compiled['index']['service'][$file->frontMatter['slug'] ?? $file->path] = $file->path,
                'knowledge' => $compiled['index']['knowledge'][] = $file->path,
                null => null,
                default => $compiled['index'][$file->type] = $file->path,
            };
        }

        return $compiled;
    }

    /**
     * The commit the folder is on, but only when the folder is a repository of its own.
     */
    private function gitSha(string $root): ?string
    {
        if (! is_dir($root.'/.git')) {
            return null;
        }

        $result = Process::path($root)->run(['git', 'rev-parse', 'HEAD']);

        return $result->successful() ? trim($result->output()) : null;
    }
}
