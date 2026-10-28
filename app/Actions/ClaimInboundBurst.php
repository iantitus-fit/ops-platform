<?php

namespace App\Actions;

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ClaimInboundBurst
{
    /**
     * Claim every unhandled inbound customer message in a conversation.
     *
     * One conditional update per row is atomic on SQLite and Postgres, so two runs can never
     * claim the same text: whichever update changes the row owns it, and the other run finds
     * nothing left. A duplicate run, a debounce that failed open and a sweep run are all
     * harmless for that reason (Pitfall 9).
     *
     * The token is written by the same update that claims the row, so every claimed row carries the run that claimed
     * it, and ProcessInbound::failed() can find that run's burst by it (06 D-12). Called without a token, the claim
     * makes a fresh one.
     *
     * @return Collection<int, Message> the messages this call claimed, oldest first; empty when nothing was left
     */
    public function handle(int $conversationId, ?string $claimToken = null): Collection
    {
        $claimToken ??= (string) Str::uuid();

        $candidateIds = Message::query()
            ->where('conversation_id', $conversationId)
            ->where('direction', MessageDirection::In)
            ->where('author', MessageAuthor::Customer)
            ->whereNull('handled_at')
            ->orderBy('id')
            ->pluck('id');

        $claimedIds = $candidateIds->filter(
            fn (int $id): bool => Message::query()
                ->whereKey($id)
                ->whereNull('handled_at')
                ->update(['handled_at' => now(), 'claim_token' => $claimToken]) === 1,
        );

        return Message::query()->whereKey($claimedIds->all())->orderBy('id')->get();
    }
}
