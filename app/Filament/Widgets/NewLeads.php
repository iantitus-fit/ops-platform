<?php

namespace App\Filament\Widgets;

use App\Enums\ContactStatus;
use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The contacts who became leads in the last 7 days, newest first (D-32).
 */
class NewLeads extends TableWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('New leads')
            ->query(fn (): Builder => Contact::query()
                ->where('status', ContactStatus::Lead)
                ->where('created_at', '>=', now()->subDays(7)))
            ->columns([
                TextColumn::make('name')
                    ->placeholder('(no name)'),
                TextColumn::make('phone_e164')
                    ->label('Phone'),
                TextColumn::make('lead_source_key')
                    ->label('Source')
                    ->badge(),
                TextColumn::make('acquisition_cost_cents')
                    ->label('Cost per lead')
                    ->money('USD', divideBy: 100)
                    ->placeholder('(none)'),
                TextColumn::make('created_at')
                    ->label('Arrived')
                    ->since(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id'))
            ->recordUrl(fn (Contact $record): string => ContactResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No new leads this week');
    }
}
