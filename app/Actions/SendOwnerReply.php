<?php

namespace App\Actions;

use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\CustomerRecordChanged;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class SendOwnerReply
{
    public function __construct(private readonly TextChannel $textChannel) {}

    /**
     * Send the owner's reply to the customer by text and record it.
     *
     * The message is stored as queued first and becomes sent only once the text channel has
     * returned its id, so a reply is never recorded as sent unless the channel accepted it.
     */
    public function handle(Conversation $conversation, string $body): Message
    {
        $body = trim($body);
        $contact = $conversation->contact;

        $message = DB::transaction(fn (): Message => $conversation->messages()->create([
            'channel' => MessageChannel::Sms,
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Owner,
            'body' => $body,
            'status' => MessageStatus::Queued,
            'provider' => $this->textChannel->name(),
        ]));

        $providerMessageId = $this->textChannel->send($contact->phone_e164, $body);

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

        CustomerRecordChanged::dispatch($contact->id);

        return $message;
    }
}
