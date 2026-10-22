<?php

namespace App\Rooms;

readonly class Room
{
    /**
     * @param  list<class-string>  $sections
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $sections = [],
    ) {}
}
