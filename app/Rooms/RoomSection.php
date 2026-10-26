<?php

namespace App\Rooms;

use App\Icm\ActiveConfig;
use Filament\Widgets\Widget;
use Throwable;

/**
 * A room's dashboard section. Filament asks canView() each time the dashboard draws, and the section answers by asking
 * the live version whether it has this room, so adding or removing rooms/<room>/ turns the section on or off at the
 * next sync with no restart. The first HTML carries the section: a lazy widget would send a placeholder first.
 */
abstract class RoomSection extends Widget
{
    protected static ?int $sort = 20;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.rooms.section';

    /**
     * The one plain line a section shows in place of its content when building that content fails (D-32). The wording
     * is a placeholder until the wording pass in Phase 11.
     */
    public const FAILED_LINE = 'This section could not load. The rest of the dashboard is fine.';

    /**
     * The room this section belongs to, such as "pretend".
     */
    abstract public static function roomKey(): string;

    public static function canView(): bool
    {
        return app(ActiveConfig::class)->hasRoom(static::roomKey());
    }

    /**
     * The one plain line an empty room shows, built from its contract (D-21): null when the contract owns nothing, or
     * when any owned file is already live. An owns entry ending in /*.md or /* reads as "a file to <folder>/", any other
     * entry reads as written. The wording is a placeholder until the wording pass in Phase 11.
     *
     * @param  list<string>  $owns
     * @param  list<string>  $ownedFiles
     */
    public static function emptyLine(string $label, array $owns, array $ownedFiles): ?string
    {
        if ($owns === [] || $ownedFiles !== []) {
            return null;
        }

        $things = array_map(
            fn (string $entry): string => preg_match('#^(.*/)\*(\.md)?$#', $entry, $folder) === 1 ? "a file to {$folder[1]}" : $entry,
            $owns,
        );

        $last = array_pop($things);
        $named = $things === [] ? $last : implode(', ', $things).' or '.$last;

        return ucfirst($label).": nothing set up yet. Add {$named} to start.";
    }

    /**
     * The room's own content while it is not empty: one plain sentence per line. A room overrides this; the default is
     * nothing to show.
     *
     * @return list<string>
     */
    protected function contentLines(): array
    {
        return [];
    }

    /**
     * The platform draws the empty line for every room, so no room builds its own empty screen (D-21). The section is
     * not lazy, so it draws inside the dashboard's own page: anything that throws while the empty line or the room's
     * lines are built is reported to the log and the section shows FAILED_LINE instead, so one room can never take down
     * the dashboard or another section (D-32).
     *
     * @return array{key: string, heading: string, emptyLine: ?string, lines: list<string>}
     */
    protected function getViewData(): array
    {
        $key = static::roomKey();
        $heading = ucfirst(app(RoomRegistry::class)->get($key)?->label ?? $key);

        try {
            $config = app(ActiveConfig::class);
            $emptyLine = static::emptyLine($heading, $config->roomContract($key)['owns'] ?? [], $config->roomOwnedFiles($key));
            $lines = $emptyLine === null ? $this->contentLines() : [];
        } catch (Throwable $exception) {
            report($exception);

            $emptyLine = null;
            $lines = [self::FAILED_LINE];
        }

        return [
            'key' => $key,
            'heading' => $heading,
            'emptyLine' => $emptyLine,
            'lines' => $lines,
        ];
    }
}
