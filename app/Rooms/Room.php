<?php

namespace App\Rooms;

/**
 * Everything the code knows about one room, as plain facts. A Room holds class names as strings, so a room may point
 * at classes outside its own folder (D-19). Whether the room is on for a business is never stored here: that is
 * decided at run time by ActiveConfig::hasRoom().
 */
readonly class Room
{
    /**
     * @param  string  $key  Lower snake case, the folder name under rooms/ in the business folder.
     * @param  string  $label  The plain name used mid-sentence, lower case, such as "pretend". Display code applies ucfirst.
     * @param  list<class-string>  $sections  The dashboard widgets the room adds, as class names.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $sections = [],
    ) {}
}
