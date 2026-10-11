<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerRecordChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * Fired whenever something happens to a customer, so the card in records/ can be
     * re-rendered from the database. It carries only the id so a listener always reads
     * the newest state. Plan 01-05 adds that listener.
     */
    public function __construct(public int $contactId) {}
}
