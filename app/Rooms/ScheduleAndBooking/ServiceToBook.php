<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Enums\ReplyLabel;
use App\Icm\ActiveConfig;
use App\Models\Conversation;

/**
 * Decides which service a booking link may be texted for, or that none may (D-01, D-02). The one place that holds the
 * conditions, so the front desk's own send (RunFrontDesk) and the owner's approval of a held draft (SendOwnerReply, D-45)
 * ask the same question and cannot drift apart.
 *
 * A link rides only on a reply labeled scheduling, while the room is live with a booking file, for a service that exists
 * and is book_online, and while the contact has no visit booked ahead (a change to a booked visit goes through the owner,
 * D-34). The service is the one the reply names (D-02); with none, the one service the conversation's intake answers
 * point to, and never a guess between two.
 */
class ServiceToBook
{
    public function __construct(private readonly ActiveConfig $config) {}

    /**
     * The service slug to book, or null when no link may ride.
     */
    public function handle(Conversation $conversation, ?ReplyLabel $label, ?string $serviceSlug): ?string
    {
        if ($label !== ReplyLabel::Scheduling) {
            return null;
        }

        if (! $this->config->hasRoom('schedule_and_booking') || BookingSettings::fromConfig($this->config) === null) {
            return null;
        }

        $slug = $this->resolve($conversation, $serviceSlug);

        if ($slug === null || ($this->config->service($slug)['book_online'] ?? false) !== true) {
            return null;
        }

        if ($conversation->contact->appointments()->booked()->ahead()->exists()) {
            return null;
        }

        return $slug;
    }

    /**
     * The reply's own service when it names one, else the conversation's intake service when exactly one service has
     * intake answers (D-02).
     */
    private function resolve(Conversation $conversation, ?string $serviceSlug): ?string
    {
        if ($serviceSlug !== null && $serviceSlug !== '') {
            return $serviceSlug;
        }

        $slugs = $conversation->intakeAnswers()->distinct()->pluck('service_slug');

        return $slugs->count() === 1 ? (string) $slugs->first() : null;
    }
}
