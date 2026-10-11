<?php

namespace App\Filament\Pages;

use App\Enums\SyncOutcome;
use App\Icm\ActiveConfig;
use App\Icm\Exceptions\IcmFolderMissing;
use App\Icm\Exceptions\NoActiveVersion;
use App\Icm\FolderSync;
use App\Icm\SyncStatus;
use App\Models\IcmVersion;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class BusinessFolder extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected string $view = 'filament.pages.business-folder';

    /**
     * Sync Now runs the same FolderSync as php artisan icm:sync, inline rather than queued, so the
     * dashboard can never activate what the command would reject.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncNow')
                ->label('Sync Now')
                ->action(fn () => $this->runSync()),
        ];
    }

    /**
     * The live version, or null when nothing has been synced yet.
     */
    public function liveVersion(): ?IcmVersion
    {
        return app(SyncStatus::class)->active();
    }

    /**
     * The files in the live version, with a router file's missing type shown as "router".
     *
     * @return list<array{path: string, type: string}>
     */
    public function files(): array
    {
        try {
            $files = app(ActiveConfig::class)->files();
        } catch (NoActiveVersion) {
            return [];
        }

        return array_map(fn (array $file) => [
            'path' => $file['path'],
            'type' => $file['type'] ?? 'router',
        ], $files);
    }

    /**
     * How the most recent sync went, or null before the first one.
     *
     * @return array{outcome: SyncOutcome, at: CarbonInterface, version: IcmVersion, errors: list<string>, notices: list<string>}|null
     */
    public function lastSync(): ?array
    {
        return app(SyncStatus::class)->latest();
    }

    /**
     * The time zone owner-facing times are shown in.
     */
    public function timezone(): string
    {
        return app(ActiveConfig::class)->displayTimezone();
    }

    /**
     * A skipped later-sprint file as one line for the owner, such as "Not supported yet: agents/front-desk.md".
     */
    public function noticeLine(string $notice): string
    {
        if (preg_match('/^(.+): type (\S+) is not supported yet/', $notice, $matches) === 1) {
            return "Not supported yet: {$matches[1]}";
        }

        return $notice;
    }

    private function runSync(): void
    {
        try {
            $result = app(FolderSync::class)->run();
        } catch (IcmFolderMissing $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $notification = Notification::make()->title($result->message);

        match ($result->outcome) {
            SyncOutcome::Activated, SyncOutcome::Unchanged => $notification->success(),
            SyncOutcome::Rejected => $notification->danger()->body(
                implode('<br>', array_map(e(...), $result->errors)),
            ),
            SyncOutcome::Busy => $notification->warning(),
        };

        $notification->send();
    }
}
