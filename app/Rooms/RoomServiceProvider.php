<?php

namespace App\Rooms;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use LogicException;

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
 * listed after it. The morph map check does not depend on that order (see boot()).
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
     * enforces, so never call enforceMorphMap() here: it would replace that list. A nickname that already points at a
     * different model is refused, because the merge would silently win over it and every stored row using the nickname
     * would then resolve to the wrong model. A model that already has a nickname is refused under a second one, because
     * Laravel would start writing its rows under the new name. A room model used as the subject of a timeline entry must be in this map, or
     * Laravel refuses it.
     *
     * The check and the merge wait until every provider has booted, so the order of bootstrap/providers.php stops
     * mattering: a room listed above AppServiceProvider would otherwise merge first and then lose to the app's nickname
     * without a word. When the app has already booted, Laravel runs the callback straight away.
     */
    public function boot(): void
    {
        $this->app->booted(function (): void {
            $morphMap = $this->room()->morphMap;

            foreach ($morphMap as $alias => $class) {
                $existing = Relation::getMorphedModel($alias);

                if ($existing !== null && $existing !== $class) {
                    throw new LogicException("Room [{$this->room()->key}] morph alias [{$alias}] is already used for [{$existing}]. A room may not replace an existing nickname. Pick another name.");
                }

                $currentAlias = array_search($class, Relation::morphMap(), true);

                if ($currentAlias !== false && $currentAlias !== $alias) {
                    throw new LogicException("Room [{$this->room()->key}] maps [{$class}], which already has the nickname [{$currentAlias}]. A model takes one nickname, so its stored rows keep resolving.");
                }
            }

            Relation::morphMap($morphMap);
        });
    }
}
