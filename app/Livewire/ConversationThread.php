<?php

namespace App\Livewire;

use App\Actions\ResolveDraft;
use App\Actions\SendOwnerReply;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
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
     * The owner's choice in the booking link box (D-45). It only asks: the server re-checks every condition when the
     * draft is approved, and uses it only while bookingLinkService names the line on screen.
     */
    public bool $addBookingLink = false;

    /**
     * The service name the booking link line on screen shows, or null when no line is drawn. Locked and filled by the
     * server at every render, so a link is added only when the owner saw the line it was added for (D-45).
     */
    #[Locked]
    public ?string $bookingLinkService = null;

    /**
     * The owner's choice in the visit line box (D-39 amended, D-45). It only asks: the server re-checks every condition
     * when the draft is approved, and uses it only while visitLine names the line on screen.
     */
    public bool $addVisitLine = false;

    /**
     * The visit line the box on screen shows, or null when no box is drawn. Locked and filled by the server at every
     * render, so a line is added only when the owner saw the line it was added for (D-39 amended, D-45).
     */
    #[Locked]
    public ?string $visitLine = null;

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
     *
     * The booking link box asks for a link only while the line was drawn (D-45). When the draft went out without the
     * link the owner asked for, because a condition stopped holding, the notice is a warning that says why. The visit line
     * box works the same way, asking only while visitLine names the line on screen.
     */
    public function approveDraft(ResolveDraft $resolveDraft): void
    {
        $draft = $this->draftToResolve();

        try {
            if ($draft === null) {
                throw new DomainException(ResolveDraft::ALREADY_HANDLED);
            }

            $sent = $resolveDraft->approve(
                $draft,
                auth()->user(),
                $this->draftBody,
                $this->addBookingLink && $this->bookingLinkService !== null,
                $this->addVisitLine && $this->visitLine !== null,
            );
        } catch (ValidationException $exception) {
            $this->addError('draftBody', (string) $exception->validator->errors()->first());

            return;
        } catch (DomainException $exception) {
            $this->draftRefused($exception, keepWords: true);

            return;
        }

        $leftOff = $sent->meta['booking_link_left_off'] ?? null;

        if (is_string($leftOff)) {
            $this->draftResolved(SendOwnerReply::SENT_WITHOUT_LINK.$leftOff, warning: true);

            return;
        }

        $visitLeftOff = $sent->meta['visit_line_left_off'] ?? null;

        if (is_string($visitLeftOff)) {
            $this->draftResolved(SendOwnerReply::SENT_WITHOUT_VISIT_LINE.$visitLeftOff, warning: true);

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
     * The newest text the customer sent, or null when they have sent none. The Needs you card shows it so the owner can
     * read the customer's last words next to the draft, before reaching the thread (G-02-1.1).
     *
     * The relation sorts oldest first, so reorder() puts the newest first.
     */
    #[Computed]
    public function lastCustomerMessage(): ?Message
    {
        return $this->conversation->messages()
            ->reorder('id', 'desc')
            ->where('direction', MessageDirection::In->value)
            ->first();
    }

    /**
     * Whether the customer has written since the held draft was written, so the owner reads the newer text before Send
     * (D-22). It is true when an inbound message of this conversation has an id above the newest text the draft
     * answered, which the front desk records in the draft's `meta['in_reply_to']`.
     *
     * It does not compare with the draft's own id or created_at: a text that arrives while the run is in flight has a
     * lower id than the draft but is not in the burst the draft answered, and it still counts. A draft that does not
     * record what it answered (written before that was kept) shows no flag.
     */
    #[Computed]
    public function customerWroteAgain(): bool
    {
        $inReplyTo = array_filter((array) ($this->liveDraft?->meta['in_reply_to'] ?? []), is_int(...));

        if ($inReplyTo === []) {
            return false;
        }

        return $this->conversation->messages()
            ->where('direction', MessageDirection::In->value)
            ->where('id', '>', max($inReplyTo))
            ->exists();
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

        $failure = $draft->alertFailure();

        if ($failure === null) {
            return null;
        }

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
     * The line above the reply box for a customer who has not texted the business or agreed to texts, or null (D-20,
     * U-26). It only informs: the owner can still send, because the switch governs automatic sends and never the owner's
     * own reply.
     */
    #[Computed]
    public function notAgreedToTextsLine(): ?string
    {
        return $this->conversation->contact->mayGetAutomaticTexts()
            ? null
            : 'This customer has not texted the business or agreed to texts.';
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

        unset($this->liveDraft, $this->draftNotAlerted, $this->customerWroteAgain);

        if ($keepWords && $this->liveDraft === null && trim($this->draftBody) !== '') {
            $this->body = trim($this->draftBody);
        }
    }

    private function draftResolved(string $title, bool $warning = false): void
    {
        unset($this->liveDraft, $this->messages, $this->draftNotAlerted, $this->customerWroteAgain);

        $this->reset('draftId', 'draftBody', 'addBookingLink', 'bookingLinkService', 'addVisitLine', 'visitLine');

        $notification = Notification::make()->title($title);

        ($warning ? $notification->warning() : $notification->success())->send();
    }

    /**
     * Point the Needs you block at the conversation's live draft.
     *
     * A new live draft fills the box with its words, unless the owner has been editing the old one: then their edit
     * stays, and the new draft is shown beside it so nothing they typed is lost (D-28).
     *
     * The booking link line follows the conditions at every render, even while one draft stays live, so it appears and
     * disappears as they change; the box keeps the owner's last choice meanwhile. A different draft becoming live resets
     * the box to that draft's starting state (D-45, U-36). The visit line box follows the same rules (D-39 amended, U-37).
     */
    private function syncDraftFields(ActiveConfig $activeConfig): void
    {
        $live = $this->liveDraft;

        if ($live === null) {
            $this->reset('draftId', 'draftBody', 'addBookingLink', 'bookingLinkService', 'addVisitLine', 'visitLine');

            return;
        }

        $slug = app(SendOwnerReply::class)->bookingLinkServiceFor($live);
        $this->bookingLinkService = $slug === null ? null : ($activeConfig->service($slug)['name'] ?? null);
        $this->visitLine = app(SendOwnerReply::class)->visitLineFor($live);

        if ($live->id === $this->draftId) {
            return;
        }

        $previous = $this->draftToResolve();
        $ownerIsEditing = $previous !== null && trim($this->draftBody) !== trim($previous->body);

        if (! $ownerIsEditing) {
            $this->draftBody = $live->body;
        }

        $this->draftId = $live->id;
        $this->addBookingLink = app(SendOwnerReply::class)->bookingLinkStartsOn($live);
        $this->addVisitLine = app(SendOwnerReply::class)->visitLineStartsOn($live);
    }

    public function render(ActiveConfig $activeConfig): View
    {
        $this->syncDraftFields($activeConfig);

        return view('livewire.conversation-thread', [
            'timezone' => $activeConfig->displayTimezone(),
        ]);
    }
}
