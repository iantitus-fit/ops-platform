<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Actions\MintBookingLink;
use App\Models\BookingLink;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;

/**
 * Takes back a booking link that never reached the customer and puts back the links its mint replaced (D-12, D-45). A
 * link minted for a text that was refused, failed or never composed must change nothing the customer holds: the link
 * stops working, and the earlier unbooked links that minting it superseded work again.
 */
class WithdrawUnsentLink
{
    public function __construct(private readonly MintBookingLink $mintBookingLink) {}

    /**
     * The ids of the conversation's links that minting a new one would supersede: those with no appointment, revoked_at
     * or superseded_at (exactly the rows MintBookingLink::handle() marks). Read just before minting and handed back to
     * handle() if the text does not go out.
     *
     * @return list<int>
     */
    public function idsAMintWouldSupersede(Conversation $conversation): array
    {
        return BookingLink::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('superseded_at')
            ->whereNull('revoked_at')
            ->whereDoesntHave('appointment')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $supersededIds  The ids of the conversation's links the mint superseded: those with no appointment, revoked_at or superseded_at just before it.
     */
    public function handle(BookingLink $link, array $supersededIds): void
    {
        DB::transaction(function () use ($link, $supersededIds): void {
            $this->mintBookingLink->revoke($link);
            $this->mintBookingLink->restoreSuperseded($supersededIds);
        });
    }
}
