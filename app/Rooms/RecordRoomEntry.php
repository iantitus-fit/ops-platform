<?php

namespace App\Rooms;

use App\Enums\ActivityType;
use App\Events\CustomerRecordChanged;
use App\Icm\ActiveConfig;
use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The one way room code adds to a customer's timeline.
 *
 * A room writes only while it is in the live version (D-08), and only entry kinds its own code registered, so a
 * removed room goes quiet and a mistyped entry key shows up in the room's own tests. Like every other writer, it asks
 * for the customer's card to be redrawn afterwards (DEC-03).
 */
class RecordRoomEntry
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly RoomRegistry $rooms,
    ) {}

    /**
     * Write one room entry on the contact's timeline, or nothing at all when the room is not in the live version.
     *
     * @throws LogicException when the room never registered this entry key.
     */
    public function handle(Contact $contact, string $room, string $entry, string $summary, ?Model $subject = null): ?Activity
    {
        if (! $this->activeConfig->hasRoom($room)) {
            return null;
        }

        if ($this->rooms->timelineEntry($room, $entry) === null) {
            throw new LogicException("Room [{$room}] never registered a timeline entry [{$entry}]. Add it to the room's timelineEntries.");
        }

        $activity = $contact->activities()->create([
            'type' => ActivityType::RoomEntry,
            'room' => $room,
            'entry' => $entry,
            'summary' => $summary,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'occurred_at' => now(),
        ]);

        CustomerRecordChanged::dispatch($contact->id);

        return $activity;
    }
}
