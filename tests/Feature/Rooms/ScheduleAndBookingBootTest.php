<?php

use App\Filament\Pages\Schedule;
use App\Filament\Resources\Contacts\ContactResource;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\BookingSend;
use App\Models\Contact;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\RoomRegistry;
use App\Rooms\RoomSection;
use App\Rooms\ScheduleAndBooking\BookingFileType;
use App\Rooms\ScheduleAndBooking\ScheduleAndBookingServiceProvider;
use App\Rooms\ScheduleAndBooking\ScheduleSection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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

/**
 * What the schedule section drew, from its marker to the end of the dashboard's section wrapper.
 */
function scheduleSectionHtml(string $html): string
{
    expect(preg_match('/<div data-room-section="schedule_and_booking">(.*?)<\/div>\s*<\/div>/s', $html, $section))->toBe(1);

    return $section[1];
}

/**
 * The clock at Tuesday 2026-11-03 07:00 in the shop's zone and $count booked AC repair visits ahead, each for a contact
 * of its own: the first today at 2:00 PM, the rest on the days after at 10:00 AM. Beside them sit what the section must
 * leave out: a cancelled visit, a done one and a block.
 */
function scheduleSectionVisits(int $count): void
{
    test()->travelTo(CarbonImmutable::parse('2026-11-03 07:00', 'America/Chicago'));

    for ($index = 1; $index <= $count; $index++) {
        $start = $index === 1 ? '2026-11-03 14:00' : CarbonImmutable::parse('2026-11-03 10:00', 'America/Chicago')->addDays($index - 1)->format('Y-m-d H:i');

        Appointment::factory()
            ->for(Contact::factory()->create(['name' => 'Visit Person '.$index]))
            ->at(CarbonImmutable::parse($start, 'America/Chicago'), 90)
            ->create();
    }

    Appointment::factory()->for(Contact::factory()->create(['name' => 'Cancelled Person']))->cancelled()->at(CarbonImmutable::parse('2026-11-04 15:00', 'America/Chicago'), 60)->create();
    Appointment::factory()->for(Contact::factory()->create(['name' => 'Done Person']))->done()->at(CarbonImmutable::parse('2026-11-02 15:00', 'America/Chicago'), 60)->create();
    ScheduleBlock::factory()->at(CarbonImmutable::parse('2026-11-04 08:00', 'America/Chicago'), 120)->create(['note' => 'Truck in the shop']);
}

/**
 * D-40, U-30: the section lists at most the next five booked visits, today's first, each a link to the contact with an
 * absolute date and time in a time element, the service and the job; past five it says how many there are and links to the
 * schedule; with none it says so. A cancelled visit, a done one and a block never appear.
 */
it('lists the next five visits on the dashboard', function (int $visits, int $shown) {
    activateExampleFolder();
    scheduleSectionVisits($visits);

    $section = scheduleSectionHtml($this->get('/admin')->assertOk()->getContent());

    expect(substr_count($section, 'data-schedule-row'))->toBe($shown)
        ->and($section)->not->toContain('Cancelled Person')
        ->and($section)->not->toContain('Done Person')
        ->and($section)->not->toContain('Truck in the shop');

    if ($visits === 0) {
        expect($section)->toContain('Nothing booked.');

        return;
    }

    expect($section)->not->toContain('Nothing booked.')
        ->and($section)->toContain('Visit Person 1')
        ->and($section)->toContain('<time datetime="2026-11-03T14:00:00-06:00">Tuesday, November 3 at 2:00 PM</time>')
        ->and($section)->toContain('AC repair')
        ->and($section)->toContain('Job 1');

    if ($visits > 5) {
        expect($section)->toContain('Showing the next 5 of '.$visits.' booked visits.')
            ->and($section)->toContain('Open the schedule')
            ->and($section)->toContain('href="'.Schedule::getUrl().'"')
            ->and($section)->not->toContain('Visit Person 6');
    } else {
        expect($section)->not->toContain('Showing the next')
            ->and($section)->not->toContain('Open the schedule');
    }
})->with([
    'none' => [0, 0],
    'one' => [1, 1],
    'five' => [5, 5],
    'seven' => [7, 5],
]);

it('links each row to its contact and names a contact with no name', function () {
    activateExampleFolder();
    scheduleSectionVisits(1);
    $nameless = Contact::factory()->create(['name' => null]);
    Appointment::factory()->for($nameless)->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'), 60)->create();

    $section = scheduleSectionHtml($this->get('/admin')->assertOk()->getContent());

    expect($section)->toContain('href="'.ContactResource::getUrl('view', ['record' => $nameless]).'"')
        ->and($section)->toContain('(no name)');
});

it('lists the visits beside the missing booking file line', function () {
    File::delete(icmPath('rooms/schedule_and_booking/booking.md'));
    activateExampleFolder();
    scheduleSectionVisits(2);

    $section = scheduleSectionHtml($this->get('/admin')->assertOk()->getContent());

    expect($section)->toContain(ScheduleSection::BOOKING_OFF_LINE)
        ->and(substr_count($section, 'data-schedule-row'))->toBe(2);
});

it('shows one line when the section cannot load', function () {
    activateExampleFolder();
    scheduleSectionVisits(1);

    Exceptions::fake();
    Schema::rename('appointments', 'appointments_unavailable');

    $response = $this->get('/admin')->assertOk();

    expect(scheduleSectionHtml($response->getContent()))->toContain(RoomSection::FAILED_LINE)
        ->and(scheduleSectionHtml($response->getContent()))->not->toContain('data-schedule-row');
});

/**
 * A11Y-01: axe finds nothing in the section with no visit booked and with seven (five rows, the total line and the link).
 */
it('finds no accessibility violation in the section with visits', function (int $visits) {
    activateExampleFolder();
    scheduleSectionVisits($visits);

    $page = visit('/admin');
    $page->assertSee($visits === 0 ? 'Nothing booked.' : 'Open the schedule');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'schedule_and_booking'));
})->with([
    'none' => [0],
    'seven' => [7],
]);
