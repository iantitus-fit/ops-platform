<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use App\Models\Appointment;
use App\Models\IcmVersion;
use App\Rooms\RoomRegistry;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use Carbon\CarbonImmutable;

/**
 * The checks on a folder that drops a room the live version has, beyond what the files alone can say. Today there is one:
 * the schedule and booking room may not be dropped while a visit is booked ahead (D-37). Dropping it would silence the
 * confirmations and reminders for those visits, so the sync refuses and the previous version stays live.
 *
 * This is the first sync check that reads the database. It reads only: booked appointments that start after now. Past,
 * cancelled and done appointments never block it. With no live version there is nothing to drop, so the check does not
 * run. It is built from the sync's own room registry, so a registry without the room (a hand-built test registry) gets no
 * check. 04 D-08 stands: a room that is off writes nothing, and this check writes nothing either.
 */
class RoomDropChecks
{
    private const ROOM = 'schedule_and_booking';

    public function __construct(private RoomRegistry $rooms, private RoomChecks $roomChecks) {}

    /**
     * What stops this folder from replacing the live version.
     *
     * @return list<array{file: string, field: ?string, message: string}>
     */
    public function errors(LoadedFolder $folder, ?IcmVersion $active): array
    {
        $room = $this->rooms->get(self::ROOM);

        if ($room === null || $active === null || ! array_key_exists(self::ROOM, $active->compiled['index']['room'] ?? [])) {
            return [];
        }

        if ($this->roomChecks->isPresent($room, collect($folder->files))) {
            return [];
        }

        $count = Appointment::query()->booked()->ahead()->count();
        $first = Appointment::query()->booked()->ahead()->orderBy('starts_at')->first();

        if ($count === 0 || $first === null) {
            return [];
        }

        $zone = $this->zone($folder, $active);
        $earliest = CarbonImmutable::instance($first->starts_at);
        $visits = $count === 1 ? '1 booked visit' : "{$count} booked visits";
        $day = BookingSettings::dayText($earliest, $zone);
        $time = BookingSettings::timeText($earliest, $zone);

        return [[
            'file' => "rooms/{$room->key}/CONTEXT.md",
            'field' => null,
            'message' => "the {$room->label} room has {$visits} still ahead, the earliest on {$day} at {$time}; keep the room, and to stop new bookings set book_online: false on the services instead",
        ]];
    }

    /**
     * The business's zone for the earliest visit: the candidate folder's own when its profile names one, otherwise the
     * live version's, otherwise the app's.
     */
    private function zone(LoadedFolder $folder, IcmVersion $active): string
    {
        $profile = collect($folder->files)->first(fn (IcmFile $file): bool => $file->kind === IcmFileKind::Typed && $file->type === 'business');
        $candidate = $profile?->frontMatter['timezone'] ?? null;

        if (is_string($candidate) && in_array($candidate, timezone_identifiers_list(), true)) {
            return $candidate;
        }

        foreach ($active->compiled['files'] ?? [] as $file) {
            $live = $file['type'] === 'business' ? ($file['front_matter']['timezone'] ?? null) : null;

            if (is_string($live) && in_array($live, timezone_identifiers_list(), true)) {
                return $live;
            }
        }

        return config('app.timezone');
    }
}
