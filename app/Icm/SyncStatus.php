<?php

namespace App\Icm;

use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Models\IcmVersion;
use Carbon\CarbonInterface;

/**
 * What the owner sees on the Business Folder page: the live version and how the last sync went.
 */
class SyncStatus
{
    /**
     * The live version, or null when nothing has been synced yet.
     */
    public function active(): ?IcmVersion
    {
        return IcmVersion::query()
            ->where('status', VersionStatus::Active->value)
            ->latest('id')
            ->first();
    }

    /**
     * The result of the most recent sync: the newest rejected attempt or the live version's last
     * check, whichever came later. A tie goes to the rejected attempt, so a failure is never hidden.
     * A no-op sync creates no row, so it shows up as the live version's last_checked_at.
     *
     * @return array{outcome: SyncOutcome, at: CarbonInterface, version: IcmVersion, errors: list<string>, notices: list<string>}|null
     */
    public function latest(): ?array
    {
        $rejected = IcmVersion::query()
            ->where('status', VersionStatus::Rejected->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        $active = $this->active();

        $activeCheckedAt = $active === null ? null : ($active->last_checked_at ?? $active->activated_at);

        if ($rejected !== null && ($activeCheckedAt === null || $rejected->created_at->greaterThanOrEqualTo($activeCheckedAt))) {
            return [
                'outcome' => SyncOutcome::Rejected,
                'at' => $rejected->created_at,
                'version' => $rejected,
                'errors' => $rejected->errors ?? [],
                'notices' => $rejected->notices ?? [],
            ];
        }

        if ($active === null) {
            return null;
        }

        return [
            'outcome' => $active->last_result ?? SyncOutcome::Activated,
            'at' => $activeCheckedAt,
            'version' => $active,
            'errors' => [],
            'notices' => $active->notices ?? [],
        ];
    }
}
