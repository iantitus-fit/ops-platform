<?php

namespace App\Icm\Walks;

use App\Enums\ReplyLabel;

/**
 * One check from a walk file, read from the owner's own words. The phrase is kept as written so a refusal or a result
 * line can quote it; the kind and the one word after it (a reply label or a service slug) are what the gate looks at.
 */
readonly class WalkCheck
{
    public function __construct(
        public WalkCheckKind $kind,
        public ?string $argument,
        public string $phrase,
    ) {}

    /**
     * Read a phrase from the closed list, or null when it is not on the list. Letter case does not matter, and a curly
     * apostrophe reads the same as a straight one, because a phone keyboard types the curly kind.
     */
    public static function parse(string $phrase): ?self
    {
        $written = trim($phrase);
        $normal = mb_strtolower(str_replace(["\u{2019}", "\u{2018}"], "'", $written));

        foreach (WalkCheckKind::cases() as $kind) {
            if (! $kind->takesWord() && $normal === $kind->phrase()) {
                return new self($kind, null, $written);
            }
        }

        if (preg_match('/^labeled ([a-z_]+)$/', $normal, $matches) === 1 && ReplyLabel::tryFrom($matches[1]) !== null) {
            return new self(WalkCheckKind::Labeled, $matches[1], $written);
        }

        if (preg_match("/^carries the owner's price sentence for ([a-z0-9]+(?:-[a-z0-9]+)*)$/", $normal, $matches) === 1) {
            return new self(WalkCheckKind::CarriesPriceSentence, $matches[1], $written);
        }

        return null;
    }

    /**
     * The phrases an owner may write in one place, for a refusal that lists them: after a customer text, or after a
     * booking or a wait and at the end of the walk.
     *
     * @return list<string>
     */
    public static function vocabulary(bool $afterSays): array
    {
        $kinds = array_filter(
            WalkCheckKind::cases(),
            fn (WalkCheckKind $kind) => $afterSays ? $kind->afterSays() : $kind->afterOutcome(),
        );

        return array_values(array_map(fn (WalkCheckKind $kind) => $kind->phrase(), $kinds));
    }

    /**
     * The first day, date or clock time a piece of text names, as written, or null when it names none. A day is a
     * weekday spelled out or in three letters ("Friday", "fri"), a date is year-month-day, and a clock time is "3pm",
     * "3 pm", "3:30pm" or "15:00". A walk that names one needs a start to anchor it (D-10).
     */
    public static function namedTime(string $text): ?string
    {
        $patterns = [
            '/\b(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)\b/i',
            '/\b\d{4}-\d{2}-\d{2}\b/',
            '/\b\d{1,2}(?::\d{2})?\s?(?:am|pm)\b/i',
            '/\b(?:[01]?\d|2[0-3]):[0-5]\d\b/',
        ];

        $found = null;
        $foundAt = PHP_INT_MAX;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === 1 && $matches[0][1] < $foundAt) {
                $found = $matches[0][0];
                $foundAt = $matches[0][1];
            }
        }

        return $found;
    }

    /**
     * Judge this check against what the candidate's code did in the walk's world. Null when it holds, otherwise the check
     * in plain words (10 D-09). Nothing is read here: the observation, the world and the folder's services come in as
     * values, so the words can be pinned with no app running.
     *
     * @param  array<string, array<string, mixed>>  $services  The folder's services, keyed by slug.
     */
    public function evaluate(WalkTurnObservation $seen, WalkWorldState $world, array $services): ?string
    {
        return match ($this->kind) {
            WalkCheckKind::SentOnItsOwn => $this->whySentOnItsOwnFailed($seen),
            WalkCheckKind::HeldForTheOwner => $this->whyHeldForTheOwnerFailed($seen),
            WalkCheckKind::HandedToTheOwner => $seen->handedToOwner ? null : 'the conversation was not handed to the owner',
            WalkCheckKind::Labeled => $this->whyLabeledFailed($seen),
            WalkCheckKind::CarriesPriceSentence => $this->whyPriceSentenceFailed($seen, $services),
            WalkCheckKind::CarriesBookingLink => in_array('booking_link', $seen->parts, true) ? null : 'the reply did not carry the booking link',
            WalkCheckKind::CarriesVisitLine => in_array('visit_line', $seen->parts, true) ? null : 'the reply did not carry the visit line',
            WalkCheckKind::OwnerAlerted => $seen->ownerAlerted ? null : 'the owner was not alerted',
            WalkCheckKind::Booked => $world->booked ? null : 'no visit was booked',
            WalkCheckKind::ReminderQueued => $world->reminderQueued ? null : 'no reminder was queued for the visit',
            WalkCheckKind::CardUpdated => $world->cardShowsVisit ? null : "the customer's card does not show the visit",
            WalkCheckKind::RecordsUpdated => $world->todayListsVisit ? null : 'records/today.md does not list the visit',
        };
    }

    private function whySentOnItsOwnFailed(WalkTurnObservation $seen): ?string
    {
        return match (true) {
            $seen->sentOnItsOwn => null,
            $seen->heldForOwner => 'the reply was held for the owner, not sent on its own',
            $seen->handedToOwner => 'the conversation was handed to the owner, not answered on its own',
            default => 'no reply was sent',
        };
    }

    /**
     * The sent case and the first two of the other cases are the plan's words; the last two are Ian's, ruled 2026-11-23.
     */
    private function whyHeldForTheOwnerFailed(WalkTurnObservation $seen): ?string
    {
        return match (true) {
            $seen->heldForOwner => null,
            $seen->sentOnItsOwn => 'the reply was sent on its own, not held for the owner',
            $seen->handedToOwner => 'the conversation was handed to the owner, not held for the owner',
            default => 'no reply was held for the owner',
        };
    }

    /**
     * A wrong label is the plan's words; no label to read, because no reply was sent and no draft was made, is Ian's,
     * ruled 2026-11-23, told apart by whether the conversation was handed to the owner.
     */
    private function whyLabeledFailed(WalkTurnObservation $seen): ?string
    {
        $expected = (string) $this->argument;

        return match (true) {
            $seen->label === $expected => null,
            $seen->label !== null => "the reply was labeled {$seen->label}, not {$expected}",
            $seen->handedToOwner => "the conversation was handed to the owner, so no reply was labeled {$expected}",
            default => "no reply was made, so none was labeled {$expected}",
        };
    }

    /**
     * The check holds when the text sent that turn holds one of the service's own price sentences, compared exactly. The
     * sentences are the candidate's, so a price the folder no longer holds fails whatever words were recorded.
     *
     * @param  array<string, array<string, mixed>>  $services
     */
    private function whyPriceSentenceFailed(WalkTurnObservation $seen, array $services): ?string
    {
        $slug = (string) $this->argument;

        if (! isset($services[$slug])) {
            return "the folder has no service {$slug} for the price sentence check";
        }

        foreach ((array) ($services[$slug]['price_sentences'] ?? []) as $entry) {
            $sentence = is_array($entry) ? (string) ($entry['sentence'] ?? '') : '';

            if ($sentence !== '' && str_contains($seen->sentText, $sentence)) {
                return null;
            }
        }

        return 'the reply did not carry the price sentence for '.($services[$slug]['name'] ?? $slug);
    }
}
