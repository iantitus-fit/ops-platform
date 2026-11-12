<?php

namespace App\Enums;

/**
 * Who opened or closed a project: a new lead (the automatic open, plan 07-08), a signed-in user (D-08) or a customer
 * booking on the page (Phase 8 D-22). Anything that prints who opened a project matches on all three cases with no
 * default, so a fourth case cannot be printed as the owner's by a leftover binary choice (research Pitfall 4).
 */
enum ProjectActor: string
{
    case Lead = 'lead';
    case User = 'user';
    case Customer = 'customer';
}
