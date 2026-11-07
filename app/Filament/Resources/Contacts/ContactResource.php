<?php

namespace App\Filament\Resources\Contacts;

use App\Filament\Resources\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Filament\Resources\Contacts\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\Contacts\RelationManagers\ProjectsRelationManager;
use App\Filament\Resources\Contacts\Schemas\ContactInfolist;
use App\Filament\Resources\Contacts\Tables\ContactsTable;
use App\Models\Contact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * The owner may add a customer who called rather than texted (D-19, Ian 2026-11-02). This reverses Phase 1's "never
     * by hand"; the Create page hands the form to AddContactByHand, which matches like an inbound.
     */
    public static function canCreate(): bool
    {
        return true;
    }

    /**
     * A lead who gave no name is titled by their phone number or email.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->name ?: $record?->phone_e164 ?: $record?->email ?: '(no name)';
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContactInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactsTable::configure($table);
    }

    /**
     * The contact's projects, listed under the details with the actions that open, close, reopen and rename them, then
     * the contact's appointments with the action that books one by hand.
     */
    public static function getRelations(): array
    {
        return [
            ProjectsRelationManager::class,
            AppointmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'view' => ViewContact::route('/{record}'),
        ];
    }
}
