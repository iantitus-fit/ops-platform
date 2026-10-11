<?php

namespace App\Livewire;

use App\Actions\SendOwnerReply;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ConversationThread extends Component
{
    public Conversation $conversation;

    public string $body = '';

    /**
     * Send what the owner typed as a text and clear the box.
     *
     * A refusal comes back as an error under the box. A failed send is kept in the thread as
     * failed, tells the owner why, and leaves their words in the box for another try.
     */
    public function sendReply(SendOwnerReply $sendOwnerReply): void
    {
        try {
            $sendOwnerReply->handle($this->conversation, $this->body);
        } catch (DomainException $exception) {
            // The message can carry provider text, and Filament renders a title as HTML.
            Notification::make()->title(e($exception->getMessage()))->danger()->send();

            return;
        }

        $this->reset('body');

        Notification::make()->title('Reply sent')->success()->send();
    }

    /**
     * The messages in the thread, oldest first.
     *
     * @return Collection<int, Message>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->conversation->messages()->get();
    }

    /**
     * The sentence that replaces the reply box when the owner cannot reply, or null.
     */
    #[Computed]
    public function replyBlockedReason(): ?string
    {
        return app(SendOwnerReply::class)->whyCannotReply($this->conversation);
    }

    public function render(ActiveConfig $activeConfig): View
    {
        return view('livewire.conversation-thread', [
            'timezone' => $activeConfig->displayTimezone(),
        ]);
    }
}
