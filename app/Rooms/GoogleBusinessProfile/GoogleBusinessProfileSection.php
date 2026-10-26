<?php

namespace App\Rooms\GoogleBusinessProfile;

use App\Icm\ActiveConfig;
use App\Rooms\RoomSection;

/**
 * The Google Business Profile room's dashboard section: whether the room is connected to Google. While the configured
 * driver is the fake (or is not one that reaches Google), or no connector file fills the slot, it says so in plain words (05 D-17, D-18). The section never fetches
 * the listing: the dashboard only reads the folder's own files (05 D-11). The wording is a placeholder until the wording
 * pass.
 */
class GoogleBusinessProfileSection extends RoomSection
{
    public static function roomKey(): string
    {
        return 'google_business_profile';
    }

    /**
     * @return list<string>
     */
    protected function contentLines(): array
    {
        $lines = [];
        $categories = app(ActiveConfig::class)->filesOfType('gbp_categories');

        if ($categories !== [] && is_string($categories[0]['primary_category'] ?? null)) {
            $lines[] = 'Primary category: '.$categories[0]['primary_category'];
        }

        if (! $this->connectedToGoogle()) {
            $lines[] = 'Not connected to Google: this room runs on a stand-in and sends nothing.';
        }

        return $lines;
    }

    /**
     * The driver names that reach Google. Only the fake exists this phase, so the list is empty until the real
     * connector lands.
     *
     * @var list<string>
     */
    private const LIVE_DRIVERS = [];

    /**
     * Connected means a live driver is configured and a connector file fills the slot. It reads the configured driver
     * name and never builds the driver: this answers a display question while a dashboard widget draws, so a bad driver
     * setting (an unknown name, or a value that is not text) reads as not connected here and cannot take the page down.
     * The provider still refuses a bad setting for a real caller of the slot (WR-02).
     */
    private function connectedToGoogle(): bool
    {
        $driver = config('rooms.google_business_profile.driver', 'fake');

        if (! is_string($driver) || ! in_array($driver, self::LIVE_DRIVERS, true)) {
            return false;
        }

        return collect(app(ActiveConfig::class)->filesOfType('connector'))
            ->contains(fn (array $file): bool => ($file['slot'] ?? null) === 'google_business_profile');
    }
}
