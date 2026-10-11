<?php

namespace App\Listeners;

use App\Events\CustomerRecordChanged;
use App\Jobs\RenderCustomerCard;

class QueueCustomerRecordRender
{
    /**
     * Queue a re-render of the customer's card. Laravel finds this listener by the event
     * type-hinted on handle(), so it needs no registration.
     */
    public function handle(CustomerRecordChanged $event): void
    {
        RenderCustomerCard::dispatch($event->contactId);
    }
}
