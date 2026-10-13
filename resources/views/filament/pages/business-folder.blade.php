<x-filament-panels::page>
    @php
        $version = $this->liveVersion();
        $lastSync = $this->lastSync();
        $timezone = $this->timezone();
        $alertWarning = $this->alertWarning();
        $failedAlert = $this->lastFailedAlert();
    @endphp

    <x-filament::section heading="Live version">
        @if ($version === null)
            <p>No version is live yet. Click Sync Now.</p>
        @else
            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                <p><strong>{{ $version->label() }}</strong> · {{ $version->file_count }} {{ \Illuminate\Support\Str::plural('file', $version->file_count) }}</p>
                <p>Live since {{ $version->activated_at->setTimezone($timezone)->format('M j, g:i a') }}</p>
                <p>Fingerprint {{ substr($version->fingerprint, 0, 12) }} · {{ $version->git_sha ?? 'Not a git repository' }}</p>
            </div>
        @endif
    </x-filament::section>

    @if ($version !== null)
        <x-filament::section heading="Front desk">
            <p>{{ $this->frontDeskLine() }}</p>
        </x-filament::section>

        <x-filament::section heading="Always held for you">
            <p>A text that uses any of these never goes out without you. You can see them here, but only the Never say list is yours to change.</p>
            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                @foreach ($this->alwaysHeld() as $label => $phrases)
                    <p wire:key="held-{{ $label }}"><strong>{{ $label }}:</strong> {{ $phrases === [] ? 'none yet' : implode(', ', $phrases) }}</p>
                @endforeach
            </div>
        </x-filament::section>

        @if ($alertWarning !== null || $failedAlert !== null)
            <x-filament::section heading="Alerts">
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    @if ($alertWarning !== null)
                        <p style="color: #dc2626;">{{ $alertWarning }} <a href="{{ \Filament\Facades\Filament::getProfileUrl() }}" style="text-decoration: underline;">Open your profile</a></p>
                    @endif
                    @if ($failedAlert !== null)
                        <p>The last alert was not sent: {{ $failedAlert->failure }} · {{ $failedAlert->failed_at->setTimezone($timezone)->format('M j, g:i a') }}</p>
                    @endif
                </div>
            </x-filament::section>
        @endif

        <x-filament::section heading="Files">
            <ul style="display: flex; flex-direction: column; gap: 0.25rem;">
                @foreach ($this->files() as $file)
                    <li wire:key="file-{{ $file['path'] }}">{{ $file['path'] }} · {{ $file['type'] }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($lastSync !== null)
        <x-filament::section heading="Last sync">
            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                <p>
                    <strong>{{ match ($lastSync['outcome']) {
                        \App\Enums\SyncOutcome::Rejected => 'Rejected',
                        \App\Enums\SyncOutcome::Unchanged => 'No changes',
                        default => 'Live',
                    } }}</strong>
                    · {{ $lastSync['at']->setTimezone($timezone)->format('M j, g:i a') }}
                </p>
                @foreach ($lastSync['errors'] as $error)
                    <p style="color: #dc2626;">{{ $error }}</p>
                @endforeach
                @foreach ($lastSync['notices'] as $notice)
                    <p>{{ $this->noticeLine($notice) }}</p>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
