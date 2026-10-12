<?php

namespace App\Actions;

use App\Enums\MessageAuthor;
use App\Enums\SendOrigin;
use App\Models\Conversation;
use App\Models\Message;
use DomainException;
use Illuminate\Validation\ValidationException;

class SendOwnerReply
{
    /**
     * The longest reply the owner may send: Twilio's limit for one concatenated text.
     */
    public const int MAX_LENGTH = 1600;

    public function __construct(private readonly OutboundSender $outboundSender) {}

    /**
     * Send the owner's reply to the customer by text and record it.
     *
     * Refusals are validation errors on the body, raised before anything is stored. The sending itself is
     * OutboundSender's job, the one path every text to a customer takes: it holds the lock that lets only one
     * reply per conversation go out at a time, stores the message as queued first and as sent only once the text
     * channel has returned its id. A channel failure leaves the message stored as failed and is raised as a
     * DomainException for the owner, and so is a second reply sent while one is in flight.
     *
     * @throws ValidationException when the reply cannot or should not go out
     * @throws DomainException when the text channel failed to send, or another reply is being sent
     */
    public function handle(Conversation $conversation, string $body): Message
    {
        $conversation->refresh();
        $body = trim($body);

        $refusal = $this->whyCannotReply($conversation) ?? $this->whyBodyIsRefused($body);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['body' => $refusal]);
        }

        return $this->outboundSender->send($conversation, $body, MessageAuthor::Owner, SendOrigin::Owner);
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
            return 'Keep replies to 1,600 characters or fewer.';
        }

        return null;
    }
}
