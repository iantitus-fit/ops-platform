<?php

namespace App\Filament\Widgets;

use App\Enums\MessageAuthor;
use App\Enums\MessageStatus;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use App\Models\Message;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The conversations the assistant has stepped back from where the customer spoke last, so they are waiting on the
 * owner, longest wait first (D-32). Who spoke last counts only texts on the customer's phone
 * (MessageStatus::onTheCustomersPhone()), so an owner reply still queued or that failed to send leaves them waiting.
 */
class PausedConversationsWaiting extends TableWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $latestAuthor = Message::query()
            ->select('author')
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->whereIn('status', MessageStatus::onTheCustomersPhone())
            ->orderByRaw('coalesce(sent_at, created_at) desc')
            ->orderByDesc('id')
            ->limit(1);

        return $table
            ->heading('Customers waiting while you handle it')
            ->query(fn (): Builder => Conversation::query()
                ->whereNotNull('paused_at')
                ->where($latestAuthor, '=', MessageAuthor::Customer->value)
                ->with('contact'))
            ->columns([
                TextColumn::make('contact.name')
                    ->label('Customer')
                    ->placeholder('(no name)'),
                TextColumn::make('contact.phone_e164')
                    ->label('Phone'),
                TextColumn::make('last_message_at')
                    ->label('Waiting')
                    ->since(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('last_message_at is null')
                ->orderBy('last_message_at')
                ->orderBy('id'))
            ->recordUrl(fn (Conversation $record): string => ConversationResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No one is waiting on you');
    }
}
