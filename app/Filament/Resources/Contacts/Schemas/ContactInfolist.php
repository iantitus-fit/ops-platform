<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ActivityType;
use App\Icm\ActiveConfig;
use App\Models\Activity;
use App\Rooms\RoomRegistry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $timezone = app(ActiveConfig::class)->displayTimezone();

        return $schema
            ->components([
                Section::make('Contact')
                    ->schema([
                        TextEntry::make('name')
                            ->placeholder('(no name)'),
                        TextEntry::make('phone_e164')
                            ->label('Phone')
                            ->placeholder('(no phone)'),
                        TextEntry::make('email')
                            ->placeholder('(no email)'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('lead_source_key')
                            ->label('Source')
                            ->badge(),
                        TextEntry::make('acquisition_cost_cents')
                            ->label('Cost per lead')
                            ->money('USD', divideBy: 100)
                            ->placeholder('(unknown)'),
                        TextEntry::make('created_at')
                            ->label('First contact')
                            ->dateTime(timezone: $timezone),
                        TextEntry::make('last_activity_at')
                            ->label('Last activity')
                            ->dateTime(timezone: $timezone)
                            ->placeholder('(none)'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make('Timeline')
                    ->description('Newest first')
                    ->schema([
                        RepeatableEntry::make('timeline')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('occurred_at')
                                    ->label('When')
                                    ->dateTime(timezone: $timezone),
                                TextEntry::make('type')
                                    ->badge()
                                    ->formatStateUsing(fn (ActivityType $state, Activity $record): string => self::activityBadge($state, $record)),
                                TextEntry::make('summary'),
                                TextEntry::make('subject.body')
                                    ->label('Message')
                                    ->visible(fn (?string $state): bool => filled($state)),
                                TextEntry::make('lead_source_key')
                                    ->label('Source')
                                    ->badge()
                                    ->visible(fn (?string $state): bool => filled($state)),
                                TextEntry::make('cost_cents')
                                    ->label('Cost')
                                    ->money('USD', divideBy: 100)
                                    ->visible(fn (?int $state): bool => $state !== null),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The badge on a timeline entry: the room's own label for an entry a room wrote, "Room entry" when the room's code
     * no longer registers it, and the type's own value for every other entry, as before.
     */
    private static function activityBadge(ActivityType $type, Activity $activity): string
    {
        if ($type !== ActivityType::RoomEntry) {
            return $type->value;
        }

        $entry = app(RoomRegistry::class)->timelineEntry((string) $activity->room, (string) $activity->entry);

        return $entry === null ? 'Room entry' : ucfirst($entry->label);
    }
}
