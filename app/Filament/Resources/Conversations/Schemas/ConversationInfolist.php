<?php

namespace App\Filament\Resources\Conversations\Schemas;

use App\Livewire\ConversationThread;
use App\Models\Conversation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Livewire::make(ConversationThread::class, fn (Conversation $record): array => ['conversation' => $record])
                    ->columnSpanFull(),
                Section::make('Customer')
                    ->schema([
                        TextEntry::make('contact.name')
                            ->label('Name')
                            ->placeholder('(no name)'),
                        TextEntry::make('contact.phone_e164')
                            ->label('Phone')
                            ->placeholder('(no phone)'),
                        TextEntry::make('contact.email')
                            ->label('Email')
                            ->placeholder('(no email)'),
                        TextEntry::make('status')
                            ->badge(),
                    ])
                    ->columns(4)
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }
}
