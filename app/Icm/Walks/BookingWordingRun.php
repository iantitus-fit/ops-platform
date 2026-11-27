<?php

namespace App\Icm\Walks;

/**
 * One recorded run of a walk that expects a scheduling reply, replayed for the report (10 D-18, D-19): which walk, which
 * wording it was recorded with, how the replay came out in a sentence, and what the booking turns did.
 */
readonly class BookingWordingRun
{
    /**
     * @param  list<WalkTurnObservation>  $turns  The turns that expect a scheduling label, in script order.
     */
    public function __construct(
        public string $walkPath,
        public string $variant,
        public string $summary,
        public array $turns,
    ) {}
}
