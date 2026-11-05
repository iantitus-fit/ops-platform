<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Enums\AppointmentState;
use App\Enums\BookingPageState;
use App\Icm\ActiveConfig;
use App\Models\BookingLink;
use Carbon\CarbonImmutable;

/**
 * Turns a booking link into the state its page shows (D-13, U-13, U-14). It reads and never writes, and it is not
 * final so a test can stand in for its state check.
 *
 * Two methods share one order. closedState() judges the link row alone and never computes open times, so the booking
 * action can ask it under the lock without a plain token and without the cost of the picker's list. handle() finds the
 * row by the hash of the token and adds the picker or "no times" when the link may book. The order, first match wins:
 * a revoked link does not work; a link with an appointment shows its booked, cancelled or done view until its expiry,
 * or until the visit ends if that is later, and then reads expired; a superseded link is replaced; a link past its
 * expiry is expired; a service that cannot be booked online gives service unavailable; else the link may book.
 */
class ResolveBookingLink
{
    public function __construct(private readonly ActiveConfig $config, private readonly Availability $availability) {}

    /**
     * Find the link by the hash of the token in the address and judge it. An unknown token and a revoked link both read
     * "does not work", so the page cannot be used to tell which tokens exist (T-08-26).
     */
    public function handle(string $token, CarbonImmutable $now): BookingLinkLookup
    {
        $link = BookingLink::query()->with('appointment')->where('token_hash', hash('sha256', $token))->first();

        if ($link === null) {
            return new BookingLinkLookup(BookingPageState::DoesNotWork);
        }

        $state = $this->closedState($link, $now);

        if ($state !== null) {
            return new BookingLinkLookup($state, $link, $link->appointment, $this->config->service($link->service_slug));
        }

        $service = $this->config->service($link->service_slug);
        $openStarts = $this->availability->openStarts($service, $now);

        return new BookingLinkLookup(
            $openStarts === [] ? BookingPageState::NoTimes : BookingPageState::Picker,
            $link,
            null,
            $service,
            $openStarts,
        );
    }

    /**
     * The link's own state, judged without computing open times: null when the link may book.
     */
    public function closedState(BookingLink $link, CarbonImmutable $now): ?BookingPageState
    {
        if ($link->revoked_at !== null) {
            return BookingPageState::DoesNotWork;
        }

        $appointment = $link->appointment;

        if ($appointment !== null) {
            if ($now >= $link->expires_at && $now >= $appointment->ends_at) {
                return BookingPageState::Expired;
            }

            return match ($appointment->state) {
                AppointmentState::Booked => BookingPageState::Booked,
                AppointmentState::Cancelled => BookingPageState::Cancelled,
                AppointmentState::Done => BookingPageState::Done,
            };
        }

        if ($link->superseded_at !== null) {
            return BookingPageState::Replaced;
        }

        if ($now >= $link->expires_at) {
            return BookingPageState::Expired;
        }

        $service = $this->config->hasRoom('schedule_and_booking') && BookingSettings::fromConfig($this->config) !== null
            ? $this->config->service($link->service_slug)
            : null;

        if ($service === null || ($service['book_online'] ?? false) !== true) {
            return BookingPageState::ServiceUnavailable;
        }

        return null;
    }
}
