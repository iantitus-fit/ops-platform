<?php

namespace App\Filament\Pages\Auth;

use App\Support\PhoneNumber;
use Closure;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * Filament's profile page with one more field: the owner's mobile phone, where owner alerts are texted (D-18).
 */
class EditProfileWithPhone extends EditProfile
{
    /**
     * Filament's own name, email and password fields, then the phone.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPhoneFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    /**
     * Anything {@see PhoneNumber::toE164()} cannot read is refused; what it can read is stored as E.164 and an
     * empty box stores null.
     */
    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Mobile phone for alerts')
            ->helperText('Held drafts and urgent messages are texted here.')
            ->inputMode('tel')
            ->autocomplete('tel')
            ->nullable()
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (PhoneNumber::toE164(is_string($value) ? $value : null) === null) {
                    $fail('Use a real-format number like +12025550123.');
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => PhoneNumber::toE164($state));
    }
}
