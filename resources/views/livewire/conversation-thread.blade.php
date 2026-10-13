<div wire:poll.5s>
    @if ($this->liveDraft !== null)
        <x-filament::section compact style="margin-bottom: 1rem;">
            <x-slot name="heading">Needs you</x-slot>

            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <p>{{ $this->liveDraft->gate['reason']['plain'] ?? 'Held for your review' }}</p>
                @if ($this->draftNotAlerted !== null)
                    <p><x-filament::badge color="warning">{{ $this->draftNotAlerted }}</x-filament::badge></p>
                @endif
                @if (trim($draftBody) !== trim($this->liveDraft->body))
                    <p style="font-size: 0.875rem;">
                        <strong>The assistant wrote:</strong>
                        <span class="whitespace-pre-line" style="white-space: pre-line;">{{ $this->liveDraft->body }}</span>
                    </p>
                @endif
                <form wire:submit="approveDraft" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <x-filament::input.wrapper>
                        <textarea wire:model="draftBody" rows="4" class="fi-input" style="width: 100%; padding: 0.5rem 0.75rem;"></textarea>
                    </x-filament::input.wrapper>
                    @error('draftBody')
                        <p style="color: #dc2626; font-size: 0.875rem;">{{ $message }}</p>
                    @enderror
                    <p style="font-size: 0.875rem;">The sign-off (and the AI note on a first text) is added when it sends.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <x-filament::button type="submit">Send</x-filament::button>
                        <x-filament::button type="button" color="gray" wire:click="discardDraft">Discard</x-filament::button>
                    </div>
                </form>
            </div>
        </x-filament::section>
    @endif

    @if ($this->pausedBanner !== null)
        <x-filament::section compact style="margin-bottom: 1rem;">
            <p>{{ $this->pausedBanner }}</p>
        </x-filament::section>
    @endif

    <div x-init="$el.scrollTop = $el.scrollHeight" style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 60vh; overflow-y: auto;">
        @forelse ($this->messages as $message)
            @php
                [$authorLabel, $authorSide, $authorBackground] = match ($message->author) {
                    \App\Enums\MessageAuthor::Customer => ['Customer', 'flex-start', 'rgba(128, 128, 128, 0.15)'],
                    \App\Enums\MessageAuthor::Agent => ['Front desk', 'flex-end', 'rgba(245, 158, 11, 0.18)'],
                    \App\Enums\MessageAuthor::Owner => ['You', 'flex-end', 'rgba(34, 197, 94, 0.18)'],
                };
            @endphp
            <div wire:key="message-{{ $message->id }}" data-author="{{ $message->author->value }}" style="align-self: {{ $authorSide }}; max-width: 85%; padding: 0.5rem 0.75rem; border-radius: 0.75rem; background: {{ $authorBackground }};">
                <div style="font-size: 0.875rem;">
                    <strong>{{ $authorLabel }}</strong>
                    <span>{{ $message->channel->value }} · {{ $message->happenedAt()->setTimezone($timezone)->format('M j, g:i a') }}</span>
                    @if ($message->direction === \App\Enums\MessageDirection::Out)
                        <x-filament::badge size="sm" :color="match ($message->status) {
                            \App\Enums\MessageStatus::Failed => 'danger',
                            \App\Enums\MessageStatus::Queued, \App\Enums\MessageStatus::Draft => 'warning',
                            \App\Enums\MessageStatus::Sent, \App\Enums\MessageStatus::Delivered => 'success',
                            default => 'gray',
                        }">{{ $message->status->value }}</x-filament::badge>
                    @endif
                </div>
                <div class="whitespace-pre-line" style="white-space: pre-line;">{{ $message->body }}</div>
            </div>
        @empty
            <p>No messages yet.</p>
        @endforelse
    </div>

    @if ($this->replyBlockedReason !== null)
        <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
            <p>{{ $this->replyBlockedReason }}</p>
            <x-filament::input.wrapper disabled>
                <textarea rows="3" disabled class="fi-input" style="width: 100%; padding: 0.5rem 0.75rem;"></textarea>
            </x-filament::input.wrapper>
            <div>
                <x-filament::button type="button" disabled>Send</x-filament::button>
            </div>
        </div>
    @else
        <form wire:submit="sendReply" style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
            <x-filament::input.wrapper>
                <textarea wire:model="body" rows="3" placeholder="Type a reply" class="fi-input" style="width: 100%; padding: 0.5rem 0.75rem;"></textarea>
            </x-filament::input.wrapper>
            @error('body')
                <p style="color: #dc2626; font-size: 0.875rem;">{{ $message }}</p>
            @enderror
            <div>
                <x-filament::button type="submit">Send</x-filament::button>
            </div>
        </form>
    @endif
</div>
