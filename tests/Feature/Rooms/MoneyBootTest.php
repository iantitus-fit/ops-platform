<?php

use App\Icm\ActiveConfig;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\Money\MoneyNotesType;
use App\Rooms\Money\MoneyServiceProvider;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;
use Tests\Rooms\RoomSectionAxe;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The money room's boot test (ROOM-14), in the shape the pretend room and the earlier real rooms set: the room boots
 * empty and with its example folder, each boot proves the registered room, its own file type, its contract against
 * SPEC's rows (04 D-14), its dashboard section and the axe check on that section (A11Y-01, D-18). The room has no slot,
 * tables or timeline entries (ROOM-14, D-05), so those proofs are that it adds none. There is no database-driver guard
 * and no skip: CI runs this on SQLite and Postgres.
 *
 * "Empty" removes the one notes file. The room is then a valid empty room, and its section says which file to add
 * (the contract owns the exact path, so the empty line names it).
 */
it('boots the money room', function (bool $empty) {
    if ($empty) {
        File::delete(icmPath('rooms/money/notes.md'));
    }

    $version = activateExampleFolder();
    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('money');

    expect($version->file_count)->toBe(exampleFolderFileCount() - ($empty ? 1 : 0))
        ->and($config->hasRoom('money'))->toBeTrue();

    expect($room->label)->toBe('money')
        ->and($room->handles)->toBe([])
        ->and($room->fileTypes)->toHaveCount(1)
        ->and($room->fileTypes[0])->toBeInstanceOf(MoneyNotesType::class)
        ->and($room->fileTypes[0]->type())->toBe('money_notes')
        ->and($room->slot)->toBeNull()
        ->and($room->slotInterface)->toBeNull()
        ->and($room->tables)->toBe([])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([]);

    $contract = $config->roomContract('money');

    expect($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'money'))
        ->and($contract['owns'])->toBe(['rooms/money/notes.md'])
        ->and($contract['reads'])->toBe(['business/profile.md'])
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('none');

    $notes = $config->filesOfType('money_notes');

    if ($empty) {
        expect($notes)->toBe([]);
    } else {
        expect($notes)->toHaveCount(1)
            ->and($notes[0]['path'])->toBe('rooms/money/notes.md');
    }

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(MoneyServiceProvider::class);

    $dashboard = $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="money"', false)
        ->assertSee('Money');

    if ($empty) {
        $dashboard->assertSee('Money: nothing set up yet. Add rooms/money/notes.md to start.')
            ->assertDontSee('Payment notes: in place');
    } else {
        $dashboard->assertSee('Payment notes: in place')
            ->assertDontSee('Money: nothing set up yet');
    }

    $page = visit('/admin');
    $page->assertSee($empty ? 'nothing set up yet' : 'Payment notes: in place');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'money'));
})->with([
    'empty' => [true],
    'with its example folder' => [false],
]);

/**
 * D-27 under the shipped contract: a second notes file in the room's folder is refused in one line naming both files,
 * and the version that was live stays live. The body has no digit, so the number check never adds a line here.
 */
it('refuses a second money notes file under the shipped contract', function () {
    $live = activateExampleFolder();
    writeIcmFile('rooms/money/more.md', "---\ntype: money_notes\n---\nMore about how we get paid.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/money/more.md: this business already has a money notes file in rooms/money/notes.md; keep one'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * D-27 under a contract the owner edited to own a second path: owning the file does not let a business keep two.
 */
it('refuses a second money notes file under a contract edited to own two paths', function () {
    $live = activateExampleFolder();

    breakFile('rooms/money/CONTEXT.md', fn (string $contents): string => str_replace(
        'owns: [rooms/money/notes.md]',
        'owns: [rooms/money/notes.md, rooms/money/more.md]',
        $contents,
    ));
    writeIcmFile('rooms/money/more.md', "---\ntype: money_notes\n---\nMore about how we get paid.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/money/more.md: this business already has a money notes file in rooms/money/notes.md; keep one'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * D-27: owning two paths is fine while only one notes file exists, so the contract can be edited ahead of the file.
 */
it('accepts a contract that owns two paths while only one notes file is there', function () {
    activateExampleFolder();

    breakFile('rooms/money/CONTEXT.md', fn (string $contents): string => str_replace(
        'owns: [rooms/money/notes.md]',
        'owns: [rooms/money/notes.md, rooms/money/more.md]',
        $contents,
    ));

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->filesOfType('money_notes'))->toHaveCount(1);
});

/**
 * ROOM-14, D-05: the room adds no table and no payment record in this phase (backlog 999.1), so no migration in the
 * repo mentions a payment table, and the room registers nothing for the database.
 */
it('adds no payment table or record', function () {
    $room = app(RoomRegistry::class)->get('money');

    $migrations = collect(File::files(database_path('migrations')))
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->filter(fn (string $name): bool => str_contains($name, 'payment') || str_contains($name, 'money'))
        ->values()
        ->all();

    expect($room->tables)->toBe([])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([])
        ->and($migrations)->toBe([]);
});
