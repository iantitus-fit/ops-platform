<?php

namespace App\Rooms;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * The base every room's service provider extends. A room is one provider under app/Rooms/<Room>/ that says, in
 * room(), everything the room adds.
 *
 * register() records facts only: it adds the room's description to the RoomRegistry. Neither register() nor boot()
 * may call ActiveConfig, IcmVersion, DB or Filament::getPanel(), because boot runs before anything is synced and only
 * once per long-running worker. Every "is this room on" decision is made when the code runs, through
 * ActiveConfig::hasRoom(). Any scheduled task, queued job or timer a room adds must ask ActiveConfig::hasRoom() when
 * it runs and do nothing while the room is off, because an absent room runs no timers or jobs (D-08; no Phase 4 room
 * has any).
 *
 * AppServiceProvider binds the RoomRegistry singleton, so it stays first in bootstrap/providers.php. Real rooms are
 * listed after it.
 */
abstract class RoomServiceProvider extends ServiceProvider
{
    /**
     * Everything this room adds, as one description.
     */
    abstract protected function room(): Room;

    /**
     * Record the room in the registry. Nothing else.
     */
    public function register(): void
    {
        $this->app->make(RoomRegistry::class)->add($this->room());
    }

    /**
     * Add the room's model nicknames to the morph map. Relation::morphMap() merges with the list AppServiceProvider
     * enforces, so never call enforceMorphMap() here: it would replace that list. A room model used as the subject of
     * a timeline entry must be in this map, or Laravel refuses it.
     */
    public function boot(): void
    {
        Relation::morphMap($this->room()->morphMap);
    }
}
