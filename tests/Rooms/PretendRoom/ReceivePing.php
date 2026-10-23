<?php

namespace Tests\Rooms\PretendRoom;

use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Rooms\RecordRoomEntry;
use Illuminate\Support\Facades\DB;

/**
 * The pretend room's one action, and the proof that a room's slot, table and timeline entry work together: the note
 * goes out through the PretendFeed slot, lands in pretend_pings and appears on the customer's timeline, the last two in one transaction. While the
 * room is not in the live version it does nothing at all (D-08).
 */
class ReceivePing
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly PretendFeed $feed,
        private readonly RecordRoomEntry $recordRoomEntry,
    ) {}

    public function handle(Contact $contact, string $body): ?PretendPing
    {
        if (! $this->activeConfig->hasRoom('pretend')) {
            return null;
        }

        // The outside push comes first because its id is a column of the row. If the database work below fails, the
        // note stays in the outside system; the row and the timeline entry are written together or not at all.
        $feedId = $this->feed->push($body);

        return DB::transaction(function () use ($contact, $body, $feedId): PretendPing {
            $ping = PretendPing::query()->create([
                'contact_id' => $contact->id,
                'body' => $body,
                'feed_id' => $feedId,
            ]);

            $this->recordRoomEntry->handle($contact, 'pretend', 'ping_received', 'Ping from the pretend feed', $ping);

            return $ping;
        });
    }
}
