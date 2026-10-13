<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;
use Filament\Schemas\Components\Component;

/**
 * Filament's login page with the remember-me box already ticked, so the owner stays signed in on their phone (D-31).
 * Filament's login response sends them on to the page they first asked for, such as an alert's conversation link.
 */
class RememberedLogin extends Login
{
    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()->default(true);
    }
}
