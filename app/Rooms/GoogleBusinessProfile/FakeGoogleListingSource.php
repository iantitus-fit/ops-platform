<?php

namespace App\Rooms\GoogleBusinessProfile;

/**
 * The slot's stand-in driver: it returns a fictional listing, counts how often it was asked and reaches nothing outside
 * the app. It is the only driver this phase has (05 D-11).
 */
class FakeGoogleListingSource implements GoogleListingSource
{
    private int $fetchCount = 0;

    /**
     * @return array{name: string, phone: string, primary_category: string, additional_categories: list<string>, description: string, service_area: list<string>}
     */
    public function fetchListing(): array
    {
        $this->fetchCount++;

        return [
            'name' => 'Example Heating & Air',
            'phone' => '+13125550147',
            'primary_category' => 'HVAC contractor',
            'additional_categories' => ['Air conditioning repair service', 'Furnace repair service'],
            'description' => 'A family-run heating and cooling company serving Chicago and its closest suburbs.',
            'service_area' => ['Chicago', 'Evanston'],
        ];
    }

    /**
     * How many times the listing was fetched through this instance, for tests.
     */
    public function fetchCount(): int
    {
        return $this->fetchCount;
    }
}
