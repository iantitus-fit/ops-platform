<?php

namespace App\Enums;

enum SyncOutcome: string
{
    case Activated = 'activated';
    case Unchanged = 'unchanged';
    case Rejected = 'rejected';
    case Busy = 'busy';
}
