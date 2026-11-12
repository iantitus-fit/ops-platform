<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Enums\BookingPageState;
use App\Models\Appointment;
use App\Models\BookingLink;
use Carbon\CarbonImmutable;

/**
 * What a token resolved to: the page's state and what that state needs to draw. The link and appointment are set when
 * the token named a row; the service and the open starts only when the page offers the picker or "no times".
 */
final readonly class BookingLinkLookup
{
    /**
     * @param  array<string, mixed>|null  $service  the folder's service, as ActiveConfig reads it
     * @param  array<string, list<CarbonImmutable>>  $openStarts  the offered starts by business-zone date, empty unless the picker is shown
     */
    public function __construct(
        public BookingPageState $state,
        public ?BookingLink $link = null,
        public ?Appointment $appointment = null,
        public ?array $service = null,
        public array $openStarts = [],
    ) {}
}
