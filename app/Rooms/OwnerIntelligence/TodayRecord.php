<?php

namespace App\Rooms\OwnerIntelligence;

use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Records\CustomerCard;
use App\Records\MarkdownText;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The words of records/today.md, the owner's AI's view of what is on today (RECS-02, D-12, D-13). It only reads: it
 * draws a title and a body from the database and returns strings, and RecordsWriter alone puts them in a file.
 *
 * The day is the business-zone day that holds the moment given (D-12). Its rows are every visit that starts in that day
 * in all three states, plus every booked block that overlaps it, ordered by start and then by id so the file reads the
 * same every time it is drawn. A row carries a time span, the service, a link to the customer's card and the visit's
 * state; a customer's phone, address, visit note and job label never appear (D-12). Owner and customer text goes only
 * through MarkdownText, so a name or service cannot pass for a heading or an instruction.
 */
class TodayRecord
{
    /**
     * What the file says when nothing is booked and nothing is blocked in the day.
     */
    public const string EMPTY_DAY = 'Nothing booked today.';

    /**
     * What the file says when the business has no schedule room (D-15): it keeps its schedule somewhere else.
     */
    public const string OUTSIDE_SYSTEM = 'This business keeps its schedule outside this system.';

    public function __construct(
        private readonly ActiveConfig $config,
        private readonly CustomerCard $customerCard,
        private readonly MarkdownText $markdown,
    ) {}

    /**
     * The file's title, naming the business-zone date it covers so a stale file never reads as today.
     */
    public function title(CarbonImmutable $now): string
    {
        return 'Schedule for '.BookingSettings::dayText($now, $this->config->displayTimezone());
    }

    /**
     * The file's body: the zone, a blank line and one bullet per visit or block in the day.
     */
    public function body(CarbonImmutable $now): string
    {
        $zone = $this->config->displayTimezone();
        $start = $now->setTimezone($zone)->startOfDay();
        $end = $start->addDay();

        $rows = [...$this->visitRows($start->utc(), $end->utc(), $zone), ...$this->blockRows($start->utc(), $end->utc(), $zone)];

        usort($rows, fn (array $a, array $b): int => [$a['starts_at'], $a['id']] <=> [$b['starts_at'], $b['id']]);

        return implode("\n", [
            'Times are in '.BookingSettings::zoneName($zone).'.',
            '',
            ...array_map(fn (array $row): string => $row['text'], $rows),
        ]);
    }

    /**
     * Every visit that starts in the day, in any state, with its contact loaded once.
     *
     * @return list<array{starts_at: int, id: int, text: string}>
     */
    private function visitRows(CarbonInterface $start, CarbonInterface $end, string $zone): array
    {
        $rows = [];

        $visits = Appointment::query()
            ->with('contact')
            ->where('starts_at', '>=', $start)
            ->where('starts_at', '<', $end)
            ->get();

        foreach ($visits as $visit) {
            $name = $this->markdown->inline($visit->contact?->name);
            $link = $visit->contact === null ? $name : "[{$name}](customers/{$this->customerCard->filename($visit->contact)})";

            $rows[] = [
                'starts_at' => $visit->starts_at->getTimestamp(),
                'id' => $visit->id,
                'text' => '- '.BookingSettings::spanText($visit->starts_at, $visit->ends_at, $zone)
                    .': '.$this->markdown->inline($visit->service_name).' for '.$link.', '.$visit->state->value,
            ];
        }

        return $rows;
    }

    /**
     * Every booked block that overlaps the day: it starts before the day ends and ends after the day starts.
     *
     * @return list<array{starts_at: int, id: int, text: string}>
     */
    private function blockRows(CarbonInterface $start, CarbonInterface $end, string $zone): array
    {
        $rows = [];

        $blocks = ScheduleBlock::query()
            ->booked()
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->get();

        foreach ($blocks as $block) {
            $rows[] = [
                'starts_at' => $block->starts_at->getTimestamp(),
                'id' => $block->id,
                'text' => '- '.BookingSettings::spanText($block->starts_at, $block->ends_at, $zone).': Blocked',
            ];
        }

        return $rows;
    }
}
