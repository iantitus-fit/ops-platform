<?php

namespace App\SendRule;

use App\Icm\ActiveConfig;
use App\Models\Conversation;

/**
 * Picks the owner's price sentence for one service the customer asked about (D-04).
 *
 * The service is read only through ActiveConfig, so a slug the live folder does not have, a service the owner quotes
 * himself (price_policy none) and a service with no sentence all give a hold: the price question then reaches the
 * owner. The model's slug is never trusted beyond ActiveConfig finding it.
 */
class PriceSentencePicker
{
    public function __construct(private readonly ActiveConfig $activeConfig) {}

    public function pick(Conversation $conversation, string $slug): PricePick
    {
        $service = $this->activeConfig->service($slug);

        if ($service === null || ($service['price_policy'] ?? null) === 'none') {
            return PricePick::hold($slug);
        }

        $sentences = $service['price_sentences'] ?? [];

        if (count($sentences) === 1 && empty($sentences[0]['answers'])) {
            $only = $sentences[0];
            $amounts = $only['amounts_cents'] ?? (isset($service['price_cents']) ? [$service['price_cents']] : []);

            return PricePick::send($slug, 'unkeyed', $only['sentence'], array_values($amounts));
        }

        return PricePick::hold($slug);
    }
}
