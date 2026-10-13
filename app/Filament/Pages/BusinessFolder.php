<?php

namespace App\Filament\Pages;

use App\Ai\AgentFile;
use App\Enums\SyncOutcome;
use App\Icm\ActiveConfig;
use App\Icm\Exceptions\IcmFolderMissing;
use App\Icm\Exceptions\NoActiveVersion;
use App\Icm\FolderSync;
use App\Icm\SyncStatus;
use App\Models\IcmVersion;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\CodeChecks;
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
     * The live agent that answers texts, or null when no version is live or no agent file serves sms.
     */
    public function frontDesk(): ?AgentFile
    {
        try {
            return AgentFile::forChannel(app(ActiveConfig::class), 'sms');
        } catch (NoActiveVersion) {
            return null;
        }
    }

    /**
     * The front desk as one sentence for the owner: which model answers texts and what it falls back to.
     */
    public function frontDeskLine(): string
    {
        $frontDesk = $this->frontDesk();

        if ($frontDesk === null) {
            return 'Front desk: off. No agent file answers texts, so customer texts wait for you here.';
        }

        $chain = implode(', then ', array_map(
            fn (array $entry) => "{$entry['provider']} {$entry['model']}",
            $frontDesk->providerChain(),
        ));

        return "Front desk: {$frontDesk->name} answers texts with {$chain}.";
    }

    /**
     * What always waits for the owner, as labelled lists of words and phrases: the three the code checks on every
     * message (they live in code, never in the folder) and the live voice file's never_say list, which is empty
     * when no version is live.
     *
     * @return array<string, list<string>>
     */
    public function alwaysHeld(): array
    {
        try {
            $neverSay = app(ActiveConfig::class)->voice()['never_say'] ?? [];
        } catch (NoActiveVersion) {
            $neverSay = [];
        }

        return [
            'Price words' => CodeChecks::PRICE_WORDS,
            'Discount phrases' => CodeChecks::DISCOUNT_PHRASES,
            'Promise phrases' => CodeChecks::PROMISE_PHRASES,
            'Never say' => array_values(array_filter((array) $neverSay, is_string(...))),
        ];
    }

    /**
     * The warning for the owner when alerts are set to go by text and no one has a phone number on their profile,
     * so every alert would have nowhere to go (D-19). Null when alerts can reach someone, or no version is live.
     * An owner_alert from a folder older than the front desk is a sentence, not a list of channels; it counts as
     * text, because every alert goes by text now (D-18).
     */
    public function alertWarning(): ?string
    {
        try {
            $alert = app(ActiveConfig::class)->sendingRules()['owner_alert'] ?? null;
        } catch (NoActiveVersion) {
            return null;
        }

        $channels = is_array($alert) ? ($alert['channels'] ?? []) : ['text'];

        if (! in_array('text', $channels, true) || User::query()->whereNotNull('phone')->exists()) {
            return null;
        }

        return 'Alerts are set to go by text, but no one has a phone number on their profile. Add yours on your profile page so held drafts reach you.';
    }

    /**
     * The newest owner alert that could not be sent. Its failure holds the reason in plain words.
     */
    public function lastFailedAlert(): ?OwnerAlert
    {
        return OwnerAlert::query()->whereNotNull('failed_at')->latest('failed_at')->latest('id')->first();
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
     * A skipped later-sprint file as one line for the owner, such as "Not supported yet: automations/follow-up.md".
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
