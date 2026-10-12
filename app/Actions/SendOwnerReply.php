<?php

namespace App\Actions;

use App\Enums\DraftResolution;
use App\Enums\MessageAuthor;
use App\Enums\PausedReason;
use App\Enums\SendOrigin;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use DomainException;
use Illuminate\Validation\ValidationException;

class SendOwnerReply
{
    /**
     * The longest reply the owner may send: Twilio's limit for one concatenated text.
     */
    public const int MAX_LENGTH = 1600;

    private const string TOO_LONG = 'Keep replies to 1,600 characters or fewer.';

    public function __construct(
        private readonly OutboundSender $outboundSender,
        private readonly PauseConversation $pauseConversation,
        private readonly ComposeAutomatedText $composeAutomatedText,
    ) {}

    /**
     * Send the owner's reply to the customer by text and record it.
     *
     * Refusals are validation errors on the body, raised before anything is stored. The sending itself is
     * OutboundSender's job, the one path every text to a customer takes: it holds the lock that lets only one
     * reply per conversation go out at a time, stores the message as queued first and as sent only once the text
     * channel has returned its id. A channel failure leaves the message stored as failed and is raised as a
     * DomainException for the owner, and so is a second reply sent while one is in flight.
     *
     * Once the reply has gone out the assistant steps back from the conversation (D-25), so it does not talk over
     * the owner. The pause happens only after a successful send, and it leaves a conversation that is already paused
     * exactly as it is, so a take over keeps its reason. `$by` is the signed-in owner, recorded as who paused.
     *
     * @throws ValidationException when the reply cannot or should not go out
     * @throws DomainException when the text channel failed to send, or another reply is being sent
     */
    public function handle(Conversation $conversation, string $body, ?User $by = null): Message
    {
        $conversation->refresh();
        $body = trim($body);

        $refusal = $this->whyCannotReply($conversation) ?? $this->whyBodyIsRefused($body);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['body' => $refusal]);
        }

        $message = $this->outboundSender->send($conversation, $body, MessageAuthor::Owner, SendOrigin::Owner);

        $this->pauseConversation->handle($conversation, PausedReason::OwnerReplied, $by);

        return $message;
    }

    /**
     * Send a held draft with the owner's say-so: as the agent wrote it, or with the owner's own words (D-27).
     *
     * The words go out exactly as typed, trimmed, with the sign-off added, and the AI note too if no automated text
     * has reached the customer yet (D-50). The length limit is checked on that composed text, because that is what the
     * customer receives. The draft row itself becomes the sent message, keeping who decided and, when the owner changed
     * the words, the agent's original (D-54).
     *
     * Unlike handle(), this never pauses the assistant: the owner approved the agent's reply, they did not take the
     * conversation over (D-25).
     *
     * @throws ValidationException when the reply cannot or should not go out
     * @throws DomainException when the draft is gone, the text channel failed, or another reply is being sent
     */
    public function sendDraft(Message $draft, string $body, User $by, bool $edited): Message
    {
        $conversation = $draft->conversation;
        $conversation->refresh();
        $words = trim($body);

        $refusal = $this->whyCannotReply($conversation) ?? ($words === '' ? 'Type a message first.' : null);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['body' => $refusal]);
        }

        $composed = $this->composeAutomatedText->forReply($conversation, $words);

        if (mb_strlen($composed) > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => self::TOO_LONG]);
        }

        return $this->outboundSender->sendExisting($draft, $composed, SendOrigin::Owner, [
            'resolution' => $edited ? DraftResolution::Edited : DraftResolution::Approved,
            'resolved_by' => $by->id,
            'resolved_at' => now(),
            'original_body' => $edited ? $draft->body : null,
            'activity_summary' => $edited ? 'Draft edited by owner and sent' : 'Draft approved by owner and sent',
        ]);
    }

    /**
     * Why the owner cannot reply in this conversation at all, or null when a reply could go out.
     * The reply box shows this sentence in place of the live form.
     */
    public function whyCannotReply(Conversation $conversation): ?string
    {
        return $this->outboundSender->whyBlocked($conversation, SendOrigin::Owner);
    }

    private function whyBodyIsRefused(string $body): ?string
    {
        if ($body === '') {
            return 'Type a message first.';
        }

        if (mb_strlen($body) > self::MAX_LENGTH) {
            return self::TOO_LONG;
        }

        return null;
    }
}
