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
use App\Icm\PlainWords;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RecordInbound
{
    /**
     * Source keys that never get an unknown-source note: texts and forms that named no source.
     */
    public const array RESERVED_SOURCES = ['text', 'direct'];

    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly DispatchInbound $dispatchInbound,
    ) {}

    /**
     * Record one inbound text or form: find or create the contact, put the message in the
     * contact's open conversation, and write the timeline entry, all or nothing.
     *
     * A message the provider already delivered is returned as it was recorded. DispatchInbound
     * queues ProcessInbound for a message only once, so a retry of a delivered message queues
     * nothing, and a retry of one that was recorded but never queued (a crash after commit,
     * WR-02) queues it then. A new message also announces CustomerRecordChanged once its
     * transaction has committed.
     */
    public function handle(InboundData $data): Message
    {
        $existing = $this->alreadyRecorded($data);

        if ($existing !== null) {
            $this->dispatchInbound->handle($existing);

            return $existing;
        }

        try {
            $message = DB::transaction(fn (): Message => $this->record($data));
        } catch (UniqueConstraintViolationException) {
            $existing = $this->alreadyRecorded($data);

            if ($existing !== null) {
                // A concurrent retry won the race; its transaction recorded the message.
                $this->dispatchInbound->handle($existing);

                return $existing;
            }

            // Not a duplicate message: another request created the same contact first. Its
            // contact is committed now, so one more attempt matches it instead of creating.
            $message = DB::transaction(fn (): Message => $this->record($data));
        }

        $this->dispatchInbound->handle($message);
        CustomerRecordChanged::dispatch($message->conversation->contact_id);

        return $message;
    }

    private function record(InboundData $data): Message
    {
        $occurredAt = now();
        [$leadSource, $sourceNotes] = $this->lookUpLeadSource($data->source);
        $costCents = $leadSource['cost_cents_per_lead'] ?? null;

        [$contact, $contactNotes, $matchedBy] = $this->matchOrCreateContact($data, $costCents);
        $notes = [...$sourceNotes, ...$contactNotes];
        $conversation = $this->openConversationFor($contact);

        $message = $conversation->messages()->create([
            'channel' => $data->channel,
            'direction' => MessageDirection::In,
            'author' => MessageAuthor::Customer,
            'body' => $data->body,
            'status' => MessageStatus::Received,
            'provider' => $data->provider,
            'provider_message_id' => $data->providerMessageId,
            'meta' => $data->channel === MessageChannel::Web
                ? [...$data->meta, 'matched_by' => $matchedBy]
                : $data->meta,
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
     * form gave becomes a note on the timeline instead, and so does an email another contact
     * already owns, so two contacts never share one. A name is the same: the form's name goes
     * on a contact only when the form creates it, because anyone can type any number into a
     * form; for a contact found by phone or email a different name is a note (D-43, WR-13).
     * The first-touch source and cost are set only when the contact is created.
     *
     * @return array{Contact, list<string>, string} the contact, the note summaries to put on the
     *                                              timeline, and how the contact was found: phone, email or new
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
            $contact = Contact::query()->where('email', $email)->orderBy('id')->first();
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

            return [$contact, $notes, 'new'];
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
                if (Contact::query()->where('email', $email)->whereKeyNot($contact->id)->exists()) {
                    $notes[] = "form gave an email another customer already has: {$email}";
                } else {
                    $contact->email = $email;
                }
            } elseif ($contact->email !== $email) {
                $notes[] = "form gave a different email: {$email}";
            }
        }

        $formName = trim((string) preg_replace('/\s+/u', ' ', (string) $data->name));

        if ($formName !== '' && mb_strtolower($formName) !== mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $contact->name)))) {
            $notes[] = 'form gave the name: '.PlainWords::oneLine($formName);
        }

        $contact->save();

        return [$contact, $notes, $matchedByEmail ? 'email' : 'phone'];
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
     * The source's entry in lead-sources.md, plus the timeline notes the lookup calls for.
     *
     * A lead is never dropped for its source. A key that is not listed is kept raw with a null
     * cost and an "unknown source" note, and so is every key when no business folder version
     * is live yet, in which case the note says so. The cost is null, never 0, whenever it is
     * not known. The reserved keys "text" and "direct" are not listed anywhere and get no note.
     *
     * @return array{array{key: string, name: string, cost_cents_per_lead: int}|null, list<string>}
     */
    private function lookUpLeadSource(string $key): array
    {
        $reserved = in_array($key, self::RESERVED_SOURCES, true);

        try {
            $source = $this->activeConfig->leadSource($key);
        } catch (NoActiveVersion) {
            return [null, $reserved ? [] : ["Cost per lead unknown for {$key}: no business folder version is live yet"]];
        }

        if ($source === null && ! $reserved) {
            return [null, ["unknown source: {$key}"]];
        }

        return [$source, []];
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
