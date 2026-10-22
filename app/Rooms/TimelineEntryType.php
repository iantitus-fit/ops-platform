<?php

namespace App\Rooms;

readonly class TimelineEntryType
{
    public function __construct(
        public string $key,
        public string $label,
        public string $intro,
    ) {}
}
