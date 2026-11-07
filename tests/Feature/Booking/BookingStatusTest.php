<?php

use App\Actions\BlockTime;
use App\Actions\BookAppointment;
use App\Actions\CancelAppointment;
use App\Actions\MarkAppointmentDone;
use App\Actions\MintBookingLink;
use App\Enums\ContactStatus;
use App\Filament\Widgets\NewLeads;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Records\RecordsWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * No booking path changes a contact's status (D-41): a page booking, a hand booking, a cancel, a done and a block each
 * leave a Lead a Lead and a Lost contact Lost. What makes a contact a Customer is not a booking (backlog 999.1).
 */

/**
 * A moment on the example business's wall clock (America/Chicago).
 */
function bookingStatusChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * The live example folder, the clock at Monday 2026-11-02 12:00 in the shop's zone, the signed-in owner, and Dana
 * Whitfield with the given status: an open conversation holding a customer text nobody has read, so no booking text goes
 * out and the records files can only change if the status does, and Job 1 open on that conversation.
 *
 * @return array{0: Contact, 1: Conversation, 2: Project, 3: User}
 */
function bookingStatusSetUp(ContactStatus $status): array
{
    activateExampleFolder();
    test()->travelTo(bookingStatusChicago('2026-11-02 12:00'));

    $owner = User::factory()->withPhone('+12025550199')->create(['name' => 'Ian Owner']);
    test()->actingAs($owner);

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123', 'status' => $status]);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'My AC is blowing warm air']);
    $job = Project::factory()->create(['contact_id' => $contact->id, 'conversation_id' => $conversation->id]);

    return [$contact, $conversation, $job, $owner];
}

/**
 * Book the contact's AC repair by hand for the given moment.
 */
function bookingStatusBook(Contact $contact, Project $job, User $owner, string $dateTime): Appointment
{
    return app(BookAppointment::class)->byHand($contact, $owner, 'ac-repair', bookingStatusChicago($dateTime), $job->id, null);
}

/**
 * The open-leads file as the engine writes it from the database right now.
 */
function bookingStatusOpenLeads(): string
{
    app(RecordsWriter::class)->writeSummaries();

    return File::get(icmPath('records/open-leads.md'));
}

it('leaves a contact\'s status alone on every booking path (D-41)', function (string $path, ContactStatus $status) {
    [$contact, $conversation, $job, $owner] = bookingStatusSetUp($status);

    $act = match ($path) {
        'a page booking' => (function () use ($conversation): Closure {
            [$link] = app(MintBookingLink::class)->handle($conversation, 'ac-repair', CarbonImmutable::now());
            $carrier = Message::factory()->for($conversation)->outbound()->create(['body' => 'Here is the link to book.', 'meta' => ['booking_link_id' => $link->id]]);
            app(MintBookingLink::class)->attachMessage($link, $carrier);

            return fn () => app(BookAppointment::class)->fromLink($link->fresh(), bookingStatusChicago('2026-11-05 10:00'), CarbonImmutable::now());
        })(),
        'a hand booking' => fn () => bookingStatusBook($contact, $job, $owner, '2026-11-05 10:00'),
        'a cancel' => (function () use ($contact, $job, $owner): Closure {
            $visit = bookingStatusBook($contact, $job, $owner, '2026-11-05 10:00');

            return fn () => app(CancelAppointment::class)->handle($visit, $owner);
        })(),
        'a done' => (function () use ($contact, $job, $owner): Closure {
            $visit = bookingStatusBook($contact, $job, $owner, '2026-11-03 10:00');
            test()->travelTo(bookingStatusChicago('2026-11-03 10:30'));

            return fn () => app(MarkAppointmentDone::class)->handle($visit, $owner, alsoCloseProject: true);
        })(),
        'a block' => fn () => app(BlockTime::class)->handle($owner, bookingStatusChicago('2026-11-04 13:00'), bookingStatusChicago('2026-11-04 15:00'), null),
    };

    $openLeadsBefore = bookingStatusOpenLeads();

    if ($status === ContactStatus::Lead) {
        expect($openLeadsBefore)->toContain('Dana Whitfield');
    }

    $act();

    expect($contact->fresh()->status)->toBe($status)
        ->and(bookingStatusOpenLeads())->toBe($openLeadsBefore);

    if ($status === ContactStatus::Lead) {
        Livewire::test(NewLeads::class)->assertCanSeeTableRecords([$contact]);
    } else {
        Livewire::test(NewLeads::class)->assertCanNotSeeTableRecords([$contact]);
    }
})->with([
    'a page booking',
    'a hand booking',
    'a cancel',
    'a done',
    'a block',
])->with([
    'a lead' => ContactStatus::Lead,
    'a lost contact' => ContactStatus::Lost,
]);
