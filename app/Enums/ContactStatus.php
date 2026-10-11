<?php

namespace App\Enums;

enum ContactStatus: string
{
    case Lead = 'lead';
    case Customer = 'customer';
    case Lost = 'lost';
}
