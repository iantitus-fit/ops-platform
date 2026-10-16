<?php

namespace App\Actions;

use App\Channels\RecipientOptedOut;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SendOrigin;
use App\Events\CustomerRecordChanged;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonInterface;
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

    /**
     * The sentence for a send the carrier refused because the number had opted out, which our own record had not
     * caught yet (Twilio error 21610).
     */
    public const string CARRIER_OPTED_OUT = 'The customer has opted out of texts.';

    /**
     * How the sentence begins when an automated text was refused because the safety check had not read the customer's
     * message, or a part of the text lacked what it needs from that reading (D-45, D-49).
     */
    public const string NO_READING = 'Not sent without the safety check: ';

    public function __construct(
        private readonly TextChannel $textChannel,
        private readonly ActiveConfig $activeConfig,
    ) {}

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
     * Send a held draft: the same row moves from draft to sent with the final text, so one message carries the
     * agent's draft, the owner's decision and the delivery (D-54).
     *
     * It takes the same lock and makes the same checks as send(), then claims the draft by moving it from draft to
     * queued under a row lock. If a newer draft replaced it or another tab sent it first, the claim fails, nothing
     * is sent and the sentence says why. The attributes are the columns to record with the claim, such as the
     * resolution and who resolved it; `activity_summary` is not a column but the words for the timeline entry.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws DomainException when the draft is gone, the text is refused, another reply is being sent, or the channel failed
     */
    public function sendExisting(Message $message, string $body, SendOrigin $origin, array $attributes = []): Message
    {
        $conversation = $message->conversation;
        $lock = Cache::lock("reply:{$conversation->id}", 30);

        $this->takeLock($lock, $origin);

        try {
            return $this->deliverExisting($conversation, $message, trim($body), $origin, $attributes);
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
     * conversation, so a Take over that lands while a reply is being composed wins.
     *
     * A customer who texted STOP is refused first, for every origin: the owner's reply, an approved draft and the
     * life-safety line included (D-40). The owner is alerted separately so they can call.
     */
    public function whyBlocked(Conversation $conversation, SendOrigin $origin): ?string
    {
        $ownerIsSending = $origin === SendOrigin::Owner;
        $contact = $conversation->contact;

        if ($contact->isOptedOut()) {
            $date = $contact->sms_opted_out_at->clone()->setTimezone($this->activeConfig->displayTimezone())->format('M j');

            return "Opted out by texting STOP on {$date}.";
        }

        if ($conversation->status === ConversationStatus::Closed) {
            return $ownerIsSending ? 'This conversation is closed. Reopen it to reply.' : 'This conversation is closed.';
        }

        if ($origin === SendOrigin::Automated && $conversation->isPaused()) {
            return 'The assistant is paused in this conversation.';
        }

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

        $summary = $attributes['activity_summary'] ?? null;
        unset($attributes['activity_summary']);

        $message = DB::transaction(fn (): Message => $conversation->messages()->create([
            ...$attributes,
            'channel' => MessageChannel::Sms,
            'direction' => MessageDirection::Out,
            'author' => $author,
            'body' => $body,
            'status' => MessageStatus::Queued,
            'provider' => $this->textChannel->name(),
        ]));

        return $this->transmit($conversation, $message, $origin, $summary);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deliverExisting(Conversation $conversation, Message $message, string $body, SendOrigin $origin, array $attributes): Message
    {
        $conversation->refresh();

        $refusal = $this->whyBlocked($conversation, $origin) ?? $this->whyBodyIsRefused($body);

        if ($refusal !== null) {
            throw new DomainException($refusal);
        }

        $summary = $attributes['activity_summary'] ?? null;
        unset($attributes['activity_summary']);

        $claimed = DB::transaction(function () use ($message, $body, $attributes): bool {
            $draft = Message::query()->lockForUpdate()->find($message->id);

            if ($draft === null || $draft->status !== MessageStatus::Draft) {
                return false;
            }

            $draft->update([
                ...$attributes,
                'body' => $body,
                'status' => MessageStatus::Queued,
                'provider' => $this->textChannel->name(),
            ]);

            return true;
        });

        $message->refresh();

        if (! $claimed) {
            throw new DomainException(ResolveDraft::whyGone($message));
        }

        return $this->transmit($conversation, $message, $origin, $summary);
    }

    /**
     * Hand a queued message to the text channel and record the outcome: sent with the channel's id, or failed with
     * the error. `$summary` words the timeline entry; null means the usual line for who sent it.
     */
    private function transmit(Conversation $conversation, Message $message, SendOrigin $origin, ?string $summary): Message
    {
        $contact = $conversation->contact;

        try {
            $providerMessageId = $this->textChannel->send($contact->phone_e164, $message->body);
        } catch (RecipientOptedOut $exception) {
            $this->recordCarrierOptOut($contact, $message);

            throw new DomainException(self::CARRIER_OPTED_OUT, previous: $exception);
        } catch (Throwable $exception) {
            $this->recordFailure($contact, $message, $exception);

            throw new DomainException(self::NOT_SENT.$exception->getMessage(), previous: $exception);
        }

        $sentAt = now();

        try {
            DB::transaction(function () use ($conversation, $contact, $message, $providerMessageId, $origin, $summary, $sentAt): void {
                $message->update([
                    'status' => MessageStatus::Sent,
                    'provider_message_id' => $providerMessageId,
                    'sent_at' => $sentAt,
                ]);

                $contact->activities()->create([
                    'type' => ActivityType::ReplySent,
                    'summary' => $summary ?? ($origin === SendOrigin::Owner ? 'Reply sent by owner' : 'Reply sent by front desk'),
                    'subject_type' => $message->getMorphClass(),
                    'subject_id' => $message->id,
                    'occurred_at' => $sentAt,
                ]);

                $conversation->update(['last_message_at' => $sentAt]);
                $contact->update(['last_activity_at' => $sentAt]);
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->markSentAfterRecordingFailed($message, $providerMessageId, $sentAt);
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
     * the message as sent, with the provider's id and the moment the channel accepted it. If even that fails, the
     * failure is logged.
     */
    private function markSentAfterRecordingFailed(Message $message, string $providerMessageId, CarbonInterface $sentAt): void
    {
        try {
            $message->refresh()->update([
                'status' => MessageStatus::Sent,
                'provider_message_id' => $providerMessageId,
                'sent_at' => $sentAt,
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

    /**
     * The carrier's opt-out list was ahead of ours (Twilio error 21610): keep the refused message as failed, mark
     * the contact opted out so no other path tries again, and leave a note. A date already on the contact is kept.
     */
    private function recordCarrierOptOut(Contact $contact, Message $message): void
    {
        DB::transaction(function () use ($contact, $message): void {
            $message->update([
                'status' => MessageStatus::Failed,
                'meta' => [...($message->meta ?? []), 'error' => 'Opted out (21610)'],
            ]);

            if (! $contact->isOptedOut()) {
                $contact->update(['sms_opted_out_at' => now()]);
            }

            $contact->activities()->create([
                'type' => ActivityType::Note,
                'summary' => 'Not sent: the customer has opted out of texts (the carrier refused it)',
                'subject_type' => $message->getMorphClass(),
                'subject_id' => $message->id,
                'occurred_at' => now(),
            ]);
        });

        CustomerRecordChanged::dispatch($contact->id);
    }
}
