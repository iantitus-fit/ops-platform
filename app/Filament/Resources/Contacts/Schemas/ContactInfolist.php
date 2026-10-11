<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Icm\ActiveConfig;
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
                                    ->badge(),
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
}
