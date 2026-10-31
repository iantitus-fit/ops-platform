<?php

namespace App\Rooms\Leads;

use App\Filament\Widgets\NewLeads;
use App\Models\Contact;
use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

/**
 * The room that turns a new lead into a project, a job the business tracks. It owns no file: it reads the lead sources
 * and the offers, which marketing owns (07 D-18). Projects are the platform's own, so the room lists no tables (07 D-10),
 * and it writes nothing to records/ (07 D-17). It is optional: a business that leaves rooms/leads/ out still activates
 * and the room is simply off (07 D-03).
 *
 * Its dashboard section lists the open projects (07 D-15). It claims New leads as a pointer, so the widget stays where
 * the admin panel puts it and looks the same (07 D-15). The web form is shared and owned by no room, so it keeps taking
 * leads while this room is off (07 D-19). The `needs` field waits for the Phase 8 discuss (07 D-20). It has no
 * register() in this plan.
 *
 * boot() listens for a new contact, which is how the room learns of a new lead without a line changing in
 * RecordInbound (07 D-02). The listener fires inside the inbound's transaction, so it only asks whether the room is
 * live and queues the open for after the commit. boot() itself reads nothing.
 */
class LeadsServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'leads',
            label: 'leads',
            sections: [LeadsSection::class],
            claimedWidgets: [NewLeads::class],
        );
    }

    /**
     * Record the room's model nicknames, then listen for a contact being created.
     */
    public function boot(): void
    {
        parent::boot();

        Contact::created(fn (Contact $contact) => OpenProjectForNewLead::queueFor($contact));
    }
}
