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
     *
     * The conversation is read again under a row lock first. Another tab may have closed it
     * since this page loaded, and closing twice would move closed_at and add a second note to
     * the timeline, so a conversation that is already closed is left exactly as it is.
     */
    public function handle(Conversation $conversation): void
    {
        $closed = DB::transaction(function () use ($conversation): bool {
            Conversation::query()->lockForUpdate()->find($conversation->id);
            $conversation->refresh();

            if ($conversation->status === ConversationStatus::Closed) {
                return false;
            }

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

            return true;
        });

        if ($closed) {
            CustomerRecordChanged::dispatch($conversation->contact_id);
        }
    }
}
