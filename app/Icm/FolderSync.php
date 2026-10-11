<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Icm\Exceptions\NoActiveVersion;
use App\Models\IcmVersion;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Turns the business folder into the live version of the business.
 */
class FolderSync
{
    public function __construct(
        private Loader $loader,
        private Validator $validator,
        private Changelog $changelog,
    ) {}

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

        $report = $this->validator->validate($folder);

        if ($report->hasErrors()) {
            return $this->reject($folder, $report, $fileCount, $fingerprint, $active, $root);
        }

        if ($active?->fingerprint === $fingerprint) {
            $active->update(['last_checked_at' => now(), 'last_result' => SyncOutcome::Unchanged]);

            return $this->recorded(new SyncResult(
                SyncOutcome::Unchanged,
                $active,
                [],
                $folder->notices,
                $active->file_count,
                "Already active: {$active->label()}, no changes",
            ), $folder, $report);
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

        return $this->recorded(new SyncResult(
            SyncOutcome::Activated,
            $version,
            [],
            $folder->notices,
            $fileCount,
            "Live as {$version->label()} ({$fileCount} files)",
        ), $folder, $report);
    }

    /**
     * Store the candidate as a rejected version, record why, and leave the live version alone.
     */
    private function reject(LoadedFolder $folder, ValidationReport $report, int $fileCount, string $fingerprint, ?IcmVersion $active, string $root): SyncResult
    {
        $lines = $report->lines();

        $rejected = IcmVersion::create([
            'fingerprint' => $fingerprint,
            'git_sha' => $this->gitSha($root),
            'compiled' => null,
            'errors' => $lines,
            'notices' => $folder->notices,
            'file_count' => $fileCount,
            'status' => VersionStatus::Rejected,
            'last_checked_at' => now(),
            'last_result' => SyncOutcome::Rejected,
        ]);

        $message = $active === null ? 'Rejected. Nothing is live yet.' : "Rejected. {$active->label()} stays live.";
        $result = new SyncResult(SyncOutcome::Rejected, $rejected, $lines, $folder->notices, $fileCount, $message);

        return $this->recorded($result, $folder, $report);
    }

    /**
     * Add the run to changelog.md in the right time zone, then hand the result back.
     *
     * The version is already live (or the rejection already stored) by now, so a changelog that
     * cannot be written is reported as a notice on the result and never fails the sync.
     */
    private function recorded(SyncResult $result, LoadedFolder $folder, ValidationReport $report): SyncResult
    {
        [$timezone, $note] = $this->changelogClock($folder, $report);

        try {
            $this->changelog->record($result, $timezone, $note);
        } catch (Exception $exception) {
            return new SyncResult(
                $result->outcome,
                $result->version,
                $result->errors,
                [...$result->notices, "changelog.md could not be updated: {$exception->getMessage()}"],
                $result->fileCount,
                $result->message,
            );
        }

        return $result;
    }

    /**
     * The time zone for a changelog entry, and the note that explains it when it is only a fallback.
     * The candidate's own zone is used when its profile is sound, otherwise the live version's, and
     * with nothing live the app's own zone.
     *
     * @return array{0: string, 1: ?string}
     */
    private function changelogClock(LoadedFolder $folder, ValidationReport $report): array
    {
        $profile = collect($folder->files)->first(fn (IcmFile $file) => $file->kind === IcmFileKind::Typed && $file->type === 'business');
        $candidate = $profile?->frontMatter['timezone'] ?? null;

        if ($profile !== null && ! $report->hasErrorsFor($profile->path) && is_string($candidate) && in_array($candidate, timezone_identifiers_list(), true)) {
            return [$candidate, null];
        }

        try {
            $config = app(ActiveConfig::class);
            $config->refresh();

            return [$config->timezone(), null];
        } catch (NoActiveVersion) {
            $fallback = config('app.timezone');

            return [$fallback, "Times are in {$fallback} because business/profile.md could not be read and no version is live yet"];
        }
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
