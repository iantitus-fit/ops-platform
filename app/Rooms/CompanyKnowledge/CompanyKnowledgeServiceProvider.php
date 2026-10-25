<?php

namespace App\Rooms\CompanyKnowledge;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;

/**
 * The room that holds what the business is and how it talks: the profile, the hours, the services, the FAQ and the
 * voice. Its files stay where they are, by kind (04 D-19), so the room adds no new file type, slot, table or timeline
 * entry (05 D-05) and has no register() or boot() of its own.
 */
class CompanyKnowledgeServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'company_knowledge',
            label: 'company knowledge',
            handles: ['business', 'hours', 'service', 'knowledge', 'voice'],
            sections: [CompanyKnowledgeSection::class],
            neverLeftOutBecause: 'it owns the profile, hours and voice files the sync requires',
        );
    }
}
