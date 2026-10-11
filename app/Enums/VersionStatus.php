<?php

namespace App\Enums;

enum VersionStatus: string
{
    case Active = 'active';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}
