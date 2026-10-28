<?php

namespace App\Rooms;

use App\Icm\Loader;
use Filament\Panel;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The one container-held list of rooms that exist in code.
 *
 * It holds facts about code only. It never reads the business folder, ActiveConfig or the database, so it is safe to
 * fill while the app starts. Every room adds itself from its provider's register(), and the sync and the dashboard
 * read the same list. It keeps its rooms in an instance property, never a static one, so nothing leaks between
 * tests.
 *
 * add() refuses anything that would make two rooms collide, so a mistake shows the moment the app starts.
 */
class RoomRegistry
{
    /**
     * Lower snake case: lowercase letters and digits, with single underscores between words. It ends in \z, not $, which
     * also matches before a final line break.
     */
    private const NAME_PATTERN = '/^[a-z0-9]+(_[a-z0-9]+)*\z/';

    /**
     * File type words no room may define, besides the ones Loader::TYPED already lists: room contracts and the types
     * that belong to later sprints.
     *
     * @var list<string>
     */
    private const RESERVED_TYPES = ['room', 'connector', 'automation', 'walk_test'];

    /**
     * @var array<string, Room>
     */
    private array $rooms = [];

    /**
     * @throws LogicException when the room would collide with one already added, or is malformed.
     */
    public function add(Room $room): void
    {
        $this->guardKey($room);
        $this->guardFileTypes($room);
        $this->guardSlot($room);
        $this->guardMorphMap($room);
        $this->guardHandles($room);
        $this->guardSections($room);
        $this->guardClaimedWidgets($room);
        $this->guardTimelineEntries($room);

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
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(fn (Room $room): string => $room->key, $this->all());
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

    /**
     * The new file type words rooms define, rooms in key order.
     *
     * @return list<string>
     */
    public function registeredTypes(): array
    {
        return array_merge(...array_map(
            fn (Room $room): array => array_map(fn (RoomFileType $fileType): string => $fileType->type(), $room->fileTypes),
            $this->all(),
        ));
    }

    public function fileType(string $type): ?RoomFileType
    {
        foreach ($this->all() as $room) {
            foreach ($room->fileTypes as $fileType) {
                if ($fileType->type() === $type) {
                    return $fileType;
                }
            }
        }

        return null;
    }

    /**
     * The rooms that are the owner for a file type, in key order. Rooms may repeat a type another room handles.
     *
     * @return list<Room>
     */
    public function roomsHandling(string $type): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (Room $room): bool => in_array($type, $room->handledTypes(), true),
        ));
    }

    public function roomWithSlot(string $slot): ?Room
    {
        foreach ($this->all() as $room) {
            if ($room->slot === $slot) {
                return $room;
            }
        }

        return null;
    }

    /**
     * The slot names rooms offer, sorted.
     *
     * @return list<string>
     */
    public function slots(): array
    {
        $slots = array_values(array_filter(array_map(fn (Room $room): ?string => $room->slot, $this->all())));
        sort($slots, SORT_STRING);

        return $slots;
    }

    public function timelineEntry(string $room, string $entry): ?TimelineEntryType
    {
        foreach ($this->get($room)?->timelineEntries ?? [] as $timelineEntry) {
            if ($timelineEntry->key === $entry) {
                return $timelineEntry;
            }
        }

        return null;
    }

    private function guardKey(Room $room): void
    {
        if (preg_match(self::NAME_PATTERN, $room->key) !== 1) {
            throw new LogicException("Room key [{$room->key}] must be lower_snake_case: lowercase letters and digits, with single underscores between words.");
        }

        if ($this->has($room->key)) {
            throw new LogicException("A room with the key [{$room->key}] is already registered. Each room key must be unique.");
        }
    }

    private function guardFileTypes(Room $room): void
    {
        $seen = [];

        foreach ($room->fileTypes as $fileType) {
            $type = $fileType->type();

            if (preg_match(self::NAME_PATTERN, $type) !== 1) {
                throw new LogicException("Room [{$room->key}] defines file type [{$type}], which must be lower_snake_case.");
            }

            if (in_array($type, Loader::TYPED, true) || in_array($type, self::RESERVED_TYPES, true)) {
                throw new LogicException("Room [{$room->key}] defines file type [{$type}], which the sync already uses. Pick another name.");
            }

            if (in_array($type, $seen, true) || in_array($type, $this->registeredTypes(), true)) {
                throw new LogicException("Room [{$room->key}] defines file type [{$type}], which another room already defines. Pick another name.");
            }

            $seen[] = $type;
        }
    }

    private function guardSlot(Room $room): void
    {
        if ($room->slot === null && $room->slotInterface === null) {
            return;
        }

        if ($room->slot === null) {
            throw new LogicException("Room [{$room->key}] sets slotInterface [{$room->slotInterface}] without a slot name. Set both or neither.");
        }

        if ($room->slotInterface === null) {
            throw new LogicException("Room [{$room->key}] sets slot [{$room->slot}] without a slotInterface. Set both or neither.");
        }

        if (preg_match(self::NAME_PATTERN, $room->slot) !== 1) {
            throw new LogicException("Room [{$room->key}] names its slot [{$room->slot}], which must be lower_snake_case.");
        }

        if ($this->roomWithSlot($room->slot) !== null) {
            throw new LogicException("Room [{$room->key}] names its slot [{$room->slot}], which another room already uses. Pick another name.");
        }

        if (! interface_exists($room->slotInterface)) {
            throw new LogicException("Room [{$room->key}] says its slot [{$room->slot}] is filled by [{$room->slotInterface}], but that interface does not exist.");
        }
    }

    /**
     * A room's model nicknames must be a map of lower_snake_case alias to model class. No two rooms may use the same
     * alias, and no model class may be mapped twice, by one room or by two, because Laravel stores a model under the
     * first nickname it finds for the class, so a second nickname would change the name its rows are written under. A
     * class name that does not exist yet is let through, because registry tests use placeholder names. Collisions with
     * the app's own nicknames cannot be seen here, because the registry fills during register() and AppServiceProvider
     * sets those nicknames in boot(); RoomServiceProvider::boot() checks them.
     */
    private function guardMorphMap(Room $room): void
    {
        foreach ($room->morphMap as $alias => $class) {
            if (! is_string($alias) || preg_match(self::NAME_PATTERN, $alias) !== 1) {
                throw new LogicException("Room [{$room->key}] morph alias [{$alias}] must be a lower_snake_case name pointing at a model class, such as ['pretend_ping' => PretendPing::class].");
            }

            if (! is_string($class)) {
                throw new LogicException("Room [{$room->key}] morph alias [{$alias}] must point at a model class, such as ['pretend_ping' => PretendPing::class].");
            }

            if (class_exists($class) && ! is_subclass_of($class, Model::class)) {
                throw new LogicException("Room [{$room->key}] morph alias [{$alias}] points at [{$class}], which is not an Eloquent model.");
            }

            if (array_search($class, $room->morphMap, true) !== $alias) {
                throw new LogicException("Room [{$room->key}] maps [{$class}] under more than one morph alias. A model takes one nickname.");
            }

            foreach ($this->rooms as $other) {
                if (array_key_exists($alias, $other->morphMap)) {
                    throw new LogicException("Room [{$room->key}] morph alias [{$alias}] is already used by room [{$other->key}]. Pick another name.");
                }

                if (in_array($class, $other->morphMap, true)) {
                    throw new LogicException("Room [{$room->key}] maps [{$class}], which room [{$other->key}] already maps under a nickname. A model takes one nickname.");
                }
            }
        }
    }

    /**
     * Every file type a room says it handles must be a lower_snake_case word, so a typo shows at start-up.
     */
    private function guardHandles(Room $room): void
    {
        foreach ($room->handles as $type) {
            if (! is_string($type) || preg_match(self::NAME_PATTERN, $type) !== 1) {
                throw new LogicException("Room [{$room->key}] handles file type [{$type}], which must be a lower_snake_case word.");
            }
        }
    }

    /**
     * A section that exists as a class must be a RoomSection for this very room, because only a RoomSection hides
     * itself while its room is off (D-08). A class name that does not exist yet is let through, because registry tests
     * use placeholder names.
     */
    private function guardSections(Room $room): void
    {
        foreach ($room->sections as $section) {
            if (! class_exists($section)) {
                continue;
            }

            if (! is_subclass_of($section, RoomSection::class)) {
                throw new LogicException("Room [{$room->key}] lists section [{$section}], which is not a RoomSection. Only a RoomSection hides itself while its room is off.");
            }

            if ($section::roomKey() !== $room->key) {
                throw new LogicException("Room [{$room->key}] lists section [{$section}], which belongs to room [{$section::roomKey()}].");
            }
        }
    }

    /**
     * A claim is a pointer and never feeds widgetClasses() or addWidgetsTo(): the panel already finds the widget on its
     * own. Unlike guardSections(), a class that does not exist is refused, because a claim names a widget the panel
     * already has, so a typo shows at start-up (06 D-18). Each widget has one owner room, a room claims it once, and a
     * room never both adds a widget in its sections and claims it, which would put it on the panel twice (06-REVIEW WR-04).
     */
    private function guardClaimedWidgets(Room $room): void
    {
        $seen = [];

        foreach ($room->claimedWidgets as $widget) {
            if (in_array($widget, $seen, true)) {
                throw new LogicException("Room [{$room->key}] claims widget [{$widget}] more than once.");
            }

            if (in_array($widget, $room->sections, true)) {
                throw new LogicException("Room [{$room->key}] both adds and claims widget [{$widget}]. Add it in sections, or claim it, not both.");
            }

            $seen[] = $widget;

            if (! class_exists($widget)) {
                throw new LogicException("Room [{$room->key}] claims widget [{$widget}], which does not exist.");
            }

            if (! is_subclass_of($widget, Widget::class)) {
                throw new LogicException("Room [{$room->key}] claims widget [{$widget}], which is not a Filament widget. A room claims only a dashboard widget the panel already has.");
            }

            foreach ($this->rooms as $other) {
                if (in_array($widget, $other->claimedWidgets, true)) {
                    throw new LogicException("Room [{$room->key}] claims widget [{$widget}], which room [{$other->key}] already claims. A widget has one owner room.");
                }
            }
        }
    }

    /**
     * Timeline entry keys must be lower_snake_case and unique inside the room, because timelineEntry() returns the
     * first match and a duplicate would never be reached.
     */
    private function guardTimelineEntries(Room $room): void
    {
        $seen = [];

        foreach ($room->timelineEntries as $entry) {
            if (preg_match(self::NAME_PATTERN, $entry->key) !== 1) {
                throw new LogicException("Room [{$room->key}] timeline entry [{$entry->key}] must be lower_snake_case.");
            }

            if (in_array($entry->key, $seen, true)) {
                throw new LogicException("Room [{$room->key}] lists the timeline entry [{$entry->key}] more than once. Each entry key must be unique inside the room.");
            }

            $seen[] = $entry->key;
        }
    }
}
