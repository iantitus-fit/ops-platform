<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Enums\ContactStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('(no name)'),
                TextColumn::make('phone_e164')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lead_source_key')
                    ->label('Source')
                    ->badge(),
                TextColumn::make('acquisition_cost_cents')
                    ->label('Cost per lead')
                    ->money('USD', divideBy: 100),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('last_activity_at')
                    ->label('Last activity')
                    ->since(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('last_activity_at is null')
                ->orderByDesc('last_activity_at')
                ->orderByDesc('id'))
            ->filters([
                SelectFilter::make('status')
                    ->options(ContactStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
