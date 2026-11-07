<?php

use App\Actions\CancelAppointment;
use App\Actions\EditVisitNote;
use App\Actions\MarkAppointmentDone;
use App\Actions\OpenProject;
use App\Actions\RemoveBlock;
use App\Enums\AppointmentActor;
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
use Illuminate\Support\Facades\File;
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

/**
 * A booked block on the given moment for the given minutes, in the shop's zone.
 *
 * @param  array<string, mixed>  $attributes
 */
function schedulePageBlock(string $at, int $minutes, array $attributes = []): ScheduleBlock
{
    return ScheduleBlock::factory()
        ->at(CarbonImmutable::parse($at, 'America/Chicago'), $minutes)
        ->create(['booked_by_user_id' => test()->owner->id, ...$attributes]);
}

/**
 * One visit of Dana's on her job, at the given moment in the shop's zone.
 *
 * @param  array<string, mixed>  $attributes
 */
function schedulePageVisit(Contact $contact, Project $job, string $at, int $minutes = 90, array $attributes = []): Appointment
{
    return Appointment::factory()->for($contact)->for($job)
        ->at(CarbonImmutable::parse($at, 'America/Chicago'), $minutes)
        ->create($attributes);
}

/**
 * Rows for every Show choice, with the clock at Tuesday 2026-11-03 07:00. Each row is named by what it is.
 *
 * @return array<string, int>
 */
function schedulePageWorld(Contact $contact, Project $job): array
{
    $removed = schedulePageBlock('2026-11-05 09:00', 60, [
        'state' => 'cancelled',
        'cancelled_by' => AppointmentActor::User,
        'cancelled_by_user_id' => test()->owner->id,
        'cancelled_at' => now(),
    ]);

    return [
        'today' => schedulePageVisit($contact, $job, '2026-11-03 10:00')->id,
        'ahead' => schedulePageVisit($contact, $job, '2026-11-04 10:00')->id,
        'block' => schedulePageBlock('2026-11-04 13:00', 120)->id,
        'earlier' => schedulePageVisit($contact, $job, '2026-11-02 09:00')->id,
        'done' => schedulePageVisit($contact, $job, '2026-11-02 13:00', 60, ['state' => 'done', 'done_by_user_id' => test()->owner->id, 'done_at' => now()])->id,
        'oldBlock' => schedulePageBlock('2026-11-02 15:00', 60)->id,
        'cancelled' => Appointment::factory()->for($contact)->for($job)->cancelled()->at(CarbonImmutable::parse('2026-11-04 15:00', 'America/Chicago'), 60)->create()->id,
        'removed' => $removed->id,
    ];
}

it('filters by Show', function (string $show, array $listed) {
    [$contact, $job] = schedulePageSetUp();
    $world = schedulePageWorld($contact, $job);

    $page = schedulePage();

    if ($show !== 'upcoming') {
        $page->filterTable('show', $show);
    }

    $page->assertCanSeeTableRecords(array_map(fn (string $name): int => $world[$name], $listed))
        ->assertCanNotSeeTableRecords(array_values(array_diff_key($world, array_flip($listed))))
        ->assertCountTableRecords(count($listed));
})->with([
    'upcoming is the default: booked visits and blocks from today' => ['upcoming', ['today', 'ahead', 'block']],
    'past: started before today, booked or done, blocks too' => ['past', ['earlier', 'done', 'oldBlock']],
    'cancelled: cancelled visits only, not a removed block' => ['cancelled', ['cancelled']],
    'done: done visits only' => ['done', ['done']],
]);

it('says so when a view is empty', function (?string $show, string $heading, string $description) {
    schedulePageSetUp();

    $page = schedulePage();

    if ($show !== null) {
        $page->filterTable('show', $show);
    }

    $page->assertSee($heading)->assertSee($description);
})->with([
    'upcoming' => [null, 'Nothing scheduled', 'Booked visits and blocked time show here.'],
    'past' => ['past', 'No visits in this view', 'Switch the Show filter to Upcoming.'],
    'cancelled' => ['cancelled', 'No visits in this view', 'Switch the Show filter to Upcoming.'],
    'done' => ['done', 'No visits in this view', 'Switch the Show filter to Upcoming.'],
]);

