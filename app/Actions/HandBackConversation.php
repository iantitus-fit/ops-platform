<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Events\CustomerRecordChanged;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one way a paused conversation goes back to the assistant (D-23), whatever paused it.
 */
class HandBackConversation
{
    /**
     * Whether the Hand back control applies: the assistant is paused in this conversation.
     */
    public function isAvailableFor(Conversation $conversation): bool
    {
        return $conversation->isPaused();
    }

    /**
     * Clear the paused state and note the hand back on the customer's timeline. The next text from the customer is
     * answered by the assistant again.
     *
     * The conversation is read again under a row lock first, so a second tab pressing Hand back adds no second note
     * and a conversation that is already active is left exactly as it is.
     */
    public function handle(Conversation $conversation, ?User $by = null): void
    {
        $handedBack = DB::transaction(function () use ($conversation): bool {
            Conversation::query()->lockForUpdate()->find($conversation->id);
            $conversation->refresh();

            if (! $conversation->isPaused()) {
                return false;
            }

            $conversation->update([
                'paused_at' => null,
                'paused_reason' => null,
                'paused_by' => null,
            ]);

            $conversation->contact->activities()->create([
                'type' => ActivityType::AgentResumed,
                'summary' => 'Handed back to the assistant',
                'occurred_at' => now(),
            ]);

            return true;
        });

        if ($handedBack) {
            CustomerRecordChanged::dispatch($conversation->contact_id);
        }
    }
}
