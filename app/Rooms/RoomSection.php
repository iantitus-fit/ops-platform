<?php

namespace App\Rooms;

use App\Icm\ActiveConfig;
use Filament\Widgets\Widget;

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
     * The room this section belongs to, such as "pretend".
     */
    abstract public static function roomKey(): string;

    public static function canView(): bool
    {
        return app(ActiveConfig::class)->hasRoom(static::roomKey());
    }

    /**
     * @return array{key: string, heading: string}
     */
    protected function getViewData(): array
    {
        $key = static::roomKey();

        return [
            'key' => $key,
            'heading' => ucfirst(app(RoomRegistry::class)->get($key)?->label ?? $key),
        ];
    }
}