it('groups by the business\'s day, not the UTC day', function () {
    [$contact, $job] = schedulePageSetUp();
    $evening = schedulePageVisit($contact, $job, '2026-11-03 19:00', 90);

    expect($evening->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-04 01:00');

    schedulePage()
        ->assertSee('Tuesday, November 3')
        ->assertDontSee('Wednesday, November 4')
        ->assertSee('7:00 PM to 8:30 PM');
});

it('orders rows by start, and a block and a visit at the same start keep id order', function () {
    [$contact, $job] = schedulePageSetUp();
    $late = schedulePageVisit($contact, $job, '2026-11-04 15:00', 60);
    $visit = schedulePageVisit($contact, $job, '2026-11-04 09:00', 60);
    $block = schedulePageBlock('2026-11-04 09:00', 30);
    $early = schedulePageVisit($contact, $job, '2026-11-03 10:00', 60);

    schedulePage()->assertCanSeeTableRecords([$early->id, $visit->id, $block->id, $late->id], inOrder: true);
});

it('lists 25 rows a page and shows a long note whole', function () {
    [$contact, $job] = schedulePageSetUp();
    $note = str_repeat('Gate code 4412, dog in the yard. ', 6);

    foreach (range(1, 26) as $index) {
        schedulePageVisit($contact, $job, '2026-11-04 08:00', 30, ['note' => $index === 1 ? trim($note) : null]);
    }

    $page = schedulePage();

    expect($page->instance()->getTableRecords())->toHaveCount(25);

    $page->assertSee(trim($note));
});

it('acts on visits from the schedule with the contact screen\'s dialogs', function () {
    [$contact, $job] = schedulePageSetUp();
    $visit = schedulePageVisit($contact, $job, '2026-11-03 10:00');

    $dialog = schedulePage()->mountTableAction('cancelVisit', $visit->id)->instance()->getMountedAction();

    expect($dialog->getName())->toBe('cancelVisit')
        ->and((string) $dialog->getModalHeading())->toBe('Cancel AC repair, Tuesday, November 3 at 10:00 AM?')
        ->and((string) $dialog->getModalDescription())->toContain('Dana Whitfield is not texted about this, so tell them yourself.')
        ->and((string) $dialog->getModalSubmitActionLabel())->toBe('Cancel appointment')
        ->and((string) $dialog->getModalCancelActionLabel())->toBe('Keep appointment');

    schedulePage()->callTableAction('cancelVisit', $visit->id)->assertNotified('Cancelled');

    expect($visit->refresh()->state->value)->toBe('cancelled');
});

it('marks a started visit done and edits a visit note from the schedule', function () {
    [$contact, $job] = schedulePageSetUp();
    $visit = schedulePageVisit($contact, $job, '2026-11-03 10:00');
    $other = schedulePageVisit($contact, $job, '2026-11-04 10:00');

    schedulePage()->assertTableActionHidden('markDone', $visit->id);

    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00', 'America/Chicago'));

    $dialog = schedulePage()->mountTableAction('markDone', $visit->id)->instance()->getMountedAction();

    expect((string) $dialog->getModalHeading())->toBe('Mark AC repair, Tuesday, November 3 at 10:00 AM as done?')
        ->and((string) $dialog->getModalDescription())->toBe('This cannot be undone.');

    schedulePage()->callTableAction('markDone', $visit->id)->assertNotified('Marked done');

    expect($visit->refresh()->state->value)->toBe('done');

    schedulePage()
        ->mountTableAction('editNote', $other->id)
        ->assertTableActionDataSet(['note' => null]);

    schedulePage()
        ->callTableAction('editNote', $other->id, data: ['note' => 'Gate code 4412'])
        ->assertNotified('Visit note saved');

    expect($other->refresh()->note)->toBe('Gate code 4412');
});

it('offers each row only its own kind\'s actions', function () {
    [$contact, $job] = schedulePageSetUp();
    $visit = schedulePageVisit($contact, $job, '2026-11-03 10:00');
    $block = schedulePageBlock('2026-11-04 08:00', 240);
    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:30', 'America/Chicago'));

    $page = schedulePage()
        ->assertTableActionVisible('removeBlock', $block->id)
        ->assertTableActionHidden('cancelVisit', $block->id)
        ->assertTableActionHidden('markDone', $block->id)
        ->assertTableActionHidden('editNote', $block->id)
        ->assertTableActionHidden('removeBlock', $visit->id)
        ->assertTableActionVisible('cancelVisit', $visit->id)
        ->assertTableActionVisible('markDone', $visit->id)
        ->assertTableActionVisible('editNote', $visit->id);

    $page->assertSeeHtml('aria-label="Cancel visit: AC repair, Tuesday, November 3 at 10:00 AM"')
        ->assertSeeHtml('aria-label="Mark done: AC repair, Tuesday, November 3 at 10:00 AM"')
        ->assertSeeHtml('aria-label="Edit note: AC repair, Tuesday, November 3 at 10:00 AM"')
        ->assertSeeHtml('aria-label="Remove block: Wednesday, November 4, 8:00 AM to 12:00 PM"');
});

it('refuses a cross-kind call at the action and changes nothing', function () {
    [$contact, $job] = schedulePageSetUp();
    $visit = schedulePageVisit($contact, $job, '2026-11-03 10:00');
    $block = schedulePageBlock('2026-11-03 08:00', 60, ['note' => 'Truck in the shop']);
    $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00', 'America/Chicago'));
    $activities = Activity::query()->count();

    $blockAsVisit = Appointment::query()->withoutGlobalScope('kind')->find($block->id);
    $visitAsBlock = ScheduleBlock::query()->withoutGlobalScope('kind')->find($visit->id);

    app(CancelAppointment::class)->handle($blockAsVisit, $this->owner);
    app(MarkAppointmentDone::class)->handle($blockAsVisit, $this->owner);
    app(EditVisitNote::class)->handle($blockAsVisit, $this->owner, 'Changed');
    app(RemoveBlock::class)->handle($visitAsBlock, $this->owner);

    expect($block->refresh()->isBooked())->toBeTrue()
        ->and($block->note)->toBe('Truck in the shop')
        ->and($block->cancelled_by_user_id)->toBeNull()
        ->and($visit->refresh()->isBooked())->toBeTrue()
        ->and($visit->cancelled_by_user_id)->toBeNull()
        ->and(Activity::query()->count())->toBe($activities);
});

it('refuses a forged cross-kind call at the screen and changes nothing', function () {
    [$contact, $job] = schedulePageSetUp();
    $visit = schedulePageVisit($contact, $job, '2026-11-03 10:00');
    $block = schedulePageBlock('2026-11-04 08:00', 240);

    schedulePage()
        ->call('mountTableAction', 'removeBlock', (string) $visit->id)
        ->call('callMountedTableAction')
        ->call('mountTableAction', 'cancelVisit', (string) $block->id)
        ->call('callMountedTableAction');

    expect($visit->refresh()->isBooked())->toBeTrue()
        ->and($block->refresh()->isBooked())->toBeTrue();
});

it('removes a block after asking, and the time is offered again', function () {
    schedulePageSetUp();
    $block = schedulePageBlock('2026-11-04 08:00', 240);
    $activities = Activity::query()->count();

    $service = app(ActiveConfig::class)->service('ac-repair');
    $offered = fn (): array => array_map(
        fn (CarbonImmutable $start): string => $start->setTimezone('America/Chicago')->format('H:i'),
        app(Availability::class)->openStarts($service, CarbonImmutable::now())['2026-11-04'] ?? [],
    );

    expect(min($offered()))->toBeGreaterThanOrEqual('12:00');

    $dialog = schedulePage()->mountTableAction('removeBlock', $block->id)->instance()->getMountedAction();

    expect((string) $dialog->getModalHeading())->toBe('Remove the block on Wednesday, November 4, 8:00 AM to 12:00 PM?')
        ->and((string) $dialog->getModalDescription())->toBe('Customers can book that time again.')
        ->and((string) $dialog->getModalSubmitActionLabel())->toBe('Remove block')
        ->and($block->refresh()->isBooked())->toBeTrue();

    schedulePage()->callTableAction('removeBlock', $block->id)->assertNotified('Block removed');

    $block->refresh();
    $removedAt = $block->cancelled_at;

    expect($block->state->value)->toBe('cancelled')
        ->and($block->cancelled_by)->toBe(AppointmentActor::User)
        ->and($block->cancelled_by_user_id)->toBe($this->owner->id)
        ->and($removedAt)->not->toBeNull()
        ->and(Activity::query()->count())->toBe($activities)
        ->and($offered())->toContain('08:00');

    // A second remove changes nothing.
    $this->travelTo(now()->addMinute());
    app(RemoveBlock::class)->handle(ScheduleBlock::query()->withoutGlobalScope('kind')->find($block->id), $this->owner);

    expect($block->refresh()->cancelled_at->equalTo($removedAt))->toBeTrue();
});

/**
 * The messages the open Block time dialog shows beside one field.
 *
 * @return list<string>
 */
function schedulePageFieldErrors(Testable $page, string $field): array
{
    return collect($page->errors()->toArray())
        ->filter(fn (array $messages, string $key): bool => str_ends_with($key, '.'.$field))
        ->flatten()
        ->values()
        ->all();
}

it('refuses a bad block beside the field', function (array $data, string $field, string $sentence) {
    [$contact, $job] = schedulePageSetUp();
    schedulePageVisit($contact, $job, '2026-11-03 10:00', 90);

    $page = schedulePage()->callAction('blockTime', data: ['note' => '', ...$data])->assertHasActionErrors([$field]);

    expect(schedulePageFieldErrors($page, $field))->toBe([$sentence])
        ->and(ScheduleBlock::query()->count())->toBe(0);
})->with([
    'the end is before the start' => [['starts_at' => '2026-11-04 12:00', 'ends_at' => '2026-11-04 08:00'], 'ends_at', 'The end must be after the start.'],
    'the end is the start' => [['starts_at' => '2026-11-04 12:00', 'ends_at' => '2026-11-04 12:00'], 'ends_at', 'The end must be after the start.'],
    'the start has passed' => [['starts_at' => '2026-11-03 06:59', 'ends_at' => '2026-11-03 08:00'], 'starts_at', 'Pick a time that has not passed.'],
    'it covers a booked visit' => [['starts_at' => '2026-11-03 09:30', 'ends_at' => '2026-11-03 10:30'], 'starts_at', 'Dana Whitfield is booked on Tuesday, November 3 at 10:00 AM. Cancel that visit first, or pick another time.'],
    'it sits inside a booked visit' => [['starts_at' => '2026-11-03 10:15', 'ends_at' => '2026-11-03 10:45'], 'starts_at', 'Dana Whitfield is booked on Tuesday, November 3 at 10:00 AM. Cancel that visit first, or pick another time.'],
    'the note is 101 characters' => [['starts_at' => '2026-11-04 08:00', 'ends_at' => '2026-11-04 12:00', 'note' => str_repeat('a', 101)], 'note', 'A block note can be at most 100 characters.'],
]);

it('accepts a block that only touches a booked visit, and one over another block', function (string $starts, string $ends) {
    [$contact, $job] = schedulePageSetUp();
    schedulePageVisit($contact, $job, '2026-11-03 10:00', 90);
    schedulePageBlock('2026-11-03 14:00', 120);

    schedulePage()
        ->callAction('blockTime', data: ['starts_at' => $starts, 'ends_at' => $ends, 'note' => ''])
        ->assertHasNoActionErrors();

    expect(ScheduleBlock::query()->count())->toBe(2);
})->with([
    'ending when the visit starts' => ['2026-11-03 08:00', '2026-11-03 10:00'],
    'starting when the visit ends' => ['2026-11-03 11:30', '2026-11-03 12:30'],
    'over another block' => ['2026-11-03 15:00', '2026-11-03 17:00'],
]);

it('reads block times in the business zone on both change days', function (string $today, string $typed, string $until, string $stored) {
    schedulePageSetUp();
    $this->travelTo(CarbonImmutable::parse($today, 'America/Chicago'));

    schedulePage()
        ->callAction('blockTime', data: ['starts_at' => $typed, 'ends_at' => $until, 'note' => ''])
        ->assertHasNoActionErrors();

    expect(ScheduleBlock::query()->sole()->starts_at->utc()->format('Y-m-d H:i'))->toBe($stored);
})->with([
    'the missing hour in spring' => ['2026-03-06 09:00', '2026-03-08 02:30', '2026-03-08 05:00', '2026-03-08 08:30'],
    'the repeated hour in autumn' => ['2026-10-30 09:00', '2026-11-01 01:30', '2026-11-01 04:00', '2026-11-01 06:30'],
]);

it('refuses a block with the room off, and the page is not reachable', function () {
    schedulePageSetUp(fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking')));

    $this->get('/admin/schedule')->assertForbidden();
    $this->get('/admin')->assertOk()->assertDontSee('/admin/schedule');
});

it('is in the navigation with the room on', function () {
    schedulePageSetUp();

    $this->get('/admin')->assertOk()->assertSee('/admin/schedule');
    $this->get('/admin/schedule')->assertOk()->assertSee('Schedule')->assertSee('Block time');
});

it('escapes an owner\'s block note and visit note', function () {
    [$contact, $job] = schedulePageSetUp();
    $hostile = '"><script>alert(1)</script>';
    schedulePageVisit($contact, $job, '2026-11-03 10:00', 90, ['note' => $hostile, 'service_name' => $hostile]);
    schedulePageBlock('2026-11-04 08:00', 240, ['note' => $hostile]);

    schedulePage()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSeeHtml('aria-label="Cancel visit: &quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;, Tuesday, November 3 at 10:00 AM"');
});
