<?php

namespace App\Rooms\Marketing;

use App\Icm\ActiveConfig;
use App\Rooms\RoomSection;
use App\SendRule\OfferSentences;

/**
 * The marketing room's dashboard section: how many lead sources the business lists and which offers are live today.
 * "Live today" is decided only by OfferSentences, the code the front desk uses to pick the offer sentences it may send,
 * so the dashboard and the front desk can never disagree (05 D-17). The wording is a placeholder until the wording pass.
 */
class MarketingSection extends RoomSection
{
    public static function roomKey(): string
    {
        return 'marketing';
    }

    /**
     * @return list<string>
     */
    protected function contentLines(): array
    {
        $config = app(ActiveConfig::class);

        $names = array_map(
            fn (array $live): string => (string) ($config->offer($live['slug'])['name'] ?? $live['slug']),
            app(OfferSentences::class)->liveToday(),
        );

        return [
            'Lead sources: '.count($config->leadSources()),
            'Offers live today: '.($names === [] ? 'none' : implode(', ', $names)),
        ];
    }
}
