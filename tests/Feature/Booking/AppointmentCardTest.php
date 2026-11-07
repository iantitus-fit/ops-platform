<?php

use App\Actions\BookAppointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Records\CustomerCard;
use Carbon\CarbonImmutable;

/**
 * Appointments on the customer card (BOOK-03, D-38): a Next appointment line, each job's visits with their state, the
 * owner's visit note only as a quote, and the room's entries on the timeline.
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
});

/**
 * A moment on the example business's wall clock (America/Chicago).
 */
function appointmentCardChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Monday 2026-11-02 12:00 in the shop's zone, and Dana Whitfield, who texted, with
 * Job 1 closed and Job 2 open.
 *
 * @return array{0: Contact, 1: Project}
 */
function appointmentCardDana(): array
{
    activateExampleFolder();
    test()->travelTo(appointmentCardChicago('2026-11-02 12:00'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = $contact->conversations()->create();
    Message::factory()->create(['conversation_id' => $conversation->id, 'handled_at' => now()]);

    Project::factory()->for($contact)->closed()->create();
    $second = Project::factory()->for($contact)->create();

    return [$contact, $second];
}

/**
 * Book one visit by hand for the contact on the job.
 */
function appointmentCardBook(Contact $contact, Project $job, string $dateTime, ?string $note = null): void
{
    app(BookAppointment::class)->byHand($contact, test()->owner, 'ac-repair', appointmentCardChicago($dateTime), $job->id, $note);
}

/**
 * The contact's card as the owner and the front desk read it.
 */
function appointmentCardRender(Contact $contact): string
{
    return app(CustomerCard::class)->render($contact->fresh());
}

it('shows the next appointment and the job\'s visit', function () {
    [$contact, $job] = appointmentCardDana();
    appointmentCardBook($contact, $job, '2026-11-03 10:00');

    $card = appointmentCardRender($contact);
    $lines = explode("\n", $card);
    $last = array_search(true, array_map(fn (string $line): bool => str_starts_with($line, '- Last activity: '), $lines), true);

    expect($lines[$last + 1])->toBe('- Next appointment: Tuesday, November 3, 2026 at 10:00 AM (Central Time), AC repair, Job 2')
        ->and($card)->toContain("- Job 2: open, opened 2026-11-02 12:00 by the owner\n  - Booked: Tuesday, November 3, 2026 at 10:00 AM, AC repair")
        ->and($card)->toContain('· Visit booked')
        ->and($card)->toContain("System note:\n\n> Booked by Ian Owner: AC repair, Tuesday, November 3 at 10:00 AM.");
});
