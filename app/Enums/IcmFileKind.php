<?php

namespace App\Enums;

enum IcmFileKind: string
{
    case Typed = 'typed';
    case Router = 'router';
    case LaterSprint = 'later_sprint';
    case Unrecognized = 'unrecognized';
}
