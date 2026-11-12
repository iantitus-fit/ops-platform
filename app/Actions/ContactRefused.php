<?php

namespace App\Actions;

use DomainException;

/**
 * A contact the platform will not add by hand, in plain words the owner reads. `field` names the form field the sentence
 * is about (the field's name) so the screen can show it beside that field (D-19).
 */
class ContactRefused extends DomainException
{
    public function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }
}
