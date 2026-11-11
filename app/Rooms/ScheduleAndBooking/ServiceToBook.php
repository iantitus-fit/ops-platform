<?php

namespace App\Rooms\ScheduleAndBooking;

use App\Enums\ReplyLabel;
use App\Icm\ActiveConfig;
use App\Models\Contact;
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
    public const string BOOKING_OFF = 'online booking is turned off';

    public const string SERVICE_UNCLEAR = 'the conversation no longer points to one service';

    public const string SERVICE_NOT_BOOKABLE = 'the service can no longer be booked online';

    public const string VISIT_BOOKED = 'a visit is already booked';

    public function __construct(private readonly ActiveConfig $config) {}

    /**
     * The service slug to book, or null when no link may ride: the reply is not labeled scheduling, or whyNoLink() gives
     * a reason.
     */
    public function handle(Conversation $conversation, ?ReplyLabel $label, ?string $serviceSlug): ?string
    {
        if ($label !== ReplyLabel::Scheduling) {
            return null;
        }

        [$reason, $slug] = $this->check($conversation, $serviceSlug);

        return $reason === null ? $slug : null;
    }

    /**
     * Why a scheduling reply cannot carry a link now, in the plain words the owner is told (D-45), or null when it can.
     * The conditions are checked in this order and the first that fails is the reason: the room live with a booking file,
     * the service resolving to one, that service existing and being book_online, and no visit booked ahead.
     */
    public function whyNoLink(Conversation $conversation, ?string $serviceSlug): ?string
    {
        return $this->check($conversation, $serviceSlug)[0];
    }

    /**
     * Whether the contact has a visit booked still ahead. The link send, the owner's approval and the front desk's prompt
     * all ask this one method (D-39 amended), so what the model is told about links cannot drift from when a link rides.
     */
    public function visitBookedAhead(Contact $contact): bool
    {
        return $contact->appointments()->booked()->ahead()->exists();
    }

    /**
     * @return array{0: ?string, 1: ?string} The reason no link may ride, and the service slug when none stops it.
     */
    private function check(Conversation $conversation, ?string $serviceSlug): array
    {
        if (! $this->config->hasRoom('schedule_and_booking') || BookingSettings::fromConfig($this->config) === null) {
            return [self::BOOKING_OFF, null];
        }

        $slug = $this->resolve($conversation, $serviceSlug);

        if ($slug === null) {
            return [self::SERVICE_UNCLEAR, null];
        }

        if (($this->config->service($slug)['book_online'] ?? false) !== true) {
            return [self::SERVICE_NOT_BOOKABLE, null];
        }

        if ($this->visitBookedAhead($conversation->contact)) {
            return [self::VISIT_BOOKED, null];
        }

        return [null, $slug];
    }

    /**
     * The reply's own service when it names one, else the conversation's intake service when exactly one service has
     * intake answers (D-02).
     *
     * reorder() drops the relation's orderBy('id'): Postgres refuses SELECT DISTINCT ordered by a column it does not
     * select (42P10), and the order of distinct slugs does not matter here.
     */
    private function resolve(Conversation $conversation, ?string $serviceSlug): ?string
    {
        if ($serviceSlug !== null && $serviceSlug !== '') {
            return $serviceSlug;
        }

        $slugs = $conversation->intakeAnswers()->reorder()->distinct()->pluck('service_slug');

        return $slugs->count() === 1 ? (string) $slugs->first() : null;
    }
}
