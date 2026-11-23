<?php

namespace App\Icm\Walks;

/**
 * What the walk's world held at the moment a check ran: the facts the outcome checks judge. A value, so a check can be
 * tried on a hand-built one with no app running.
 */
readonly class WalkWorldState
{
    public function __construct(
        public bool $booked,
        public bool $reminderQueued,
        public bool $cardShowsVisit,
        public bool $todayListsVisit,
        public bool $ownerAlertedInWalk,
    ) {}
}
