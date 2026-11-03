<?php

namespace App\Enums;

/**
 * Where an appointment stands (D-14). Only booked holds time. A block is booked while it holds time and cancelled once
 * the owner removes it; a block is never done.
 */
enum AppointmentState: string
{
    case Booked = 'booked';
    case Cancelled = 'cancelled';
    case Done = 'done';
}
