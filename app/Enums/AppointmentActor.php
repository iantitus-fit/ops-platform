<?php

namespace App\Enums;

/**
 * Who booked or cancelled an appointment: the customer through their link, or a signed-in user (D-15). The customer
 * case is stored from the start so self-service cancel can be added later without a new column (D-32).
 */
enum AppointmentActor: string
{
    case Customer = 'customer';
    case User = 'user';
}
