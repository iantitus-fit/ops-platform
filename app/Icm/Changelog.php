<?php

namespace App\Icm;

use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;
use LogicException;

/**
 * Writes the sync's changelog.md in the business folder, newest entry first.
 */
class Changelog
{
    private const HEADER = "# Changelog\n\nWritten by the sync. Every attempt, pass or fail, newest first.\n";

    /**
     * Add one entry for what this sync run did.
     *
     * The file is written to a temporary file and renamed into place, so an interrupted write
     * leaves the previous changelog intact.
     */
    public function record(SyncResult $result, string $timezone, ?string $timezoneNote = null): void
    {
        $path = rtrim(config('icm.path'), '/').'/changelog.md';
        $existing = File::exists($path) ? File::get($path) : self::HEADER;

        File::replace($path, $this->withEntry($existing, $this->entry($result, $timezone, $timezoneNote)));
    }

    /**
     * The heading and bullets for one sync run.
     */
    private function entry(SyncResult $result, string $timezone, ?string $timezoneNote): string
    {
        $time = now($timezone)->format('Y-m-d H:i');

        [$title, $bullets] = match ($result->outcome) {
            SyncOutcome::Rejected => ['Rejected', [...($result->changelogErrors ?: $result->errors), ...$result->staleNotes, $this->liveBullet()]],
            SyncOutcome::Activated => [
                "Live as {$result->version->label()} ({$result->fileCount} files)",
                [...array_map($this->noticeBullet(...), $result->notices), ...$result->staleNotes],
            ],
            SyncOutcome::Unchanged => ['No changes', ["Already active: {$result->version->label()}"]],
            SyncOutcome::Busy => throw new LogicException('A sync that did not run is never recorded.'),
        };

        if ($timezoneNote !== null) {
            $bullets[] = $timezoneNote;
        }

        return "## {$time} · {$title}\n".implode('', array_map(fn (string $bullet) => "- {$bullet}\n", $bullets));
    }

    /**
     * A skipped later-sprint file, worded for the changelog.
     */
    private function noticeBullet(string $notice): string
    {
        if (preg_match('/^(.+): type (\S+) is not supported yet/', $notice, $matches) === 1) {
            return "Not supported yet: {$matches[1]} (type {$matches[2]})";
        }

        return $notice;
    }

    /**
     * Says which version the owner is still running.
     */
    private function liveBullet(): string
    {
        $live = IcmVersion::query()->where('status', VersionStatus::Active->value)->latest('id')->first();

        return $live === null ? 'Nothing is live yet' : "{$live->label()} stays live";
    }

    /**
     * Put the entry above the newest existing one, or at the end when there are none yet.
     */
    private function withEntry(string $existing, string $entry): string
    {
        if (preg_match('/^## /m', $existing, $match, PREG_OFFSET_CAPTURE) === 1) {
            return substr($existing, 0, $match[0][1]).$entry."\n".substr($existing, $match[0][1]);
        }

        return rtrim($existing)."\n\n".$entry;
    }
}
