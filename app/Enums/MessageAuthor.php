<?php

namespace App\Enums;

enum MessageAuthor: string
{
    case Customer = 'customer';
    case Agent = 'agent';
    case Owner = 'owner';
}
