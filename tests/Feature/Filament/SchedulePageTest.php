<?php

use App\Actions\OpenProject;
use App\Filament\Pages\Schedule;
use App\Filament\Resources\Contacts\ContactResource;
use App\Icm\ActiveConfig;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Project;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\Availability;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ian Owner']);
    $this->actingAs($this->owner);
});

/**
 * The schedule screen's tests (BOOK-03, D-40, D-16, D-11): the live example folder, the clock at Tuesday 2026-11-03 07:00
 * in the shop's zone, and Dana Whitfield, who texted and has one open job. Helpers carry the schedulePage prefix because
 * Pest functions are global.
 *
 * @return array{0: Contact, 1: Project}
 */
function schedulePageSetUp(?Closure $changeFolder = null): array
{
    if ($changeFolder !== null) {
        $changeFolder();
    }

    activateExampleFolder();
    test()->travelTo(CarbonImmutable::parse('2026-11-03 07:00', 'America/Chicago'));

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = $contact->conversations()->create();
    Message::factory()->create(['conversation_id' => $conversation->id]);
    $job = app(OpenProject::class)->byHand($contact, test()->owner);

    return [$contact, $job];
}

/**
 * Two visits on Dana's one job: Tuesday 10:00 AC repair and Tuesday 14:00 Furnace tune-up.
 *
 * @return array{0: Appointment, 1: Appointment}
 */
function schedulePageTwoVisits(Contact $contact, Project $job): array
{
    return [
        Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-03 10:00', 'America/Chicago'), 90)->create(),
        Appointment::factory()->for($contact)->for($job)->at(CarbonImmutable::parse('2026-11-03 14:00', 'America/Chicago'), 60)->create(['service_name' => 'Furnace tune-up']),
    ];
}

/**
 * The schedule page, mounted.
 */
function schedulePage(): Testable
{
    return Livewire::test(Schedule::class);
}

it('lists the day\'s visits under its heading', function () {
    [$contact, $job] = schedulePageSetUp();
    schedulePageTwoVisits($contact, $job);

    schedulePage()
        ->assertSee('Tuesday, November 3')
        ->assertSeeInOrder(['10:00 AM to 11:30 AM', 'AC repair', 'Dana Whitfield', 'Job 1', '1 hr 30 min', 'Booked'])
        ->assertSeeInOrder(['2:00 PM to 3:00 PM', 'Furnace tune-up', 'Dana Whitfield', 'Job 1', '1 hr', 'Booked'])
        ->assertSeeHtml('href="'.ContactResource::getUrl('view', ['record' => $contact]).'"');
});

it('shows both visits of one job, so a job with two appointments shows both', function () {
    [$contact, $job] = schedulePageSetUp();
    schedulePageTwoVisits($contact, $job);

    schedulePage()
        ->assertSee('AC repair')
        ->assertSee('Furnace tune-up')
        ->assertDontSee('Nothing scheduled');

    expect(Appointment::query()->where('project_id', $job->id)->count())->toBe(2);
});

it('blocks time, shows the block under its day and writes no timeline entry', function () {
    [$contact, $job] = schedulePageSetUp();
    schedulePageTwoVisits($contact, $job);
    $activitiesBefore = Activity::query()->count();

    $service = app(ActiveConfig::class)->service('ac-repair');
    $offeredWednesday = fn (): array => array_map(
        fn (CarbonImmutable $start): string => $start->setTimezone('America/Chicago')->format('H:i'),
        app(Availability::class)->openStarts($service, CarbonImmutable::now())['2026-11-04'] ?? [],
    );

    expect($offeredWednesday())->toContain('08:00');

    schedulePage()
        ->callAction('blockTime', data: [
            'starts_at' => '2026-11-04 08:00',
            'ends_at' => '2026-11-04 12:00',
            'note' => 'Truck in the shop',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Blocked Wednesday, November 4, 8:00 AM to 12:00 PM.')
        ->assertSee('Wednesday, November 4')
        ->assertSeeInOrder(['Blocked', 'Truck in the shop', 'Blocked by Ian Owner']);

    $block = ScheduleBlock::query()->sole();

    expect($block->booked_by_user_id)->toBe($this->owner->id)
        ->and($block->getRawOriginal('contact_id'))->toBeNull()
        ->and($block->getRawOriginal('project_id'))->toBeNull()
        ->and($block->getRawOriginal('service_slug'))->toBeNull()
        ->and($block->note)->toBe('Truck in the shop')
        ->and($block->isBooked())->toBeTrue()
        ->and(Activity::query()->count())->toBe($activitiesBefore);

    // The block counts in availability: nothing before noon on Wednesday is offered, and the afternoon still is.
    $offered = $offeredWednesday();

    expect($offered)->not->toBeEmpty()
        ->and(min($offered))->toBeGreaterThanOrEqual('12:00');
});
