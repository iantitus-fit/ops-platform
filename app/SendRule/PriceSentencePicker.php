<?php

namespace App\SendRule;

use App\Actions\RecordIntakeAnswers;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\IntakeAnswer;
use Illuminate\Support\Collection;

/**
 * Picks the owner's price sentence for one service the customer asked about (D-04, D-05).
 *
 * The service is read only through ActiveConfig, so a slug the live folder does not have, a service the owner quotes
 * himself (price_policy none) and a service with no sentence all give a hold: the price question then reaches the
 * owner. The model's slug is never trusted beyond ActiveConfig finding it.
 *
 * A service whose sentences are keyed to intake answers is priced from the customer's stored answers. A question with
 * no answer yet is asked first; an answer that matches an option picks that option's sentence. An answer that matches
 * none is asked again once; after a second miss, and for any option the owner has no sentence for, the owner's default
 * sentence goes out, or the price holds when the file has none.
 *
 * The pick reads only stored rows and the live version, never the clock, so running it again on the same rows picks
 * the same sentence key.
 */
class PriceSentencePicker
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly RecordIntakeAnswers $recordIntakeAnswers,
    ) {}

    /**
     * @param  bool  $askedNow  Whether the customer asked for this price in this run, or it is still pending from an earlier one.
     * @param  Collection<int, IntakeAnswer>  $newAnswers  The intake answers stored in this run.
     */
    public function pick(Conversation $conversation, string $slug, bool $askedNow, Collection $newAnswers): PricePick
    {
        $service = $this->activeConfig->service($slug);

        if ($service === null || ($service['price_policy'] ?? null) === 'none') {
            return PricePick::hold($slug);
        }

        $sentences = array_values($service['price_sentences'] ?? []);
        $keyed = array_values(array_filter($sentences, fn (array $sentence): bool => ! empty($sentence['answers'])));

        if ($keyed === []) {
            return $this->pickUnkeyed($slug, $service, $sentences);
        }

        return $this->pickKeyed($conversation, $slug, $service, $keyed);
    }

    /**
     * The single sentence a service without keyed sentences has, or a hold when it has none.
     *
     * @param  array<string, mixed>  $service
     * @param  list<array<string, mixed>>  $sentences
     */
    private function pickUnkeyed(string $slug, array $service, array $sentences): PricePick
    {
        if (count($sentences) !== 1) {
            return PricePick::hold($slug);
        }

        $only = $sentences[0];
        $amounts = $only['amounts_cents'] ?? (isset($service['price_cents']) ? [$service['price_cents']] : []);

        return PricePick::send($slug, 'unkeyed', $only['sentence'], $this->amounts($amounts));
    }

    /**
     * The sentence for the customer's current answers, the default when no option fits, or the questions still to ask.
     *
     * @param  array<string, mixed>  $service
     * @param  list<array<string, mixed>>  $keyed
     */
    private function pickKeyed(Conversation $conversation, string $slug, array $service, array $keyed): PricePick
    {
        $keyingKeys = array_map('strval', array_keys($keyed[0]['answers']));
        $current = $this->recordIntakeAnswers->currentFor($conversation, $slug);

        $options = [];
        $missing = [];

        foreach ($keyingKeys as $key) {
            $row = $current->get($key);

            if ($row === null || ($row->option === null && $this->unmatchedCount($conversation, $slug, $key) === 1)) {
                $missing[] = $key;

                continue;
            }

            $options[$key] = $row->option;
        }

        if ($missing !== []) {
            return PricePick::needsAnswers($slug, $missing);
        }

        ksort($options);

        foreach ($keyed as $sentence) {
            $answers = array_map('strval', $sentence['answers']);
            ksort($answers);

            if ($answers === $options) {
                return PricePick::send($slug, $this->keyFor($answers), $sentence['sentence'], $this->amounts($sentence['amounts_cents'] ?? []));
            }
        }

        return $this->pickDefault($slug, $service);
    }

    /**
     * How many of the customer's answers to one question matched no option. One miss is asked again; a second is not
     * (D-06). Earlier misses do not matter once the newest answer matches, because the caller only asks when the
     * newest answer is itself a miss.
     */
    private function unmatchedCount(Conversation $conversation, string $slug, string $key): int
    {
        return $conversation->intakeAnswers()
            ->where('service_slug', $slug)
            ->where('question_key', $key)
            ->whereNull('option')
            ->count();
    }

    /**
     * The owner's default sentence, or a hold when the service file has none.
     *
     * @param  array<string, mixed>  $service
     */
    private function pickDefault(string $slug, array $service): PricePick
    {
        $default = $service['price_default'] ?? null;

        if (! is_array($default) || ! is_string($default['sentence'] ?? null)) {
            return PricePick::hold($slug);
        }

        return PricePick::send($slug, 'default', $default['sentence'], $this->amounts($default['amounts_cents'] ?? []));
    }

    /**
     * The sentence key of a keyed sentence: its answers as `key=Option` pairs, sorted by key and joined with `&`.
     *
     * @param  array<string, string>  $answers  Already sorted by key.
     */
    private function keyFor(array $answers): string
    {
        return implode('&', array_map(fn (string $key, string $option): string => $key.'='.$option, array_keys($answers), $answers));
    }

    /**
     * Every amount once, lowest first.
     *
     * @param  array<array-key, mixed>  $amounts
     * @return list<int>
     */
    private function amounts(array $amounts): array
    {
        $amounts = array_values(array_unique(array_map('intval', $amounts)));
        sort($amounts);

        return $amounts;
    }
}
