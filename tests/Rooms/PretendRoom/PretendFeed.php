<?php

namespace Tests\Rooms\PretendRoom;

/**
 * The pretend room's connector slot: the one outside system a driver for this room talks to. The room's code asks for
 * this interface and config picks the driver, the way TextChannel works.
 */
interface PretendFeed
{
    /**
     * The driver's name, such as "fake".
     */
    public function name(): string;

    /**
     * Hand a note to the outside system and return the id it gave the note.
     */
    public function push(string $note): string;
}
