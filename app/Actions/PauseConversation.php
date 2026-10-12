<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\ConversationStatus;
use App\Enums\PausedReason;
use App\Events\CustomerRecordChanged;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one way the assistant steps back from a conversation (D-23). Taking over, a request for a person, the owner's
 * own reply, the safety line and the reply cap all pause through here, so there is one paused state and one hand back.
 */
class PauseConversation
{
    /**
     * Whether the Take over control applies: the conversation is open and the assistant is still active.
     */
    public function isAvailableFor(Conversation $conversation): bool
    {
        return $conversation->status === ConversationStatus::Open && ! $conversation->isPaused();
    }

    /**
     * Pause the assistant in a conversation and note why on the customer's timeline.
     *
     * The conversation is read again under a row lock first. A second pause, from another tab or another cause,
     * would overwrite who paused and why and add a second note, so a conversation that is already paused is left
     * exactly as it is.
     */
    public function handle(Conversation $conversation, PausedReason $reason, ?User $by = null): void
    {
        $paused = DB::transaction(function () use ($conversation, $reason, $by): bool {
            Conversation::query()->lockForUpdate()->find($conversation->id);
            $conversation->refresh();

            if ($conversation->isPaused()) {
                return false;
            }

            $pausedAt = now();

            $conversation->update([
                'paused_at' => $pausedAt,
                'paused_reason' => $reason,
                'paused_by' => $by?->id,
            ]);

            $conversation->contact->activities()->create([
                'type' => ActivityType::AgentPaused,
                'summary' => $reason->timelineSummary(),
                'occurred_at' => $pausedAt,
            ]);

            return true;
        });

        if ($paused) {
            CustomerRecordChanged::dispatch($conversation->contact_id);
        }
    }
}
