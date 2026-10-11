<?php

namespace App\Actions;

use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\CustomerRecordChanged;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendOwnerReply
{
    /**
     * The longest reply the owner may send: Twilio's limit for one concatenated text.
     */
    public const int MAX_LENGTH = 1600;

    public function __construct(private readonly TextChannel $textChannel) {}

    /**
     * Send the owner's reply to the customer by text and record it.
     *
     * Refusals are validation errors on the body, raised before anything is stored. The message
     * is stored as queued first and becomes sent only once the text channel has returned its id,
     * so a reply is never recorded as sent unless the channel accepted it. A channel failure
     * leaves the message stored as failed and is raised as a DomainException for the owner.
     *
     * Only one reply per conversation is sent at a time, so two tabs cannot both send. Once the
     * channel has accepted the text, a failure while recording it is reported to the log and
     * never raised: the customer has the text, and an error would invite the owner to send it again.
     *
     * @throws ValidationException when the reply cannot or should not go out
     * @throws DomainException when the text channel failed to send, or another reply is being sent
     */
    public function handle(Conversation $conversation, string $body): Message
    {
        $lock = Cache::lock("reply:{$conversation->id}", 30);

        if (! $lock->get()) {
            throw new DomainException('Another reply to this customer is being sent. Wait a moment, then check the thread before sending again.');
        }

        try {
            return $this->send($conversation, $body);
        } finally {
            $lock->release();
        }
    }

    private function send(Conversation $conversation, string $body): Message
    {
        $conversation->refresh();
        $body = trim($body);

        $refusal = $this->whyCannotReply($conversation) ?? $this->whyBodyIsRefused($body);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['body' => $refusal]);
        }

        $contact = $conversation->contact;

        $message = DB::transaction(fn (): Message => $conversation->messages()->create([
            'channel' => MessageChannel::Sms,
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Owner,
            'body' => $body,
            'status' => MessageStatus::Queued,
            'provider' => $this->textChannel->name(),
        ]));

        try {
            $providerMessageId = $this->textChannel->send($contact->phone_e164, $body);
        } catch (Throwable $exception) {
            $this->recordFailure($contact, $message, $exception);

            throw new DomainException("The reply was not sent: {$exception->getMessage()}", previous: $exception);
        }

        try {
            DB::transaction(function () use ($conversation, $contact, $message, $providerMessageId): void {
                $sentAt = now();

                $message->update([
                    'status' => MessageStatus::Sent,
                    'provider_message_id' => $providerMessageId,
                ]);

                $contact->activities()->create([
                    'type' => ActivityType::ReplySent,
                    'summary' => 'Reply sent by owner',
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
     * Why the owner cannot reply in this conversation at all, or null when a reply could go out.
     * The reply box shows this sentence in place of the live form.
     */
    public function whyCannotReply(Conversation $conversation): ?string
    {
        if ($conversation->status === ConversationStatus::Closed) {
            return 'This conversation is closed. Reopen it to reply.';
        }

        $contact = $conversation->contact;

        if (blank($contact->phone_e164)) {
            return filled($contact->email)
                ? "No phone number on file. Reply by email: {$contact->email}"
                : 'No phone number or email on file.';
        }

        return null;
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

    /**
     * Keep the failed attempt on the record: the message as failed with the error, and a note.
     */
    private function recordFailure(Contact $contact, Message $message, Throwable $exception): void
    {
        DB::transaction(function () use ($contact, $message, $exception): void {
            $message->update([
                'status' => MessageStatus::Failed,
                'meta' => ['error' => $exception->getMessage()],
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
