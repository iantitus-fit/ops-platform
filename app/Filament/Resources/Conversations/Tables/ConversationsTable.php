<?php

namespace App\Filament\Resources\Conversations\Tables;

use App\Enums\ConversationStatus;
use Filament\Actions\ViewAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact.name')
                    ->label('Customer')
                    ->placeholder('(no name)'),
                TextColumn::make('contact.phone_e164')
                    ->label('Phone'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ConversationStatus $state): array => match ($state) {
                        ConversationStatus::Open => Color::Green,
                        ConversationStatus::Closed => Color::Gray,
                    }),
                TextColumn::make('last_message_at')
                    ->label('Last message')
                    ->since()
                    ->sortable(),
                TextColumn::make('messages_count')
                    ->label('Messages')
                    ->counts('messages'),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ConversationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
