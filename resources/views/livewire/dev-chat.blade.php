<div>
    <h1>Dev chat</h1>
    <p>Texts typed here go through the fake text channel as if a customer sent them. Only available in the local environment.</p>

    <div wire:poll.2s style="display: flex; flex-direction: column; gap: 0.75rem; margin: 1rem 0;">
        @forelse ($this->thread as $message)
            <div wire:key="message-{{ $message->id }}">
                <div style="font-size: 0.875rem;">
                    <strong>{{ $message->author->name }}</strong>
                    <span>{{ $message->happenedAt()->setTimezone($timezone)->format('M j, g:i a') }}</span>
                </div>
                <div style="white-space: pre-line;">{{ $message->body }}</div>
            </div>
        @empty
            <p>No messages yet.</p>
        @endforelse
    </div>

    <form wire:submit="send" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <label for="dev-chat-from">From</label>
        <input id="dev-chat-from" type="text" wire:model="from">
        @error('from')
            <p style="color: #dc2626; font-size: 0.875rem;">{{ $message }}</p>
        @enderror

        <label for="dev-chat-body">Message</label>
        <textarea id="dev-chat-body" rows="3" wire:model="body" placeholder="Type a message"></textarea>
        @error('body')
            <p style="color: #dc2626; font-size: 0.875rem;">{{ $message }}</p>
        @enderror

        <div>
            <button type="submit">Send</button>
        </div>
    </form>
</div>
