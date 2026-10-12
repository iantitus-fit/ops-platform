<?php

namespace App\Actions;

use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SendOrigin;
use App\Events\CustomerRecordChanged;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use DomainException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The one path every automated text to a customer goes through (Pitfall 15).
 *
 * Whatever wrote the text, it is stored as queued first and becomes sent only once the text channel has returned
 * its id, so a message is never recorded as sent unless the channel accepted it. Every send to one conversation
 * takes the same lock as the owner's own replies, so two texts to one customer never go out together. Checks that
 * must hold for every sender belong here, in whyBlocked(), so no path can skip them.
 */
class OutboundSender
{
    /**
     * How the sentence begins when the text channel refused a send, so a caller can tell that failure from a refusal.
     */
    public const string NOT_SENT = 'The reply was not sent: ';

    public function __construct(private readonly TextChannel $textChannel) {}

    /**
     * Send a text to the customer of a conversation and record it.
     *
     * An owner's send takes the lock at once or refuses, so the owner is told when another reply is in flight.
     * Any other send waits a few seconds for the lock first, because the owner's send is brief and the agent can
     * simply wait its turn. When the channel fails, the message stays on the record as failed and the failure is
     * raised. Once the channel has accepted the text, a failure while recording it is reported to the log and
     * never raised: the customer has the text, and an error would invite a second send.
     *
     * @param  array<string, mixed>  $attributes  Further message columns, such as kind, label, the verdict and meta.
     *
     * @throws DomainException when the text is refused, another reply is being sent, or the channel failed
     */
    public function send(Conversation $conversation, string $body, MessageAuthor $author, SendOrigin $origin, array $attributes = []): Message
    {
        $lock = Cache::lock("reply:{$conversation->id}", 30);

        $this->takeLock($lock, $origin);

        try {
            return $this->deliver($conversation, trim($body), $author, $origin, $attributes);
        } finally {
            $lock->release();
        }
    }

    /**
     * Why nothing may be sent to this conversation at all, or null when a send could go out. The owner's reply box
     * shows this sentence in place of the live form, so it is worded for the owner when the owner is the sender.
     *
     * While the assistant is paused in the conversation, every automated text is refused; only the owner's own
     * reply and the life-safety line pass (D-24). deliver() asks again inside the send lock, on a freshly read
     * conversation, so a Take over that lands while a reply is being composed wins. A later plan adds the opted-out
     * check here.
     */
    public function whyBlocked(Conversation $conversation, SendOrigin $origin): ?string
    {
        $ownerIsSending = $origin === SendOrigin::Owner;

        if ($conversation->status === ConversationStatus::Closed) {
            return $ownerIsSending ? 'This conversation is closed. Reopen it to reply.' : 'This conversation is closed.';
        }

        if ($origin === SendOrigin::Automated && $conversation->isPaused()) {
            return 'The assistant is paused in this conversation.';
        }

        $contact = $conversation->contact;

        if (blank($contact->phone_e164)) {
            if (! $ownerIsSending) {
                return 'There is no phone number on file for this customer.';
            }

            return filled($contact->email)
                ? "No phone number on file. Reply by email: {$contact->email}"
                : 'No phone number or email on file.';
        }

        return null;
    }

    /**
     * @throws DomainException
     */
    private function takeLock(Lock $lock, SendOrigin $origin): void
    {
        if ($origin === SendOrigin::Owner) {
            if (! $lock->get()) {
                throw new DomainException('Another reply to this customer is being sent. Wait a moment, then check the thread before sending again.');
            }

            return;
        }

        try {
            $lock->block(config('gate.agent.lock_wait_seconds'));
        } catch (LockTimeoutException) {
            throw new DomainException('Another reply to this customer is being sent.');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deliver(Conversation $conversation, string $body, MessageAuthor $author, SendOrigin $origin, array $attributes): Message
    {
        $conversation->refresh();

        $refusal = $this->whyBlocked($conversation, $origin) ?? $this->whyBodyIsRefused($body);

        if ($refusal !== null) {
            throw new DomainException($refusal);
        }

        $contact = $conversation->contact;

        $message = DB::transaction(fn (): Message => $conversation->messages()->create([
            ...$attributes,
            'channel' => MessageChannel::Sms,
            'direction' => MessageDirection::Out,
            'author' => $author,
            'body' => $body,
            'status' => MessageStatus::Queued,
            'provider' => $this->textChannel->name(),
        ]));

        try {
            $providerMessageId = $this->textChannel->send($contact->phone_e164, $body);
        } catch (Throwable $exception) {
            $this->recordFailure($contact, $message, $exception);

            throw new DomainException(self::NOT_SENT.$exception->getMessage(), previous: $exception);
        }

        try {
            DB::transaction(function () use ($conversation, $contact, $message, $providerMessageId, $origin): void {
                $sentAt = now();

                $message->update([
                    'status' => MessageStatus::Sent,
                    'provider_message_id' => $providerMessageId,
                ]);

                $contact->activities()->create([
                    'type' => ActivityType::ReplySent,
                    'summary' => $origin === SendOrigin::Owner ? 'Reply sent by owner' : 'Reply sent by front desk',
                    'subject_type' => $message->getMorphClass(),
                    'subject_id' => $message->id,
                    'occurred_at' => $sentAt,
                ]);

                $conversation->update(['last_message_at' => $sentAt]);
                $contact->update(['last_activity_at' => $sentAt]);
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->markSentAfterRecordingFailed($message, $providerMessageId);
        }

        CustomerRecordChanged::dispatch($contact->id);

        return $message;
    }

    /**
     * The last guard against a text the channel should never be handed: an empty one, or one past the limit that
     * the send rule holds as a draft rather than cutting.
     */
    private function whyBodyIsRefused(string $body): ?string
    {
        if ($body === '') {
            return 'There is nothing to send.';
        }

        if (mb_strlen($body) > SendOwnerReply::MAX_LENGTH) {
            return 'Texts are limited to 1,600 characters.';
        }

        return null;
    }

    /**
     * The text has gone out but its record failed. Save the least that keeps the thread honest:
     * the message as sent with the provider's id. If even that fails, the failure is logged.
     */
    private function markSentAfterRecordingFailed(Message $message, string $providerMessageId): void
    {
        try {
            $message->refresh()->update([
                'status' => MessageStatus::Sent,
                'provider_message_id' => $providerMessageId,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Keep the failed attempt on the record: the message as failed with the error, and a note.
     */
    private function recordFailure(Contact $contact, Message $message, Throwable $exception): void
    {
        DB::transaction(function () use ($contact, $message, $exception): void {
            $message->update([
                'status' => MessageStatus::Failed,
                'meta' => [...($message->meta ?? []), 'error' => $exception->getMessage()],
            ]);

            $contact->activities()->create([
                'type' => ActivityType::Note,
                'summary' => "Reply not sent: {$exception->getMessage()}",
                'subject_type' => $message->getMorphClass(),
                'subject_id' => $message->id,
                'occurred_at' => now(),
            ]);
        });

        CustomerRecordChanged::dispatch($contact->id);
    }
}
