<?php

namespace App\Enums;

enum MessageChannel: string
{
    case Sms = 'sms';
    case Web = 'web';
    case Voice = 'voice';
    case Email = 'email';
}
