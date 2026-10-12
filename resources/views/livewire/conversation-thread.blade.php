<div wire:poll.5s>
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        @forelse ($this->messages as $message)
            <div wire:key="message-{{ $message->id }}">
                <div style="font-size: 0.875rem;">
                    <strong>{{ $message->author->name }}</strong>
                    <span>{{ $message->channel->value }} · {{ $message->created_at->setTimezone($timezone)->format('M j, g:i a') }}</span>
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
                <textarea rows="3" disabled class="fi-input" style="width: 100%;"></textarea>
            </x-filament::input.wrapper>
            <div>
                <x-filament::button type="button" disabled>Send</x-filament::button>
            </div>
        </div>
    @else
        <form wire:submit="sendReply" style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
            <x-filament::input.wrapper>
                <textarea wire:model="body" rows="3" placeholder="Type a reply" class="fi-input" style="width: 100%;"></textarea>
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
