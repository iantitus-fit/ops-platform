<?php

namespace Tests\Rooms\PretendRoom;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;
use App\Rooms\TimelineEntryType;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * The room that exists only in tests. It is never listed in bootstrap/providers.php: enablePretendRoom() in
 * tests/Pest.php registers it for the one test that asks (D-20).
 */
class PretendRoomServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'pretend',
            label: 'pretend',
            fileTypes: [new PretendNoteType],
            slot: 'pretend_feed',
            slotInterface: PretendFeed::class,
            sections: [PretendRoomSection::class],
            tables: ['pretend_pings'],
            timelineEntries: [new TimelineEntryType(key: 'ping_received', label: 'pretend ping', intro: 'System note:')],
            morphMap: ['pretend_ping' => PretendPing::class],
        );
    }

    /**
     * Record the room, then bind its slot the way AppServiceProvider binds TextChannel: the fake is one shared
     * instance, and the closure reads the driver from config each time the slot is asked for.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(FakePretendFeed::class);
        $this->app->bind(PretendFeed::class, function (Application $app): PretendFeed {
            $driver = config('pretend.feed.driver', 'fake');

            return match ($driver) {
                'fake' => $app->make(FakePretendFeed::class),
                default => throw new InvalidArgumentException("Unknown pretend feed driver [{$driver}]. Use fake."),
            };
        });
    }

    /**
     * Merge the room's model nicknames, then load the room's migrations. The pretend room's table exists only in tests,
     * so only this test provider loads a migration path; a real room keeps its migrations in database/migrations (D-18).
     */
    public function boot(): void
    {
        parent::boot();

        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
