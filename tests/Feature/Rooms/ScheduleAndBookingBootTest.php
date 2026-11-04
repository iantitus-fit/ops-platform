<?php

use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\BookingSend;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\RoomRegistry;
use App\Rooms\ScheduleAndBooking\BookingFileType;
use App\Rooms\ScheduleAndBooking\ScheduleAndBookingServiceProvider;
use App\Rooms\ScheduleAndBooking\ScheduleSection;
use Illuminate\Support\Facades\File;
use Tests\Rooms\RoomSectionAxe;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The schedule and booking room's boot test (ROOM-12, criterion 5), in the shape the money room set: the room boots empty
 * and with its example folder, and each boot proves the registered room, its own file type, its contract against SPEC's
 * rows (04 D-14), its dashboard section and the axe check on that section (A11Y-01). There is no database-driver guard
 * and no skip: CI runs this on SQLite and Postgres.
 *
 * "Empty" removes the one booking file. The room is then a valid empty room, and its section says which file turns the
 * customer texts on (D-03, 04 D-21).
 */
it('boots the schedule and booking room', function (bool $empty) {
    if ($empty) {
        File::delete(icmPath('rooms/schedule_and_booking/booking.md'));
    }

    $version = activateExampleFolder();
    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('schedule_and_booking');

    expect($version->file_count)->toBe(exampleFolderFileCount() - ($empty ? 1 : 0))
        ->and($config->hasRoom('schedule_and_booking'))->toBeTrue();

    expect($room->label)->toBe('schedule and booking')
        ->and($room->handles)->toBe([])
        ->and($room->fileTypes)->toHaveCount(1)
        ->and($room->fileTypes[0])->toBeInstanceOf(BookingFileType::class)
        ->and($room->fileTypes[0]->type())->toBe('booking')
        ->and($room->slot)->toBeNull()
        ->and($room->slotInterface)->toBeNull()
        ->and($room->sections)->toBe([ScheduleSection::class])
        ->and($room->tables)->toBe(['appointments', 'booking_links', 'booking_sends'])
        ->and($room->neverLeftOutBecause)->toBeNull();

    expect(array_map(fn ($entry) => $entry->key, $room->timelineEntries))
        ->toBe(['visit_booked', 'visit_cancelled', 'visit_done', 'visit_note_changed', 'booking_text_not_sent'])
        ->and(array_map(fn ($entry) => $entry->trusted, $room->timelineEntries))
        ->each->toBeTrue();

    expect($room->morphMap)->toBe([
        'appointment' => Appointment::class,
        'schedule_block' => ScheduleBlock::class,
        'booking_link' => BookingLink::class,
        'booking_send' => BookingSend::class,
    ]);

    $contract = $config->roomContract('schedule_and_booking');

    expect($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'schedule and booking'))
        ->and($contract['owns'])->toBe(['rooms/schedule_and_booking/booking.md'])
        ->and($contract['reads'])->toBe(['business/hours.md', 'business/profile.md', 'business/services/*.md'])
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('none');

    $booking = $config->filesOfType('booking');

    if ($empty) {
        expect($booking)->toBe([]);
    } else {
        expect($booking)->toHaveCount(1)
            ->and($booking[0]['path'])->toBe('rooms/schedule_and_booking/booking.md');
    }

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(ScheduleAndBookingServiceProvider::class);
})->with([
    'empty' => [true],
    'with its example folder' => [false],
]);

/**
 * D-35: the room is optional. A folder without rooms/schedule_and_booking/ still syncs, two files fewer, and the room is
 * off.
 */
it('lets a business leave the room out', function () {
    File::deleteDirectory(icmPath('rooms/schedule_and_booking'));

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount() - 2)
        ->and(app(ActiveConfig::class)->hasRoom('schedule_and_booking'))->toBeFalse();
});

/**
 * D-03, 04 D-21: the section names the file that turns the customer texts on while the booking file is missing, and the
 * visits part reads "Nothing booked." either way, because booking by hand works without the file.
 */
it('shows the section', function (bool $empty) {
    if ($empty) {
        File::delete(icmPath('rooms/schedule_and_booking/booking.md'));
    }

    activateExampleFolder();

    $dashboard = $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="schedule_and_booking"', false)
        ->assertSee('Schedule and booking')
        ->assertSee('Nothing booked.');

    if ($empty) {
        $dashboard->assertSee('Customer texts for booking stay off until rooms/schedule_and_booking/booking.md is added.');
    } else {
        $dashboard->assertDontSee('Customer texts for booking stay off');
    }
})->with([
    'empty' => [true],
    'with its example folder' => [false],
]);

/**
 * A11Y-01: axe finds nothing in the section, empty or with the example folder.
 */
it('finds no accessibility violation in the section', function (bool $empty) {
    if ($empty) {
        File::delete(icmPath('rooms/schedule_and_booking/booking.md'));
    }

    activateExampleFolder();

    $page = visit('/admin');
    $page->assertSee($empty ? 'Customer texts for booking stay off' : 'Nothing booked.');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'schedule_and_booking'));
})->with([
    'empty' => [true],
    'with its example folder' => [false],
]);
