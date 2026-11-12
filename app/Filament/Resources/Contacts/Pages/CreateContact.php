<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Actions\AddContactByHand;
use App\Actions\ContactRefused;
use App\Filament\Resources\Contacts\ContactResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Add a contact by hand (D-19). Filament's own Create page draws the form and the button; the typed values go to
 * AddContactByHand instead of straight to the table, so the same phone-then-email rule an inbound text uses decides
 * whether this is a new person, and a refusal comes back as an error beside the field it is about.
 */
class CreateContact extends CreateRecord
{
    protected static string $resource = ContactResource::class;

    protected static ?string $title = 'New contact';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(100),
            TextInput::make('phone')
                ->type('tel')
                ->autocomplete(false)
                ->helperText('A US number, such as 312 555 0147.'),
            TextInput::make('email')
                ->trim()
                ->email()
                ->autocomplete(false),
        ]);
    }

    /**
     * The typed values go to AddContactByHand, and a refusal that names a field is shown beside that field.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(AddContactByHand::class)->handle(
                (string) $data['name'],
                $data['phone'] ?? null,
                $data['email'] ?? null,
            );
        } catch (ContactRefused $refusal) {
            throw ValidationException::withMessages(['data.'.$refusal->field => $refusal->getMessage()]);
        }
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Add contact');
    }
}
