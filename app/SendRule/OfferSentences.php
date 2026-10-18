<?php

namespace App\SendRule;

use App\Icm\ActiveConfig;
use Carbon\CarbonImmutable;

/**
 * Picks the owner's offer sentences that may go out today (D-12, D-13).
 *
 * Code checks dates only, never whether this customer qualifies: every offer is written as a conditional sentence, and
 * the business confirms at the visit that the customer meets the condition. An offer is live from its start date
 * through its end date inclusive, as calendar dates in the business's time zone, so an offer that ends on the 31st is
 * still live at 11:30 pm on the 31st even though UTC is already the 1st. Offers are read only through ActiveConfig.
 */
class OfferSentences
{
    public function __construct(private readonly ActiveConfig $activeConfig) {}

    /**
     * The sentence of every offer live today, in the folder's path order.
     *
     * @return list<array{slug: string, sentence: string}>
     */
    public function liveToday(): array
    {
        $today = CarbonImmutable::now($this->activeConfig->timezone())->toDateString();

        $live = [];

        foreach ($this->activeConfig->offers() as $offer) {
            if ($this->isLive($offer, $today)) {
                $live[] = ['slug' => (string) $offer['slug'], 'sentence' => (string) $offer['sentence']];
            }
        }

        return $live;
    }

    /**
     * Whether the offer runs on the given day: it has started, and it has no end date or has not passed it. Both
     * dates and the day are `Y-m-d` text, which compares correctly as text.
     *
     * @param  array<string, mixed>  $offer
     * @param  string  $today  A `Y-m-d` date in the business's time zone.
     */
    public function isLive(array $offer, string $today): bool
    {
        $start = (string) $offer['start_date'];
        $end = $offer['end_date'] ?? null;

        return $start <= $today && ($end === null || $today <= (string) $end);
    }
}
