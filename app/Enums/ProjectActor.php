<?php

namespace App\Enums;

/**
 * Who opened or closed a project: a new lead (the automatic open, plan 07-08) or a signed-in user (D-08).
 */
enum ProjectActor: string
{
    case Lead = 'lead';
    case User = 'user';
}
