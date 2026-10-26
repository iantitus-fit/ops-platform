<?php

namespace App\Rooms\GoogleBusinessProfile;

use App\Icm\ActiveConfig;
use App\Rooms\RoomSection;

/**
 * The Google Business Profile room's dashboard section: whether the room is connected to Google. While the bound driver
 * is the fake, or no connector file fills the slot, it says so in plain words (05 D-17, D-18). The section never fetches
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

        if (! $this->connectedToGoogle()) {
            $lines[] = 'Not connected to Google: this room runs on a stand-in and sends nothing.';
        }

        return $lines;
    }

    /**
     * Connected means a live driver is bound and a connector file fills the slot. Only the fake exists this phase, so
     * the answer is no until the real connector lands.
     */
    private function connectedToGoogle(): bool
    {
        if (app(GoogleListingSource::class) instanceof FakeGoogleListingSource) {
            return false;
        }

        return collect(app(ActiveConfig::class)->filesOfType('connector'))
            ->contains(fn (array $file): bool => ($file['slot'] ?? null) === 'google_business_profile');
    }
}
