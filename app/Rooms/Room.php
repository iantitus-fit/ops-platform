<?php

namespace App\Rooms;

/**
 * Everything the code knows about one room, as plain facts. A Room names its classes as strings, so a room may point
 * at classes outside its own folder (D-19). Whether the room is on for a business is never stored here: that is
 * decided at run time by ActiveConfig::hasRoom().
 */
readonly class Room
{
    /**
     * @param  string  $key  Lower snake case, the folder name under rooms/ in the business folder.
     * @param  string  $label  The plain name used mid-sentence, lower case, such as "pretend". Display code applies ucfirst.
     * @param  list<string>  $handles  Hard-coded file types this room is the owner room for. No Phase 4 room lists any: the owner room for agents/*.md is left to the front desk room's phase (D-15).
     * @param  list<RoomFileType>  $fileTypes  New kinds of file the room defines (D-04).
     * @param  ?string  $slot  The name of the outside-system slot the room offers, such as "text".
     * @param  ?string  $slotInterface  The interface a driver for that slot implements, in any namespace (D-19).
     * @param  list<class-string>  $sections  The dashboard widgets the room adds, as class names.
     * @param  list<string>  $tables  The database tables the room owns.
     * @param  list<TimelineEntryType>  $timelineEntries  The kinds of entry the room writes on a customer's timeline.
     * @param  array<string, class-string>  $morphMap  Model nicknames the room adds, alias to model class.
     * @param  ?string  $neverLeftOutBecause  Why a business can never leave this room out, a plain clause that follows "because", such as "it owns the lead sources file the sync requires". Null for a room a business may leave out (05 D-02, 04 D-09).
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $handles = [],
        public array $fileTypes = [],
        public ?string $slot = null,
        public ?string $slotInterface = null,
        public array $sections = [],
        public array $tables = [],
        public array $timelineEntries = [],
        public array $morphMap = [],
        public ?string $neverLeftOutBecause = null,
    ) {}

    /**
     * Every file type this room is the owner for: the ones it handles plus the new ones it defines.
     *
     * @return list<string>
     */
    public function handledTypes(): array
    {
        return [...$this->handles, ...array_map(fn (RoomFileType $fileType): string => $fileType->type(), $this->fileTypes)];
    }
}
