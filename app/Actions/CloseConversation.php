<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Events\CustomerRecordChanged;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;

class CloseConversation
{
    /**
     * Close a conversation and note it on the customer's timeline. A text that arrives later
     * starts a new conversation, and this one is kept as a past thread.
     */
    public function handle(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            $closedAt = now();

            $conversation->update([
                'status' => ConversationStatus::Closed,
                'closed_at' => $closedAt,
            ]);

            $conversation->contact->activities()->create([
                'type' => ActivityType::Note,
                'summary' => 'Conversation closed by owner',
                'occurred_at' => $closedAt,
            ]);
        });

        CustomerRecordChanged::dispatch($conversation->contact_id);
    }
}
