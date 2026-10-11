<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Events\CustomerRecordChanged;
use App\Models\Conversation;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReopenConversation
{
    /**
     * Whether the Reopen action applies: the conversation is closed and its customer has no
     * other open conversation.
     */
    public function isAvailableFor(Conversation $conversation): bool
    {
        return $conversation->status === ConversationStatus::Closed
            && ! $this->customerHasAnotherOpenConversation($conversation);
    }

    /**
     * Reopen a closed conversation, unless its customer already has an open one: a customer
     * never has two open conversations. The database's partial unique index backs this check
     * up if another conversation opens at the same moment.
     *
     * @throws DomainException when the customer already has an open conversation
     */
    public function handle(Conversation $conversation): void
    {
        $contact = $conversation->contact;

        $refusal = ($contact->name ?: $contact->phone_e164 ?: $contact->email ?: 'This customer')
            .' already has an open conversation. Reply there instead.';

        if ($this->customerHasAnotherOpenConversation($conversation)) {
            throw new DomainException($refusal);
        }

        try {
            DB::transaction(function () use ($conversation, $contact): void {
                $conversation->update([
                    'status' => ConversationStatus::Open,
                    'closed_at' => null,
                ]);

                $contact->activities()->create([
                    'type' => ActivityType::Note,
                    'summary' => 'Conversation reopened by owner',
                    'occurred_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $conversation->refresh();

            throw new DomainException($refusal);
        }

        CustomerRecordChanged::dispatch($contact->id);
    }

    private function customerHasAnotherOpenConversation(Conversation $conversation): bool
    {
        return $conversation->contact->conversations()
            ->where('status', ConversationStatus::Open)
            ->whereKeyNot($conversation->id)
            ->exists();
    }
}
