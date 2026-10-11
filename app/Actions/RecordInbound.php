<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Jobs\ProcessInbound;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

class RecordInbound
{
    /**
     * Record one inbound text or form: find or create the contact, put the message in the
     * contact's open conversation, and write the timeline entry, all or nothing.
     */
    public function handle(InboundData $data): Message
    {
        $message = DB::transaction(function () use ($data): Message {
            $contact = $this->findOrCreateContact($data);

            $conversation = $contact->openConversation()->first()
                ?? $contact->conversations()->create(['status' => ConversationStatus::Open]);

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

            $contact->activities()->create([
                'type' => $this->activityTypeFor($data->channel),
                'summary' => 'Text received',
                'subject_type' => $message->getMorphClass(),
                'subject_id' => $message->id,
                'occurred_at' => now(),
            ]);

            $contact->update(['last_activity_at' => now()]);
            $conversation->update(['last_message_at' => now()]);

            return $message;
        });

        ProcessInbound::dispatch($message->id)->afterCommit();

        return $message;
    }

    private function findOrCreateContact(InboundData $data): Contact
    {
        $phone = PhoneNumber::toE164($data->phone);

        if ($phone !== null) {
            $existing = Contact::query()->where('phone_e164', $phone)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return Contact::create([
            'name' => $data->name,
            'phone_e164' => $phone,
            'status' => ContactStatus::Lead,
            'lead_source_key' => $data->source,
        ]);
    }

    private function activityTypeFor(MessageChannel $channel): ActivityType
    {
        return $channel === MessageChannel::Sms ? ActivityType::TextReceived : ActivityType::FormReceived;
    }
}
