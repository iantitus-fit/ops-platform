<?php

namespace Tests\Rooms\PretendRoom;

use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Rooms\RecordRoomEntry;

/**
 * The pretend room's one action, and the proof that a room's slot, table and timeline entry work together: the note
 * goes out through the PretendFeed slot, lands in pretend_pings and appears on the customer's timeline. While the
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

        $ping = PretendPing::query()->create([
            'contact_id' => $contact->id,
            'body' => $body,
            'feed_id' => $this->feed->push($body),
        ]);

        $this->recordRoomEntry->handle($contact, 'pretend', 'ping_received', 'Ping from the pretend feed', $ping);

        return $ping;
    }
}
