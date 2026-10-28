<?php

namespace App\Rooms\Team;

use App\Filament\Widgets\DraftsWaiting;
use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

/**
 * The room through which the owner hears about things and acts on them: owner alerts, draft approvals and the record of
 * who sent, edited or discarded each draft. Its code (RaiseOwnerAlert, SendOwnerAlert, SendDueAlerts, the draft actions)
 * stays where it is and the room points at it (04 D-19), so wrapping it changes no alert, approval or who-acted record.
 *
 * It owns no file. It reads owner_alert in rules/sending.md, owner_name in rules/replies.md and escalate_to in
 * agents/front-desk.md, all of which the front desk owns (04 D-12, D-13). It owns the owner_alerts table; who acted stays
 * in messages.resolved_by and messages.resolution, which the front desk owns, and no column moves (06 D-04). It claims
 * Drafts waiting as a pointer (06 D-03, D-18) and adds no timeline entry types, model nicknames or dashboard section
 * (06 D-05), so it has no register() or boot() of its own. Alert routing is code, not an agent action (06 D-09).
 */
class TeamServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'team',
            label: 'team',
            tables: ['owner_alerts'],
            claimedWidgets: [DraftsWaiting::class],
        );
    }
}
