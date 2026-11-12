<?php

namespace App\Actions;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The second way a conversation starts (D-30): the platform opens one to send a text when the customer has none open, such
 * as a reminder after the owner closed the thread. The first way is RecordInbound, when the customer texts. It opens no
 * project and changes no status; the contact is left exactly as it was.
 *
 * The contact's open conversation is used when there is one. Otherwise one is created in a nested transaction, which is a
 * savepoint, so that losing a race against another send or an inbound text does not abort the outer transaction on
 * Postgres. The partial unique index conversations_one_open_per_contact decides the winner and this send joins its thread
 * (RecordInbound::openConversationFor()'s pattern, copied so RecordInbound stays as it is).
 */
class OpenConversationForText
{
    public function handle(Contact $contact): Conversation
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
}
