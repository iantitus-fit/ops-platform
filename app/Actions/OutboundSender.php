<?php

namespace App\Actions;

use App\Channels\RecipientOptedOut;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\ConversationStatus;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\SendOrigin;
use App\Events\CustomerRecordChanged;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\SendRule\CustomerReading;
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
 *
 * It is also the second chokepoint (D-45): an automated text goes out only with a reading of the customer's text
 * attached to its gate (gate.customer_reading). The safety line is the one exception and is never asked for one. A
 * reading marked missing counts as no reading at all (Ian, 2026-10-16). A refused send is never sent and the owner
 * is told: a text with no reading raises safety_check_missing, always right away and once per customer text.
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

    /**
     * Why a text with no reading of the customer's message was stopped, as the owner reads it in the alert.
     */
    private const string NO_READING_WHY = "A text was stopped because the safety check had not read the customer's message";

    public function __construct(
        private readonly TextChannel $textChannel,
        private readonly ActiveConfig $activeConfig,
        private readonly RaiseOwnerAlert $raiseOwnerAlert,
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

        if ($origin === SendOrigin::Automated) {
            $this->refuseWithoutReading($conversation, $attributes);
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
     * Refuse an automated text that lacks what its gate must carry (D-45, D-49), tell the owner, and stop. A text with
     * no reading of the customer's text, or one marked missing, raises safety_check_missing; the owner is told at once
     * at any hour, once per customer text. A text that has a reading but lacks something else raises front_desk_failed,
     * on the folder's owner_alert timing.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws DomainException
     */
    private function refuseWithoutReading(Conversation $conversation, array $attributes): void
    {
        $why = $this->whyReadingBlocks($attributes);

        if ($why === null) {
            return;
        }

        $this->alertAboutRefusal(
            $conversation,
            $this->readingOf($attributes) === null ? AlertReason::SafetyCheckMissing : AlertReason::FrontDeskFailed,
            $why,
        );

        throw new DomainException(self::NO_READING.$why);
    }

    /**
     * Why an automated text lacks what it needs from the reading of the customer's text, or null when it has it.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function whyReadingBlocks(array $attributes): ?string
    {
        $reading = $this->readingOf($attributes);

        if ($reading === null) {
            return self::NO_READING_WHY;
        }

        foreach ($this->partsOf($attributes) as $part) {
            $why = match ($part) {
                'code_sentences' => $reading->allowsOwnerSentences() ? null : "an owner sentence needs a clean reading of the customer's text",
                'model_words' => $this->whyModelWordsAreBlocked($attributes, $reading),
                default => null,
            };

            if ($why !== null) {
                return $why;
            }
        }

        return null;
    }

    /**
     * The parts a text is made of. A combined text records them in meta.parts. A text with no parts is the model's words
     * when its kind is agent_reply, and a text with no kind is treated the same, so a path that forgets to name its kind
     * is held to the strictest rule. Any other kind is a fixed line, which needs only the reading record (Phase 2 D-12).
     *
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private function partsOf(array $attributes): array
    {
        $parts = data_get($attributes, 'meta.parts');

        if (is_array($parts) && $parts !== []) {
            return array_values(array_filter($parts, is_string(...)));
        }

        $kind = $attributes['kind'] ?? null;
        $kind = is_string($kind) ? MessageKind::tryFrom($kind) : $kind;

        return $kind === null || $kind === MessageKind::AgentReply ? ['model_words'] : [];
    }

    /**
     * Why the model's words may not go out, or null when they may: they need their own words check to have passed (the
     * send rule let them send and Jev ran on them and found them sendable), and a reading of the customer's text that ran
     * and did not fail (D-45, D-49, D-50).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function whyModelWordsAreBlocked(array $attributes, CustomerReading $reading): ?string
    {
        $outcome = $attributes['gate_outcome'] ?? null;
        $classifier = data_get($attributes, 'gate.checks.classifier');

        $passed = ($outcome === GateOutcome::Send || $outcome === GateOutcome::Send->value)
            && is_array($classifier)
            && ($classifier['ran'] ?? false) === true
            && (data_get($classifier, 'reading.sendable') ?? false) === true;

        if (! $passed) {
            return "the reply's words did not pass the safety check";
        }

        if (! $reading->ran || $reading->failed) {
            return "the safety check could not read the customer's message, so the reply's words cannot go out";
        }

        return null;
    }

    /**
     * The reading attached to a text's gate, or null when there is none: absent, malformed, or marked missing.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function readingOf(array $attributes): ?CustomerReading
    {
        $gate = $attributes['gate'] ?? null;

        return CustomerReading::fromStoredIfMade(is_array($gate) ? ($gate['customer_reading'] ?? null) : null);
    }

    /**
     * Tell the owner a text was stopped, about the conversation's newest customer text. A second refused send for the
     * same customer text and reason adds no second alert, because RaiseOwnerAlert skips one that is already there. A
     * failure to raise the alert is reported and never hides the refusal.
     */
    private function alertAboutRefusal(Conversation $conversation, AlertReason $reason, string $why): void
    {
        try {
            $newestCustomerText = $conversation->messages()
                ->reorder('id', 'desc')
                ->where('direction', MessageDirection::In)
                ->first();

            $this->raiseOwnerAlert->handle($conversation, $reason, $newestCustomerText, $why);
        } catch (Throwable $exception) {
            report($exception);
        }
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
