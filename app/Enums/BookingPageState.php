<?php

namespace App\Enums;

/**
 * What a booking link's page shows (D-13, U-13, U-14). The order a link is judged in lives in ResolveBookingLink. The
 * check-your-time step and the rate-limited notice belong to the page alone and live in its component (plan 08-08), not
 * here.
 */
enum BookingPageState: string
{
    case Picker = 'picker';
    case Booked = 'booked';
    case Cancelled = 'cancelled';
    case Done = 'done';
    case NoTimes = 'no_times';
    case ServiceUnavailable = 'service_unavailable';
    case Expired = 'expired';
    case Replaced = 'replaced';
    case DoesNotWork = 'does_not_work';
}
