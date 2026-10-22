<?php

namespace App\Rooms;

/**
 * One kind of entry a room writes on a customer's timeline.
 */
readonly class TimelineEntryType
{
    /**
     * @param  string  $key  The entry's name, lower snake case.
     * @param  string  $label  The heading on the customer card.
     * @param  string  $intro  The line above the quote, such as "System note:".
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $intro,
    ) {}
}
