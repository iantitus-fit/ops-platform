<?php

namespace App\Enums;

/**
 * What a row in the appointments table is: a customer's appointment, or a block of time the owner holds for
 * themselves (Phase 8 A3). Both hold time while booked, so the one table keeps overlap checks in one place.
 */
enum AppointmentKind: string
{
    case Appointment = 'appointment';
    case Block = 'block';
}
