<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\CustomerRecordChanged;
use App\Icm\ActiveConfig;
use App\Icm\Exceptions\NoActiveVersion;
use App\Jobs\ProcessInbound;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RecordInbound
{
    public function __construct(private readonly ActiveConfig $activeConfig) {}

    /**
     * Record one inbound text or form: find or create the contact, put the message in the
     * contact's open conversation, and write the timeline entry, all or nothing.
     *
     * A message the provider already delivered is returned as it was recorded, and nothing
     * is dispatched a second time. A new message queues ProcessInbound and announces
     * CustomerRecordChanged once its transaction has committed.
     */
    public function handle(InboundData $data): Message
    {
        $existing = $this->alreadyRecorded($data);

        if ($existing !== null) {
            return $existing;
        }

        try {
            $message = DB::transaction(fn (): Message => $this->record($data));
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent retry won the race; its transaction recorded the message.
            return $this->alreadyRecorded($data) ?? throw $exception;
        }

        ProcessInbound::dispatch($message->id)->afterCommit();
        CustomerRecordChanged::dispatch($message->conversation->contact_id);

        return $message;
    }

    private function record(InboundData $data): Message
    {
        $occurredAt = now();
        $leadSource = $this->leadSourceFor($data->source);
        $costCents = $leadSource['cost_cents_per_lead'] ?? null;

        [$contact, $notes] = $this->matchOrCreateContact($data, $costCents);
        $conversation = $this->openConversationFor($contact);

        $message = $conversation->messages()->create([
            'channel' => $data->channel,
            'direction' => MessageDirection::In,
            'author' => MessageAuthor::Customer,
            'body' => $data->body,
            'status' => MessageStatus::Received,
            'provider' => $data->provider,
            'provider_message_id' => $data->providerMessageId,
            'meta' => $data->meta,
        ]);
        $message->setRelation('conversation', $conversation);

        $contact->activities()->create([
            'type' => $this->activityTypeFor($data->channel),
            'summary' => $this->summaryFor($data, $leadSource['name'] ?? $data->source),
            'subject_type' => $message->getMorphClass(),
            'subject_id' => $message->id,
            'lead_source_key' => $data->source,
            'cost_cents' => $costCents,
            'occurred_at' => $occurredAt,
        ]);

        foreach ($notes as $note) {
            $contact->activities()->create([
                'type' => ActivityType::Note,
                'summary' => $note,
                'subject_type' => $message->getMorphClass(),
                'subject_id' => $message->id,
                'occurred_at' => $occurredAt,
            ]);
        }

        $contact->update(['last_activity_at' => $occurredAt]);
        $conversation->update(['last_message_at' => $occurredAt]);

        return $message;
    }

    private function alreadyRecorded(InboundData $data): ?Message
    {
        if ($data->provider === null || $data->providerMessageId === null) {
            return null;
        }

        return Message::query()
            ->where('provider', $data->provider)
            ->where('provider_message_id', $data->providerMessageId)
            ->first();
    }

    /**
     * Find the contact this inbound belongs to, or create one.
     *
     * The phone is tried first, then the email. This is written out by hand rather than with
     * Eloquent's update-or-create helper, which cannot express "phone, then email, and only
     * fill what is empty". A stored phone or email is never overwritten: a different one the
     * form gave becomes a note on the timeline instead. The first-touch source and cost are
     * set only when the contact is created.
     *
     * @return array{Contact, list<string>} the contact and the note summaries to put on the timeline
     */
    private function matchOrCreateContact(InboundData $data, ?int $costCents): array
    {
        $rawPhone = trim((string) $data->phone);
        $phone = PhoneNumber::toE164($rawPhone);
        $email = $this->normalizeEmail($data->email);

        $notes = [];

        if ($rawPhone !== '' && $phone === null) {
            $notes[] = "form gave a phone we could not read: {$rawPhone}";
        }

        $contact = $phone === null ? null : Contact::query()->where('phone_e164', $phone)->first();
        $matchedByEmail = false;

        if ($contact === null && $email !== null) {
            $contact = Contact::query()->where('email', $email)->first();
            $matchedByEmail = $contact !== null;
        }

        if ($contact === null) {
            $contact = Contact::create([
                'name' => $data->name,
                'phone_e164' => $phone,
                'email' => $email,
                'status' => ContactStatus::Lead,
                'lead_source_key' => $data->source,
                'acquisition_cost_cents' => $costCents,
            ]);

            return [$contact, $notes];
        }

        if ($matchedByEmail && $phone !== null) {
            if ($contact->phone_e164 === null) {
                $contact->phone_e164 = $phone;
            } elseif ($contact->phone_e164 !== $phone) {
                $notes[] = "form gave a different phone: {$phone}";
            }
        }

        if ($email !== null) {
            if ($contact->email === null) {
                $contact->email = $email;
            } elseif ($contact->email !== $email) {
                $notes[] = "form gave a different email: {$email}";
            }
        }

        $contact->save();

        return [$contact, $notes];
    }

    /**
     * An email as it is stored and compared: trimmed and lowercased, or null when empty.
     */
    private function normalizeEmail(?string $email): ?string
    {
        $normalized = mb_strtolower(trim((string) $email));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * The source's entry in lead-sources.md, or null when the key is not listed or no
     * business folder version is live yet. A missing version must never lose a lead.
     *
     * @return array{key: string, name: string, cost_cents_per_lead: int}|null
     */
    private function leadSourceFor(string $key): ?array
    {
        try {
            return $this->activeConfig->leadSource($key);
        } catch (NoActiveVersion) {
            return null;
        }
    }

    private function summaryFor(InboundData $data, string $sourceName): string
    {
        return $data->channel === MessageChannel::Sms ? 'Text received' : "Form received from {$sourceName}";
    }

    /**
     * The contact's one open conversation, created if there is none.
     *
     * The create runs in a nested transaction, which is a savepoint, so that losing a race
     * against a concurrent inbound does not abort the outer transaction on Postgres. The
     * database's partial unique index decides the winner and this inbound joins its thread.
     */
    private function openConversationFor(Contact $contact): Conversation
    {
        $open = $contact->openConversation()->first();

        if ($open !== null) {
            return $open;
        }

        try {
            return DB::transaction(
                fn (): Conversation => $contact->conversations()->create(['status' => ConversationStatus::Open]),
            );
        } catch (UniqueConstraintViolationException) {
            return $contact->openConversation()->firstOrFail();
        }
    }

    private function activityTypeFor(MessageChannel $channel): ActivityType
    {
        return $channel === MessageChannel::Sms ? ActivityType::TextReceived : ActivityType::FormReceived;
    }
}
