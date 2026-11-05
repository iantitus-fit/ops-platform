<?php

use App\Actions\BookAppointment;
use App\Actions\MintBookingLink;
use App\Enums\AlertReason;
use App\Enums\AppointmentState;
use App\Enums\BookingPageState;
use App\Icm\FolderSync;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\Project;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\BookingLinkLookup;
use App\Rooms\ScheduleAndBooking\BookingRefused;
use App\Rooms\ScheduleAndBooking\ResolveBookingLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;

/**
 * A moment on the example business's wall clock (America/Chicago).
 */
function linkTokenChicago(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Chicago');
}

/**
 * Move the clock and hand out fresh scoped objects, so the folder and the clock are read as they are now.
 */
function linkTokenTravel(string $dateTime): void
{
    test()->travelTo(linkTokenChicago($dateTime));
    app()->forgetScopedInstances();
}

/**
 * The live example folder (changed first by $changeFolder when given), the clock at Tuesday 2026-11-03 11:00 in the
 * shop's zone, an owner with a phone, and Dana Whitfield with her open conversation and Job 1 tied to it.
 *
 * @return array{0: Contact, 1: Conversation, 2: Project}
 */
function linkTokenSetUp(?Closure $changeFolder = null): array
{
    if ($changeFolder !== null) {
        $changeFolder();
    }

    activateExampleFolder();
    test()->travelTo(linkTokenChicago('2026-11-03 11:00'));

    User::factory()->withPhone('+12025550199')->create();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    Message::factory()->for($conversation)->create(['body' => 'My AC is blowing warm air']);
    $job = Project::factory()->create(['contact_id' => $contact->id, 'conversation_id' => $conversation->id]);

    return [$contact, $conversation, $job];
}

/**
 * Change the live folder and make it live again, then hand out fresh scoped objects.
 */
function linkTokenResync(Closure $changeFolder): void
{
    $changeFolder();

    app(FolderSync::class)->run();
    app()->forgetScopedInstances();
}

/**
 * Mint a link for the conversation; with $carried, an outbound text that carries it is attached the way the send step
 * does (plan 08-09).
 *
 * @return array{0: BookingLink, 1: string, 2: Message|null}
 */
function linkTokenMint(Conversation $conversation, bool $carried = true): array
{
    [$link, $token] = app(MintBookingLink::class)->handle($conversation, 'ac-repair', CarbonImmutable::now());

    $message = null;

    if ($carried) {
        $message = Message::factory()->for($conversation)->outbound()->create([
            'body' => 'Here is the link to book.',
            'meta' => ['booking_link_id' => $link->id],
        ]);

        app(MintBookingLink::class)->attachMessage($link, $message);
    }

    return [$link->fresh(), $token, $message];
}

/**
 * Book through the link at a time on the shop's wall clock.
 */
function linkTokenBook(BookingLink $link, string $start, ?string $now = null): Appointment
{
    return app(BookAppointment::class)->fromLink(
        $link,
        linkTokenChicago($start),
        $now === null ? CarbonImmutable::now() : linkTokenChicago($now),
    );
}

/**
 * Block the shop's time between two wall-clock moments.
 */
function linkTokenBlock(string $from, string $to): ScheduleBlock
{
    $start = linkTokenChicago($from);

    return ScheduleBlock::factory()->at($start, (int) $start->diffInMinutes(linkTokenChicago($to)))->create();
}

