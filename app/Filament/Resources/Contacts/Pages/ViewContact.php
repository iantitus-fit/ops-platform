<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Actions\SetAutomaticTexts;
use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    /**
     * The automatic-texts switch (D-20, U-26), shown only for a customer who has never texted the business: a customer
     * who has texted may get automatic texts already, so there is nothing to switch.
     *
     * It is one action whose label and dialog follow the switch, so its button keeps one key and keyboard focus stays on
     * it after it flips (G-07-2.2). Both directions ask first. The dialog is decided when the owner presses and the
     * direction rides in the form, so a stale page (a second tab) does what the dialog said or nothing (T-08-63).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('automaticTexts')
                ->label(fn (): string => $this->automaticTextsAreOn() ? 'Turn off automatic texts' : 'Turn on automatic texts')
                ->color('gray')
                ->visible(fn (): bool => ! $this->contact()->hasTextedBusiness())
                ->requiresConfirmation()
                ->modalHeading(fn (): string => ($this->automaticTextsAreOn() ? 'Turn off' : 'Turn on').' automatic texts for '.$this->contactName().'?')
                ->modalDescription(fn (): string => $this->automaticTextsAreOn()
                    ? 'The business will stop texting confirmations and reminders on its own. You can still reply yourself.'
                    : 'Only turn this on if the customer agreed to get texts from the business, on a call for example. The business will then text booking confirmations and reminders on its own.')
                ->modalSubmitActionLabel(fn (): string => $this->automaticTextsAreOn() ? 'Turn off' : 'Turn on')
                ->schema([
                    Hidden::make('direction')
                        ->required()
                        ->in(['on', 'off']),
                ])
                ->fillForm(fn (): array => ['direction' => $this->automaticTextsAreOn() ? 'off' : 'on'])
                ->action(function (array $data): void {
                    $contact = $this->contact();
                    $turnOn = $data['direction'] === 'on';

                    try {
                        $turnOn
                            ? app(SetAutomaticTexts::class)->on($contact, auth()->user())
                            : app(SetAutomaticTexts::class)->off($contact, auth()->user());
                    } catch (DomainException $refusal) {
                        // Filament renders a title as HTML, and the refusal is plain words.
                        Notification::make()->title(e($refusal->getMessage()))->danger()->send();

                        return;
                    }

                    Notification::make()->title($turnOn ? 'Automatic texts are on' : 'Automatic texts are off')->success()->send();
                }),
        ];
    }

    private function automaticTextsAreOn(): bool
    {
        return $this->contact()->automatic_texts_on_at !== null;
    }

    /**
     * What the dialog calls the customer: the name, else the phone or email, else "this customer".
     */
    private function contactName(): string
    {
        $contact = $this->contact();

        return $contact->name ?: $contact->phone_e164 ?: $contact->email ?: 'this customer';
    }

    private function contact(): Contact
    {
        /** @var Contact */
        return $this->getRecord();
    }
}
