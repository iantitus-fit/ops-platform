<?php

namespace App\Rooms\GoogleBusinessProfile;

/**
 * The Google Business Profile room's connector slot: the one outside system a driver for this room talks to. The room's
 * code asks for this interface and config picks the driver, the way TextChannel works.
 *
 * It is read-only by decision (05 D-11). Methods that send to Google (listing updates, posts, review replies) come with
 * the real connector, each through a chokepoint that records the owner's approval.
 */
interface GoogleListingSource
{
    /**
     * The listing as Google has it.
     *
     * @return array{name: string, phone: string, primary_category: string, additional_categories: list<string>, description: string, service_area: list<string>}
     */
    public function fetchListing(): array;
}
