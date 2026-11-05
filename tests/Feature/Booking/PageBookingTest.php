<?php

use App\Actions\BookAppointment;
use App\Actions\MintBookingLink;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\AppointmentActor;
use App\Enums\AppointmentState;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A moment on the example business's wall clock (America/Chicago), as the customer taps it.
 */
function pageBookingChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Tuesday 2026-11-03 11:00 in the shop's zone, an owner with a phone, and Dana
 * Whitfield, who texted: her open conversation and Job 1 open and tied to it.
 *
 * @return array{0: Contact, 1: Conversation, 2: Project}
 */
function pageBookingSetUp(): array
{
    activateExampleFolder();
    test()->travelTo(pageBookingChicago('2026-11-03 11:00'));

    User::factory()->withPhone('+12025550199')->create();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'My AC is blowing warm air']);
    $job = Project::factory()->create(['contact_id' => $contact->id, 'conversation_id' => $conversation->id]);

    return [$contact, $conversation, $job];
}

/**
 * Mint a link for the conversation and give it its carrying text: an outbound agent message whose meta names the link,
 * attached to the link the way the send step does (plan 08-09).
 *
 * @return array{0: BookingLink, 1: string, 2: Message}
 */
function pageBookingMint(Conversation $conversation, string $service = 'ac-repair'): array
{
    [$link, $token] = app(MintBookingLink::class)->handle($conversation, $service, CarbonImmutable::now());

    $message = Message::factory()->for($conversation)->outbound()->create([
        'body' => 'Here is the link to book.',
        'meta' => ['booking_link_id' => $link->id],
    ]);

    app(MintBookingLink::class)->attachMessage($link, $message);

    return [$link->fresh(), $token, $message];
}

it('books the tapped time for the customer through the link', function () {
    [$contact, $conversation, $job] = pageBookingSetUp();
    [$link] = pageBookingMint($conversation);
    $statusBefore = $contact->status;

    $appointment = app(BookAppointment::class)->fromLink($link, pageBookingChicago('2026-11-05 10:00'), CarbonImmutable::now());

    expect($appointment->project_id)->toBe($job->id)
        ->and($appointment->contact_id)->toBe($contact->id)
        ->and($appointment->booked_by)->toBe(AppointmentActor::Customer)
        ->and($appointment->booked_by_user_id)->toBeNull()
        ->and($appointment->booking_link_id)->toBe($link->id)
        ->and($appointment->service_slug)->toBe('ac-repair')
        ->and($appointment->service_name)->toBe('AC repair')
        ->and($appointment->state)->toBe(AppointmentState::Booked)
        ->and($appointment->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-05 16:00')
        ->and($appointment->ends_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-05 17:30');

    $entry = $contact->activities()->where('type', ActivityType::RoomEntry->value)->sole();

    expect($entry->entry)->toBe('visit_booked')
        ->and($entry->summary)->toBe('Booked by the customer on the booking page: AC repair, Thursday, November 5 at 10:00 AM.')
        ->and($entry->subject_id)->toBe($appointment->id)
        ->and($contact->fresh()->status)->toBe($statusBefore);
});

it('alerts the owner once on the text that carried the link (D-33)', function () {
    [, $conversation] = pageBookingSetUp();
    [$link, , $message] = pageBookingMint($conversation);

    app(BookAppointment::class)->fromLink($link, pageBookingChicago('2026-11-05 10:00'), CarbonImmutable::now());

    $alerts = OwnerAlert::query()->get();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->reason)->toBe(AlertReason::AppointmentBooked)
        ->and($alerts[0]->message_id)->toBe($message->id)
        ->and($alerts[0]->due_at->toIso8601String())->toBe(CarbonImmutable::now()->toIso8601String())
        ->and($alerts[0]->body)->toContain('AC repair, Thursday, November 5 at 10:00 AM');
});

it('stores only the hash of the token (D-12)', function () {
    [, $conversation] = pageBookingSetUp();
    [$link, $token] = pageBookingMint($conversation);

    expect(strlen($token))->toBe(64)
        ->and(ctype_xdigit($token))->toBeTrue()
        ->and($link->token_hash)->toBe(hash('sha256', $token));

    $row = (array) DB::table('booking_links')->where('id', $link->id)->first();

    expect(collect($row)->contains(fn ($value): bool => $value === $token))->toBeFalse()
        ->and(Appointment::query()->count())->toBe(0);
});
