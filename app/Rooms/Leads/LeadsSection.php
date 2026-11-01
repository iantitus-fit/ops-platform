<?php

namespace App\Rooms\Leads;

use App\Enums\ProjectActor;
use App\Filament\Resources\Contacts\ContactResource;
use App\Icm\ActiveConfig;
use App\Models\Project;
use App\Rooms\RoomSection;
use Throwable;

/**
 * The leads room's dashboard section (D-15): the open projects, newest first, one row each. A row carries a link to the
 * contact's screen and an absolute date inside a time element, because the Phase 6 UI review found rows with no
 * keyboard link and times shown only as "3 hours ago". The section has its own view so the shared section view the other
 * rooms use, and RoomSection itself, stay as they are (research Pattern 6, A6). It only reads. The wording is a
 * placeholder until the wording pass in Phase 11.
 */
class LeadsSection extends RoomSection
{
    /**
     * The most rows the section draws. Every new lead opens a project and closing is by hand until Phase 8, so an
     * uncapped list would grow without bound; every project stays on its contact's screen.
     */
    public const SHOWN = 20;

    protected string $view = 'filament.rooms.leads-section';

    public static function roomKey(): string
    {
        return 'leads';
    }

    /**
     * One line when there is no open project, and one line giving the total when there are more than the section shows.
     *
     * @return list<string>
     */
    protected function contentLines(): array
    {
        $plural = mb_strtolower(app(ActiveConfig::class)->projectWord()->plural());
        $count = Project::query()->whereNull('closed_at')->count();

        if ($count === 0) {
            return ["No open {$plural}."];
        }

        if ($count > self::SHOWN) {
            return ['Showing the newest '.self::SHOWN." of {$count} open {$plural}."];
        }

        return [];
    }

    /**
     * The base class's heading, empty line and failure line, plus the rows. A throw while the rows are built is
     * reported and the section shows the failure line, so the dashboard still loads (05 D-32).
     *
     * @return array{key: string, heading: string, emptyLine: ?string, lines: list<string>, rows: list<array{contact: string, url: string, label: string, opened: string, datetime: string, origin: string}>}
     */
    protected function getViewData(): array
    {
        $data = parent::getViewData();
        $rows = [];

        if ($data['lines'] !== [self::FAILED_LINE] && $data['emptyLine'] === null) {
            try {
                $rows = $this->rows();
            } catch (Throwable $exception) {
                report($exception);

                $rows = [];
                $data['lines'] = [self::FAILED_LINE];
            }
        }

        return [...$data, 'rows' => $rows];
    }

    /**
     * The newest open projects, with each contact and opening user loaded up front so the number of queries does not
     * grow with the number of rows.
     *
     * @return list<array{contact: string, url: string, label: string, opened: string, datetime: string, origin: string}>
     */
    private function rows(): array
    {
        $config = app(ActiveConfig::class);
        $word = $config->projectWord();
        $timezone = $config->displayTimezone();
        $sourceNames = [];

        return Project::query()
            ->withNumber()
            ->whereNull('closed_at')
            ->with(['contact', 'openedByUser'])
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit(self::SHOWN)
            ->get()
            ->map(function (Project $project) use ($config, $word, $timezone, &$sourceNames): array {
                $opened = $project->opened_at->copy()->setTimezone($timezone);

                return [
                    'contact' => (string) ContactResource::getRecordTitle($project->contact),
                    'url' => ContactResource::getUrl('view', ['record' => $project->contact]),
                    'label' => $project->label($word, (int) $project->number),
                    'opened' => $opened->format('Y-m-d H:i'),
                    'datetime' => $opened->toIso8601String(),
                    'origin' => $project->opened_by === ProjectActor::Lead
                        ? 'source '.$this->sourceName((string) $project->contact->lead_source_key, $config, $sourceNames)
                        : 'opened by '.($project->openedByUser?->name ?? 'a removed user'),
                ];
            })
            ->all();
    }

    /**
     * The name lead-sources.md gives a source key, else the key as written when the file does not list it, else
     * "unknown" when the contact has no key (D-16). Each key is looked up once per draw, however many rows share it.
     * Printed escaped, so a key a form supplied is only text.
     *
     * @param  array<string, string>  $known  keys already looked up, filled as rows are built
     */
    private function sourceName(string $key, ActiveConfig $config, array &$known): string
    {
        if ($key === '') {
            return 'unknown';
        }

        return $known[$key] ??= $config->leadSource($key)['name'] ?? $key;
    }
}
