<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The conversations whose last message arrived today, by the business's clock, newest first (D-32).
 */
class TodaysConversations extends TableWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $timezone = app(ActiveConfig::class)->displayTimezone();

        return $table
            ->heading("Today's conversations")
            ->query(function () use ($timezone): Builder {
                $today = Carbon::now($timezone);

                // Times are stored in the app's zone, so the business day's edges are converted to it.
                return Conversation::query()
                    ->whereBetween('last_message_at', [
                        $today->copy()->startOfDay()->setTimezone(config('app.timezone')),
                        $today->copy()->endOfDay()->setTimezone(config('app.timezone')),
                    ])
                    ->with('contact');
            })
            ->columns([
                TextColumn::make('contact.name')
                    ->label('Customer')
                    ->placeholder('(no name)'),
                TextColumn::make('contact.phone_e164')
                    ->label('Phone'),
                TextColumn::make('last_message_at')
                    ->label('Last message')
                    ->since(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByDesc('last_message_at')
                ->orderByDesc('id'))
            ->recordUrl(fn (Conversation $record): string => ConversationResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No conversations today');
    }
}
