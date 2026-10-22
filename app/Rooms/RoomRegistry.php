<?php

namespace App\Rooms;

use Filament\Panel;

/**
 * The one container-held list of rooms that exist in code.
 *
 * It holds facts about code only. It never reads the business folder, ActiveConfig or the database, so it is safe to
 * fill while the app starts. Every room adds itself from its provider's register(), and the sync and the dashboard
 * read the same list. It keeps its rooms in an instance property, never a static one, so nothing leaks between
 * tests.
 */
class RoomRegistry
{
    /**
     * @var array<string, Room>
     */
    private array $rooms = [];

    public function add(Room $room): void
    {
        $this->rooms[$room->key] = $room;
    }

    public function has(string $key): bool
    {
        return isset($this->rooms[$key]);
    }

    public function get(string $key): ?Room
    {
        return $this->rooms[$key] ?? null;
    }

    /**
     * Every room, in key order whatever order they were added in.
     *
     * @return list<Room>
     */
    public function all(): array
    {
        $rooms = $this->rooms;
        ksort($rooms, SORT_STRING);

        return array_values($rooms);
    }

    /**
     * Every room's dashboard widgets, rooms in key order.
     *
     * @return list<class-string>
     */
    public function widgetClasses(): array
    {
        return array_merge(...array_map(fn (Room $room): array => $room->sections, $this->all()));
    }

    /**
     * Add the rooms' widgets to the panel, skipping any it already has, and hand the panel back.
     */
    public function addWidgetsTo(Panel $panel): Panel
    {
        $existing = $panel->getWidgets();

        return $panel->widgets(array_values(array_filter(
            $this->widgetClasses(),
            fn (string $widget): bool => ! in_array($widget, $existing, true),
        )));
    }
}
