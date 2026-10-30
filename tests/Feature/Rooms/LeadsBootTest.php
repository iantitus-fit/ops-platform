<?php

use App\Filament\Widgets\NewLeads;
use App\Icm\ActiveConfig;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\Leads\LeadsServiceProvider;
use App\Rooms\RoomRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The leads room's boot test (ROOM-05, ROOM-07), in the shape the team room set: the room boots and each boot proves the
 * registered room and its contract against SPEC's rows (04 D-14). The leads room owns no file, so its empty folder and
 * its example folder are the same folder and there is one boot case, not two (04 D-21). The section listing open projects
 * and its axe check join in plan 07-07. There is no database-driver guard and no skip: CI runs this on SQLite and
 * Postgres.
 */
it('boots the leads room', function () {
    $version = activateExampleFolder();

    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('leads');

    expect($version->file_count)->toBe(exampleFolderFileCount())
        ->and($config->hasRoom('leads'))->toBeTrue();

    expect($room->label)->toBe('leads')
        ->and($room->handles)->toBe([])
        ->and($room->fileTypes)->toBe([])
        ->and($room->slot)->toBeNull()
        ->and($room->slotInterface)->toBeNull()
        ->and($room->sections)->toBe([])
        ->and($room->tables)->toBe([])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([])
        ->and($room->neverLeftOutBecause)->toBeNull()
        ->and($room->claimedWidgets)->toBe([NewLeads::class]);

    $contract = $config->roomContract('leads');

    expect($contract['owns'])->toBe([])
        ->and($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'leads'))
        ->and($contract['reads'])->toBe(['business/lead-sources.md', 'business/offers/*.md'])
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('none');

    expect($config->roomOwnedFiles('leads'))->toBe([]);

    $context = File::get(icmPath('rooms/leads/CONTEXT.md'));

    expect($context)->toContain('It owns no file.')
        ->and($context)->toContain('The web form is shared and owned by no room, so it keeps taking leads while this room is off.');

    expect(File::directories(icmPath('rooms/leads')))->toBe([])
        ->and(array_map('basename', File::files(icmPath('rooms/leads'))))->toBe(['CONTEXT.md']);

    expect(app(RoomRegistry::class)->widgetClasses())->not->toContain(NewLeads::class);

    $panelWidgets = Filament::getPanel('admin')->getWidgets();

    expect(array_count_values($panelWidgets)[NewLeads::class] ?? 0)->toBe(1);

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(LeadsServiceProvider::class);

    $this->get('/admin')
        ->assertOk()
        ->assertSeeInOrder([
            'Drafts waiting',
            'Customers waiting while you handle it',
            'New leads',
            "Today's conversations",
        ]);
});

/**
 * D-03: the leads room is optional. A folder without rooms/leads/ still activates, one file fewer, the room is off, and
 * New leads still shows because the widget is the panel's own and the room only points at it.
 */
it('lets a business leave the leads room out', function () {
    File::deleteDirectory(icmPath('rooms/leads'));

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount() - 1)
        ->and(app(ActiveConfig::class)->hasRoom('leads'))->toBeFalse();

    $this->get('/admin')
        ->assertOk()
        ->assertSee('New leads');
});
