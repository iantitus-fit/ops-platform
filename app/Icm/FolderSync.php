<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Events\CustomerRecordChanged;
use App\Events\ScheduleChanged;
use App\Icm\Exceptions\NoActiveVersion;
use App\Icm\Walks\WalkCandidate;
use App\Icm\Walks\WalkGate;
use App\Icm\Walks\WalkGateResult;
use App\Models\Activity;
use App\Models\IcmVersion;
use App\Rooms\RoomRegistry;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Turns the business folder into the live version of the business.
 */
class FolderSync
{
    public function __construct(
        private Loader $loader,
        private Validator $validator,
        private Changelog $changelog,
        private RoomRegistry $rooms,
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

        // Built here from this sync's own registry, not injected: the constructor keeps its four parameters, so a
        // FolderSync wired by hand with a test-only registry still works and gets no check for a room it lacks.
        $dropErrors = (new RoomDropChecks($this->rooms, new RoomChecks($this->rooms)))->errors($folder, $active);

        if ($dropErrors !== []) {
            $report = new ValidationReport([...$report->errors, ...$dropErrors]);
        }

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

        $walks = $this->walks($compiledFiles->all(), $active);

        if ($walks->refused()) {
            return $this->rejectedByWalks($folder, $report, $walks, $fileCount, $fingerprint, $active, $root);
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

        $this->queueCardsForRoomChanges($active, $version);

        return $this->recorded(new SyncResult(
            SyncOutcome::Activated,
            $version,
            [],
            $folder->notices,
            $fileCount,
            "Live as {$version->label()} ({$fileCount} files)",
            staleNotes: $walks->staleNotes(),
        ), $folder, $report);
    }

    /**
     * When a sync turns a room off or on, redraw the cards that hold that room's entries. A card in records/ is drawn
     * through Activity::visibleOnTimeline() only when it is rendered, so without this a removed room's entries would
     * stay on the card the owner's AI reads until the customer's next event, and would stay missing after the folder
     * came back. Every contact with a row of a room that went off or on is queued once (Ian, 2026-10-22).
     *
     * The version is already live by now, so a card that cannot be queued is reported and never fails the sync, and one
     * failed card does not stop the cards after it.
     *
     * Turning the owner intelligence room or the schedule and booking room off or on between two live versions also
     * redraws records/today.md and records/time-to-booked.md at once, so an off room's files are deleted and an on room's
     * are written without waiting for midnight, and the router records/CLAUDE.md follows in the same step (D-15, D-25).
     *
     * The first sync of a new database has no room file to remove, so the first booking change or the nightly run
     * writes them.
     */
    private function queueCardsForRoomChanges(?IcmVersion $previous, IcmVersion $version): void
    {
        $before = array_keys($previous?->compiled['index']['room'] ?? []);
        $after = array_keys($version->compiled['index']['room'] ?? []);
        $changed = array_values(array_unique([...array_diff($before, $after), ...array_diff($after, $before)]));

        if ($changed === []) {
            return;
        }

        if ($previous !== null && array_intersect($changed, ['owner_intelligence', 'schedule_and_booking']) !== []) {
            try {
                ScheduleChanged::dispatch();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        try {
            $contactIds = Activity::query()
                ->whereIn('room', $changed)
                ->distinct()
                ->orderBy('contact_id')
                ->pluck('contact_id');
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        foreach ($contactIds as $contactId) {
            try {
                CustomerRecordChanged::dispatch((int) $contactId);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
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
                $result->changelogErrors,
                $result->staleNotes,
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
        $compiled = ['files' => [], 'index' => ['service' => [], 'knowledge' => [], 'agent' => [], 'offer' => [], 'room' => [], 'connector' => [], 'walk_test' => []]];

        foreach ($files as $file) {
            $compiled['files'][$file->path] = [
                'type' => $file->type,
                'front_matter' => $file->frontMatter,
                'body' => $file->body,
                'router' => $file->kind === IcmFileKind::Router,
            ];

            if (in_array($file->type, $this->rooms->registeredTypes(), true)) {
                $compiled['index'][$file->type][] = $file->path;

                continue;
            }

            match ($file->type) {
                'service' => $compiled['index']['service'][$file->frontMatter['slug'] ?? $file->path] = $file->path,
                'offer' => $compiled['index']['offer'][$file->frontMatter['slug'] ?? $file->path] = $file->path,
                'knowledge' => $compiled['index']['knowledge'][] = $file->path,
                'agent' => $compiled['index']['agent'][$file->frontMatter['channel'] ?? $file->path] = $file->path,
                'room' => preg_match(Loader::ROOM_CONTRACT, $file->path, $room) === 1 ? $compiled['index']['room'][$room[1]] = $file->path : null,
                'connector' => $compiled['index']['connector'][] = $file->path,
                'walk_test' => $compiled['index']['walk_test'][] = $file->path,
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

    /**
     * Replay every walk of the candidate against it, before anything is activated (10 WALK-02). The candidate is compiled
     * here for the walks to read; the copy that is stored is still compiled inside the activation transaction.
     *
     * @param  list<IcmFile>  $files  Already sorted by path.
     */
    private function walks(array $files, ?IcmVersion $active): WalkGateResult
    {
        return app(WalkGate::class)->run($this->compile($files), $active);
    }

    /**
     * Store the candidate as a rejected version because a walk failed, had no recording, needs a new one or stopped with an
     * error, and leave the live version alone. It mirrors reject(): the stored errors and the sync's errors are the owner's
     * lines, which the Business Folder page and the Sync Now notification already print, and changelog.md gets Ian's lines
     * and the stale notes.
     */
    private function rejectedByWalks(LoadedFolder $folder, ValidationReport $report, WalkGateResult $walks, int $fileCount, string $fingerprint, ?IcmVersion $active, string $root): SyncResult
    {
        $lines = $walks->ownerLines($active?->label());

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

        return $this->recorded(new SyncResult(
            SyncOutcome::Rejected,
            $rejected,
            $lines,
            $folder->notices,
            $fileCount,
            $message,
            $walks->ianLines($active?->label()),
            $walks->staleNotes(),
        ), $folder, $report);
    }

    /**
     * The folder as it is on disk, read for the walks and never made live: the same load, the same validation and room drop
     * checks and the same compile as a sync, so a walk recorded against it is played against exactly what a sync would
     * activate. It writes no version and runs no walk, and it does not take the sync lock.
     */
    public function candidateForWalks(): WalkCandidate
    {
        $folder = $this->loader->load(config('icm.path'));

        $compiledFiles = collect($folder->files)
            ->filter(fn (IcmFile $file) => in_array($file->kind, [IcmFileKind::Typed, IcmFileKind::Router], true))
            ->sortBy('path', SORT_STRING)
            ->values();

        $active = IcmVersion::query()->where('status', VersionStatus::Active->value)->latest('id')->first();

        $report = $this->validator->validate($folder);
        $dropErrors = (new RoomDropChecks($this->rooms, new RoomChecks($this->rooms)))->errors($folder, $active);

        if ($dropErrors !== []) {
            $report = new ValidationReport([...$report->errors, ...$dropErrors]);
        }

        if ($report->hasErrors()) {
            return new WalkCandidate(null, $report->lines(), $active);
        }

        return new WalkCandidate($this->compile($compiledFiles->all()), [], $active);
    }
}