it('resolves each state a link can be in', function (string $case, string $stateName) {
    $state = BookingPageState::{$stateName};
    $token = str_repeat('a', 64);
    $now = CarbonImmutable::now();

    switch ($case) {
        case 'unknown':
            [, $conversation] = linkTokenSetUp();

            break;
        case 'revoked':
            [, $conversation] = linkTokenSetUp();
            [$link, $token] = linkTokenMint($conversation);
            app(MintBookingLink::class)->revoke($link);

            break;
        case 'superseded':
            [, $conversation] = linkTokenSetUp();
            [, $token] = linkTokenMint($conversation);
            linkTokenMint($conversation);

            break;
        case 'at expiry':
            [, $conversation] = linkTokenSetUp();
            [$link, $token] = linkTokenMint($conversation);
            $now = $link->expires_at->toImmutable();

            break;
        case 'a second before expiry':
            [, $conversation] = linkTokenSetUp();
            [$link, $token] = linkTokenMint($conversation);
            $now = $link->expires_at->toImmutable()->subSecond();

            break;
        case 'service file deleted':
            [, $conversation] = linkTokenSetUp();
            [, $token] = linkTokenMint($conversation);
            linkTokenResync(fn () => File::delete(icmPath('business/services/ac-repair.md')));

            break;
        case 'not bookable online':
            [, $conversation] = linkTokenSetUp();
            [, $token] = linkTokenMint($conversation);
            linkTokenResync(fn () => breakFile('business/services/ac-repair.md', fn (string $file): string => str_replace('book_online: true', 'book_online: false', $file)));

            break;
        case 'room off':
            [, $conversation] = linkTokenSetUp();
            [, $token] = linkTokenMint($conversation);
            linkTokenResync(fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking')));

            break;
        case 'every day blocked':
            [, $conversation] = linkTokenSetUp();
            [, $token] = linkTokenMint($conversation);
            linkTokenBlock('2026-11-03 00:00', '2026-11-20 00:00');

            break;
        case 'fresh':
            [, $conversation] = linkTokenSetUp();
            [, $token] = linkTokenMint($conversation);

            break;
    }

    $lookup = app(ResolveBookingLink::class)->handle($token, $now);

    expect($lookup)->toBeInstanceOf(BookingLinkLookup::class)
        ->and($lookup->state)->toBe($state);

    if ($state === BookingPageState::Picker) {
        expect($lookup->openStarts)->not->toBe([])
            ->and($lookup->service['slug'])->toBe('ac-repair')
            ->and($lookup->link->conversation_id)->toBe($conversation->id);
    }

    if ($state === BookingPageState::DoesNotWork && $case === 'unknown') {
        expect($lookup->link)->toBeNull();
    }
})->with([
    'an unknown token' => ['unknown', 'DoesNotWork'],
    'a revoked link' => ['revoked', 'DoesNotWork'],
    'a superseded link' => ['superseded', 'Replaced'],
    'a link at exactly its expiry' => ['at expiry', 'Expired'],
    'a link a second before its expiry' => ['a second before expiry', 'Picker'],
    'a service file that is gone' => ['service file deleted', 'ServiceUnavailable'],
    'a service no longer bookable online' => ['not bookable online', 'ServiceUnavailable'],
    'the room switched off' => ['room off', 'ServiceUnavailable'],
    'every day blocked' => ['every day blocked', 'NoTimes'],
    'a fresh link' => ['fresh', 'Picker'],
]);

it('shows the visit, not the replacement (U-14)', function () {
    [, $conversation] = linkTokenSetUp();
    [$link, $token] = linkTokenMint($conversation);
    $appointment = linkTokenBook($link, '2026-11-12 10:00');
    $resolve = app(ResolveBookingLink::class);
    $now = CarbonImmutable::now();

    $link->forceFill(['superseded_at' => $now])->save();

    expect($resolve->handle($token, $now)->state)->toBe(BookingPageState::Booked)
        ->and($resolve->handle($token, $now)->appointment->id)->toBe($appointment->id);

    $appointment->forceFill(['state' => AppointmentState::Cancelled])->save();
    expect($resolve->handle($token, $now)->state)->toBe(BookingPageState::Cancelled);

    $appointment->forceFill(['state' => AppointmentState::Done])->save();
    expect($resolve->handle($token, $now)->state)->toBe(BookingPageState::Done);
});

it('keeps the booked view past the link\'s expiry until the visit ends (U-14)', function () {
    [, $conversation] = linkTokenSetUp();
    [$link, $token] = linkTokenMint($conversation);
    $appointment = linkTokenBook($link, '2026-11-12 10:00');
    $resolve = app(ResolveBookingLink::class);

    expect($link->expires_at->lessThan($appointment->starts_at))->toBeTrue()
        ->and($resolve->handle($token, $link->expires_at->toImmutable()->addSecond())->state)->toBe(BookingPageState::Booked)
        ->and($resolve->handle($token, $appointment->ends_at->toImmutable()->subSecond())->state)->toBe(BookingPageState::Booked)
        ->and($resolve->handle($token, $appointment->ends_at->toImmutable())->state)->toBe(BookingPageState::Expired)
        ->and($resolve->handle($token, $appointment->ends_at->toImmutable()->addSecond())->state)->toBe(BookingPageState::Expired);
});

it('supersedes only unbooked links', function () {
    [, $conversation] = linkTokenSetUp();
    [$first] = linkTokenMint($conversation);
    linkTokenBook($first, '2026-11-05 10:00');
    [$second] = linkTokenMint($conversation);
    [$third] = linkTokenMint($conversation);

    expect($first->fresh()->superseded_at)->toBeNull()
        ->and($second->fresh()->superseded_at)->not->toBeNull()
        ->and($third->fresh()->superseded_at)->toBeNull();
});

it('books at most once, ever (D-13)', function () {
    [, $conversation] = linkTokenSetUp();
    [$link] = linkTokenMint($conversation);
    $appointment = linkTokenBook($link, '2026-11-05 10:00');

    expect(fn () => linkTokenBook($link, '2026-11-06 10:00'))->toThrow(BookingRefused::class, 'This link cannot book a time now.');

    $appointment->forceFill(['state' => AppointmentState::Cancelled])->save();

    expect(fn () => linkTokenBook($link, '2026-11-06 10:00'))->toThrow(BookingRefused::class, 'This link cannot book a time now.')
        ->and(Appointment::query()->count())->toBe(1);
});

it('keeps the unique index as the backstop (D-13)', function () {
    [, $conversation] = linkTokenSetUp();
    [$link] = linkTokenMint($conversation);
    linkTokenBook($link, '2026-11-05 10:00');

    $this->partialMock(ResolveBookingLink::class, fn ($mock) => $mock->shouldReceive('closedState')->andReturn(null));

    expect(fn () => linkTokenBook($link, '2026-11-06 10:00'))->toThrow(BookingRefused::class, 'This link cannot book a time now.')
        ->and(Appointment::query()->count())->toBe(1)
        ->and(OwnerAlert::query()->count())->toBe(1);
});

it('refuses a just-taken last open time as taken, not closed (D-07, U-09)', function () {
    [$contact, $conversation, $job] = linkTokenSetUp();

    linkTokenBlock('2026-11-03 00:00', '2026-11-05 10:00');
    linkTokenBlock('2026-11-05 12:00', '2026-11-20 00:00');

    [$link, $token] = linkTokenMint($conversation);
    $resolve = app(ResolveBookingLink::class);
    $lookup = $resolve->handle($token, CarbonImmutable::now());

    expect($lookup->state)->toBe(BookingPageState::Picker)
        ->and(array_keys($lookup->openStarts))->toBe(['2026-11-05'])
        ->and($lookup->openStarts['2026-11-05'])->toHaveCount(1);

    app(BookAppointment::class)->byHand($contact, User::factory()->create(), 'ac-repair', linkTokenChicago('2026-11-05 10:00'), $job->id, null);

    try {
        linkTokenBook($link, '2026-11-05 10:00');
        $this->fail('The taken time was booked.');
    } catch (BookingRefused $refused) {
        expect($refused->field)->toBe('starts_at')
            ->and($refused->getMessage())->toBe(BookingRefused::taken()->getMessage());
    }

    expect(Appointment::query()->count())->toBe(1)
        ->and(OwnerAlert::query()->count())->toBe(0)
        ->and($resolve->handle($token, CarbonImmutable::now())->state)->toBe(BookingPageState::NoTimes);
});

it('re-checks the service at the tap (D-02)', function () {
    [, $conversation] = linkTokenSetUp();
    [$link] = linkTokenMint($conversation);

    linkTokenResync(fn () => breakFile('business/services/ac-repair.md', fn (string $file): string => str_replace('book_online: true', 'book_online: false', $file)));

    expect(fn () => linkTokenBook($link, '2026-11-05 10:00'))->toThrow(BookingRefused::class, 'This link cannot book a time now.')
        ->and(Appointment::query()->count())->toBe(0)
        ->and(OwnerAlert::query()->count())->toBe(0);
});

it('re-checks the time at the tap (D-07)', function (string $start) {
    [$contact, $conversation, $job] = linkTokenSetUp();
    [$link] = linkTokenMint($conversation);

    if ($start === 'taken meanwhile') {
        app(BookAppointment::class)->byHand($contact, User::factory()->create(), 'ac-repair', linkTokenChicago('2026-11-05 10:00'), $job->id, null);
        $start = '2026-11-05 10:00';
    }

    expect(fn () => linkTokenBook($link, $start))->toThrow(BookingRefused::class, 'That time is taken')
        ->and(Appointment::query()->where('booking_link_id', $link->id)->count())->toBe(0);
})->with([
    'a time taken after the list was drawn' => ['taken meanwhile'],
    'a time inside the lead' => ['2026-11-03 12:00'],
    'a time that has passed' => ['2026-11-03 09:00'],
    'a closed day' => ['2026-11-08 10:00'],
]);

it('waits for the opening after hours (D-33, D-10)', function () {
    [, $conversation] = linkTokenSetUp();
    linkTokenTravel('2026-11-03 22:00');
    [$link] = linkTokenMint($conversation);

    linkTokenBook($link, '2026-11-05 10:00');

    $alert = OwnerAlert::query()->sole();

    expect($alert->reason)->toBe(AlertReason::AppointmentBooked)
        ->and($alert->sent_at)->toBeNull()
        ->and($alert->due_at->setTimezone('America/Chicago')->format('Y-m-d H:i'))->toBe('2026-11-04 08:00');
});

it('alerts a second booking from a new link (02.1 IN-11)', function () {
    [, $conversation] = linkTokenSetUp();
    [$first, , $firstMessage] = linkTokenMint($conversation);
    $appointment = linkTokenBook($first, '2026-11-05 10:00');
    $appointment->forceFill(['state' => AppointmentState::Cancelled])->save();

    [$second, , $secondMessage] = linkTokenMint($conversation);
    linkTokenBook($second, '2026-11-05 10:00');

    expect(OwnerAlert::query()->count())->toBe(2)
        ->and(OwnerAlert::query()->orderBy('id')->pluck('message_id')->all())->toBe([$firstMessage->id, $secondMessage->id]);
});

it('finds the carrying text by its meta when the back-fill never ran', function () {
    [, $conversation] = linkTokenSetUp();
    [$link, , $message] = linkTokenMint($conversation);
    $link->forceFill(['message_id' => null])->save();

    linkTokenBook($link, '2026-11-05 10:00');

    expect(OwnerAlert::query()->sole()->message_id)->toBe($message->id);
});

it('raises nothing without the carrying message and reports it', function () {
    Exceptions::fake();

    [, $conversation] = linkTokenSetUp();
    [$link, $token] = linkTokenMint($conversation, carried: false);

    $appointment = linkTokenBook($link, '2026-11-05 10:00');

    expect($appointment->exists)->toBeTrue()
        ->and(OwnerAlert::query()->count())->toBe(0);

    Exceptions::assertReported(fn (LogicException $exception): bool => str_contains($exception->getMessage(), (string) $link->id)
        && ! str_contains($exception->getMessage(), $token));
});
