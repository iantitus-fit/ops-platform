<?php

namespace App\Livewire;

use App\Actions\ResolveDraft;
use App\Actions\SendOwnerReply;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConversationThread extends Component
{
    public Conversation $conversation;

    public string $body = '';

    /**
     * The held draft the Needs you block is about. Locked, so the browser cannot point it at another message; the
     * server fills it from the conversation's own live draft.
     */
    #[Locked]
    public ?int $draftId = null;

    /**
     * The words in the Needs you box: the draft as the agent wrote it, until the owner changes them.
     */
    public string $draftBody = '';

    /**
     * Send what the owner typed as a text and clear the box.
     *
     * A refusal comes back as an error under the box. A failed send is kept in the thread as
     * failed, tells the owner why, and leaves their words in the box for another try.
     */
    public function sendReply(SendOwnerReply $sendOwnerReply): void
    {
        try {
            $sendOwnerReply->handle($this->conversation, $this->body, auth()->user());
        } catch (DomainException $exception) {
            // The message can carry provider text, and Filament renders a title as HTML.
            Notification::make()->title(e($exception->getMessage()))->danger()->send();

            return;
        }

        $this->reset('body');

        Notification::make()->title('Reply sent')->success()->send();
    }

    /**
     * Send the held draft: as written, or with the owner's edit from the box (D-27).
     *
     * A draft that a newer text replaced is refused with the new draft shown and the owner's edit left in the box
     * (D-28). A refusal about the words comes back as an error under the box. When the draft is gone, or the text
     * channel failed so the draft row is kept as failed, the owner's words move to the reply box for another try.
     */
    public function approveDraft(ResolveDraft $resolveDraft): void
    {
        $draft = $this->draftToResolve();

        try {
            if ($draft === null) {
                throw new DomainException(ResolveDraft::ALREADY_HANDLED);
            }

            $resolveDraft->approve($draft, auth()->user(), $this->draftBody);
        } catch (ValidationException $exception) {
            $this->addError('draftBody', (string) $exception->validator->errors()->first());

            return;
        } catch (DomainException $exception) {
            $this->draftRefused($exception, keepWords: true);

            return;
        }

        $this->draftResolved('Draft sent');
    }

    /**
     * Discard the held draft without sending anything.
     */
    public function discardDraft(ResolveDraft $resolveDraft): void
    {
        $draft = $this->draftToResolve();

        try {
            if ($draft === null) {
                throw new DomainException(ResolveDraft::ALREADY_HANDLED);
            }

            $resolveDraft->discard($draft, auth()->user());
        } catch (DomainException $exception) {
            $this->draftRefused($exception);

            return;
        }

        $this->draftResolved('Draft discarded');
    }

    /**
     * The messages in the thread, oldest first. A draft waiting for the owner is in the Needs you block instead, and a
     * withdrawn one never reached the customer, so neither is part of the conversation.
     *
     * @return Collection<int, Message>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->conversation->messages()
            ->whereNotIn('status', [MessageStatus::Draft, MessageStatus::Withdrawn])
            ->get();
    }

    /**
     * The reply the front desk wrote and the send rule kept for the owner, or null when nothing is waiting (D-28).
     */
    #[Computed]
    public function liveDraft(): ?Message
    {
        return $this->conversation->messages()->where('status', MessageStatus::Draft)->first();
    }

    /**
     * The flag shown on a held draft nobody was told about, or null (D-19). It appears only when the draft has alerts
     * and every one of them failed: an alert still waiting to go, sent, or skipped on purpose is not a gap.
     */
    #[Computed]
    public function draftNotAlerted(): ?string
    {
        $draft = $this->liveDraft;

        if ($draft === null) {
            return null;
        }

        $alerts = OwnerAlert::query()->where('message_id', $draft->id)->orderBy('id')->get();

        if ($alerts->isEmpty() || $alerts->contains(fn (OwnerAlert $alert): bool => $alert->failed_at === null)) {
            return null;
        }

        $failure = rtrim(trim((string) $alerts->last()->failure), '.');

        return $failure === 'No phone on file'
            ? "Not alerted: {$failure}. Add your phone on your profile."
            : 'Not alerted: '.($failure !== '' ? $failure : 'the text could not be sent').'.';
    }

    /**
     * The sentence that replaces the reply box when the owner cannot reply, or null.
     */
    #[Computed]
    public function replyBlockedReason(): ?string
    {
        return app(SendOwnerReply::class)->whyCannotReply($this->conversation);
    }

    /**
     * The banner above a paused thread, or null while the assistant is active (D-25).
     */
    #[Computed]
    public function pausedBanner(): ?string
    {
        $conversation = $this->conversation->refresh();

        if (! $conversation->isPaused()) {
            return null;
        }

        return trim($conversation->paused_reason?->bannerLead()." You're handling this. Hand back to the assistant when done.");
    }

    /**
     * The draft the owner meant, looked up inside this conversation only so a swapped id finds nothing.
     */
    private function draftToResolve(): ?Message
    {
        return $this->draftId === null ? null : $this->conversation->messages()->find($this->draftId);
    }

    /**
     * Tell the owner why the draft was refused. Their words stay where they can try again: in the Needs you box while a
     * draft is still waiting, otherwise in the reply box.
     */
    private function draftRefused(DomainException $exception, bool $keepWords = false): void
    {
        // The message can carry provider text, and Filament renders a title as HTML.
        Notification::make()->title(e($exception->getMessage()))->danger()->send();

        unset($this->liveDraft, $this->draftNotAlerted);

        if ($keepWords && $this->liveDraft === null && trim($this->draftBody) !== '') {
            $this->body = trim($this->draftBody);
        }
    }

    private function draftResolved(string $title): void
    {
        unset($this->liveDraft, $this->messages, $this->draftNotAlerted);

        $this->reset('draftId', 'draftBody');

        Notification::make()->title($title)->success()->send();
    }

    /**
     * Point the Needs you block at the conversation's live draft.
     *
     * A new live draft fills the box with its words, unless the owner has been editing the old one: then their edit
     * stays, and the new draft is shown beside it so nothing they typed is lost (D-28).
     */
    private function syncDraftFields(): void
    {
        $live = $this->liveDraft;

        if ($live === null) {
            $this->reset('draftId', 'draftBody');

            return;
        }

        if ($live->id === $this->draftId) {
            return;
        }

        $previous = $this->draftToResolve();
        $ownerIsEditing = $previous !== null && trim($this->draftBody) !== trim($previous->body);

        if (! $ownerIsEditing) {
            $this->draftBody = $live->body;
        }

        $this->draftId = $live->id;
    }

    public function render(ActiveConfig $activeConfig): View
    {
        $this->syncDraftFields();

        return view('livewire.conversation-thread', [
            'timezone' => $activeConfig->displayTimezone(),
        ]);
    }
}
