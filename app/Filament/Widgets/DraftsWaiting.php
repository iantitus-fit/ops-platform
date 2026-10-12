<?php

namespace App\Filament\Widgets;

use App\Enums\MessageStatus;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Message;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The replies the front desk wrote and the send rule kept for the owner, longest wait first (D-32).
 */
class DraftsWaiting extends TableWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Drafts waiting')
            ->query(fn (): Builder => Message::query()
                ->where('status', MessageStatus::Draft)
                ->with(['conversation.contact', 'ownerAlerts']))
            ->columns([
                TextColumn::make('conversation.contact.name')
                    ->label('Customer')
                    ->placeholder('(no name)'),
                TextColumn::make('held_reason')
                    ->label('Held because')
                    ->state(fn (Message $record): string => $record->gate['reason']['plain'] ?? 'Held for your review'),
                TextColumn::make('created_at')
                    ->label('Waiting')
                    ->since(),
                TextColumn::make('alert_flag')
                    ->label('Alert')
                    ->state(fn (Message $record): ?string => ($failure = $record->alertFailure()) === null
                        ? null
                        : 'Not alerted: '.($failure !== '' ? $failure : 'the text could not be sent'))
                    ->badge()
                    ->color('warning'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('created_at')
                ->orderBy('id'))
            ->recordUrl(fn (Message $record): string => ConversationResource::getUrl('view', ['record' => $record->conversation_id]))
            ->emptyStateHeading('No drafts waiting');
    }
}
