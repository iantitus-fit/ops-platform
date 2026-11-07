<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Resources\Contacts\ContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    /**
     * The owner adds a customer who called by hand (D-19).
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New contact'),
        ];
    }
}
