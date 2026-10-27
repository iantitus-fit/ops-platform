<?php

namespace App\Rooms\FrontDesk;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

/**
 * The room that answers customer texts. Today's code (RunFrontDesk, ProcessInbound, OutboundSender, the send rule in
 * app/SendRule) stays where it is and the room points at it (04 D-19), so wrapping it changes no reply, hold or alert.
 *
 * It handles the agent type, so every agents/*.md is the front desk's (06 D-02). It offers the text slot, but names the
 * interface as a string, so only the outbound chokepoint and the alert sender use the class (06 D-19, and the unchanged
 * architecture test proves it). channels.text.driver keeps picking the driver, and a connector file for the slot comes
 * with CONN-01 (06 D-01). It owns the messages and conversations tables without moving a column (06 D-04) and adds no
 * timeline entry types, model nicknames or dashboard section (06 D-05), so it has no register() or boot() of its own.
 */
class FrontDeskServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'front_desk',
            label: 'front desk',
            handles: ['sending_rules', 'set_replies', 'triage', 'agent'],
            slot: 'text',
            slotInterface: 'App\Channels\TextChannel',
            tables: ['messages', 'conversations'],
            neverLeftOutBecause: 'it owns the sending, replies and triage files the sync requires',
        );
    }
}
