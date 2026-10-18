<?php

namespace App\SendRule;

use App\Actions\RecordIntakeAnswers;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
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
 * A sentence the customer was already told is not sent again unless they ask again, and then it goes with the owner's
 * "same" lead. When a changed answer picks a different sentence, it goes right away with the lead for the way the price
 * moved (D-09). A sentence the owner has no lead for goes alone.
 *
 * The pick reads only stored rows, the live version and the last sentence sent, never the clock, so running it again on
 * the same rows picks the same sentence key.
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
            return $askedNow ? PricePick::hold($slug) : PricePick::nothing($slug);
        }

        $sentences = array_values($service['price_sentences'] ?? []);
        $keyed = array_values(array_filter($sentences, fn (array $sentence): bool => ! empty($sentence['answers'])));

        if (! $askedNow && ! $this->isCorrected($slug, $keyed, $newAnswers)) {
            return PricePick::nothing($slug);
        }

        $candidate = $keyed === []
            ? $this->pickUnkeyed($slug, $service, $sentences)
            : $this->pickKeyed($conversation, $slug, $service, $keyed);

        if (! $candidate->isSend()) {
            return $askedNow ? $candidate : PricePick::nothing($slug);
        }

        return $this->withLead($conversation, $candidate, $askedNow);
    }

    /**
     * The last price sentence of a service that reached, or is on its way to, the customer: its key and the amounts it
     * named, read from the newest queued, sent or delivered text whose meta names the service.
     *
     * @return array{key: string, amounts_cents: list<int>}|null
     */
    public function lastSent(Conversation $conversation, string $slug): ?array
    {
        $messages = $conversation->messages()
            ->where('direction', MessageDirection::Out)
            ->whereIn('status', [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered])
            ->reorder()
            ->orderByRaw('coalesce(sent_at, created_at) desc')
            ->orderByDesc('id')
            ->lazy();

        foreach ($messages as $message) {
            foreach ($message->meta['price_sentences'] ?? [] as $entry) {
                if (($entry['slug'] ?? null) === $slug && is_string($entry['key'] ?? null)) {
                    return ['key' => $entry['key'], 'amounts_cents' => $this->amounts($entry['amounts_cents'] ?? [])];
                }
            }
        }

        return null;
    }

    /**
     * How a new price compares with the one the customer was last told, by integer cents (D-09, D-39). The same amounts
     * are `same`. A new range that starts at or above where the old one ended is `higher`, and one that ends at or below
     * where the old one started is `lower`, so ranges that only touch still say which way the price went. Ranges that
     * overlap are `changed`.
     *
     * @param  list<int>  $oldCents
     * @param  list<int>  $newCents
     */
    public function direction(array $oldCents, array $newCents): string
    {
        $old = $this->amounts($oldCents);
        $new = $this->amounts($newCents);

        if ($old === [] || $new === []) {
            return 'changed';
        }

        return match (true) {
            $old === $new => 'same',
            $new[0] >= $old[count($old) - 1] => 'higher',
            $new[count($new) - 1] <= $old[0] => 'lower',
            default => 'changed',
        };
    }

    /**
     * Whether this run stored an answer to one of the service's keying questions, which can change the price.
     *
     * @param  list<array<string, mixed>>  $keyed
     * @param  Collection<int, IntakeAnswer>  $newAnswers
     */
    private function isCorrected(string $slug, array $keyed, Collection $newAnswers): bool
    {
        if ($keyed === []) {
            return false;
        }

        $keyingKeys = array_map('strval', array_keys($keyed[0]['answers']));

        return $newAnswers->contains(fn (IntakeAnswer $answer): bool => $answer->service_slug === $slug && in_array($answer->question_key, $keyingKeys, true));
    }

    /**
     * The sentence to send given what the customer was last told: nothing when it is the same sentence and was not
     * asked for again, the same lead when it is asked for again, and the lead for the way the price moved when a
     * different sentence replaces it. A customer who was never told a price gets the sentence only if they asked.
     */
    private function withLead(Conversation $conversation, PricePick $candidate, bool $askedNow): PricePick
    {
        $last = $this->lastSent($conversation, $candidate->slug);

        if ($last === null) {
            return $askedNow ? $candidate : PricePick::nothing($candidate->slug);
        }

        if ($last['key'] === $candidate->sentenceKey) {
            return $askedNow ? $this->leadWith($candidate, 'same') : PricePick::nothing($candidate->slug);
        }

        return $this->leadWith($candidate, $this->direction($last['amounts_cents'], $candidate->amountsCents));
    }

    private function leadWith(PricePick $pick, string $direction): PricePick
    {
        return PricePick::send($pick->slug, (string) $pick->sentenceKey, (string) $pick->sentence, $pick->amountsCents, $direction);
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
     * How many of the customer's answers to one question matched no option since the newest answer that matched one.
     * One miss is asked again; a second is not (D-06). A miss, a match and a miss is one miss, so a customer who
     * corrects a matched answer to something unclear is asked again before the default goes out (WR-06).
     */
    private function unmatchedCount(Conversation $conversation, string $slug, string $key): int
    {
        $answers = $conversation->intakeAnswers()
            ->where('service_slug', $slug)
            ->where('question_key', $key);

        $lastMatchId = (clone $answers)->whereNotNull('option')->max('id');

        return $answers
            ->whereNull('option')
            ->when($lastMatchId !== null, fn ($query) => $query->where('id', '>', $lastMatchId))
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
