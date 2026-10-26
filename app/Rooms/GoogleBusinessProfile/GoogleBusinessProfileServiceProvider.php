<?php

namespace App\Rooms\GoogleBusinessProfile;

use App\Rooms\Room;
use App\Rooms\RoomServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * The room that will keep the business's Google listing fed from the folder. This phase it has one read-only slot on a
 * fake driver and nothing reaches Google (ROOM-11, 05 D-11). It stays optional (04 D-08). The label is a proper noun, so
 * it keeps its capitals mid-sentence.
 */
class GoogleBusinessProfileServiceProvider extends RoomServiceProvider
{
    protected function room(): Room
    {
        return new Room(
            key: 'google_business_profile',
            label: 'Google Business Profile',
            slot: 'google_business_profile',
            slotInterface: GoogleListingSource::class,
            sections: [GoogleBusinessProfileSection::class],
        );
    }

    /**
     * Record the room, then bind its slot the way AppServiceProvider binds TextChannel: the fake is one shared instance,
     * and the closure reads the driver from config each time the slot is asked for. Only the fake exists, so there is no
     * config file and no .env key: the default is the fake.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(FakeGoogleListingSource::class);
        $this->app->bind(GoogleListingSource::class, function (Application $app): GoogleListingSource {
            $driver = config('rooms.google_business_profile.driver', 'fake');

            return match ($driver) {
                'fake' => $app->make(FakeGoogleListingSource::class),
                default => throw new InvalidArgumentException("Unknown Google Business Profile driver [{$driver}]. Use fake."),
            };
        });
    }
}
