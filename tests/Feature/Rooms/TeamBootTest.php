<?php

use App\Filament\Widgets\DraftsWaiting;
use App\Icm\ActiveConfig;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\RoomRegistry;
use App\Rooms\Team\TeamServiceProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The team room's boot test (ROOM-15), in the shape the earlier real rooms set: the room boots and each boot proves the
 * registered room and its contract against SPEC's rows (04 D-14). The team owns no file, so its empty folder and its
 * example folder are the same folder and there is one case, not two (04 D-21). It adds no dashboard section, so there is
 * no axe call here; 04 D-27 starts the accessibility check for this room's screens in Phase 11. There is no
 * database-driver guard and no skip: CI runs this on SQLite and Postgres.
 */
it('boots the team room', function () {
    $version = activateExampleFolder();

    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('team');

    expect($version->file_count)->toBe(exampleFolderFileCount())
        ->and($config->hasRoom('team'))->toBeTrue();

    expect($room->label)->toBe('team')
        ->and($room->handles)->toBe([])
        ->and($room->fileTypes)->toBe([])
        ->and($room->slot)->toBeNull()
        ->and($room->slotInterface)->toBeNull()
        ->and($room->sections)->toBe([])
        ->and($room->tables)->toBe(['owner_alerts'])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([])
        ->and($room->claimedWidgets)->toBe([DraftsWaiting::class]);

    $contract = $config->roomContract('team');

    expect($contract['owns'])->toBe([])
        ->and($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'team'))
        ->and($contract['reads'])->toBe(['rules/sending.md', 'rules/replies.md', 'agents/front-desk.md'])
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('none');

    expect($config->roomOwnedFiles('team'))->toBe([]);

    expect(File::get(icmPath('rooms/team/CONTEXT.md')))->toContain('who resolved each draft');

    expect(File::directories(icmPath('rooms/team')))->toBe([])
        ->and(array_map('basename', File::files(icmPath('rooms/team'))))->toBe(['CONTEXT.md']);

    expect(app(RoomRegistry::class)->widgetClasses())->not->toContain(DraftsWaiting::class);

    $panelWidgets = Filament::getPanel('admin')->getWidgets();

    expect(array_count_values($panelWidgets)[DraftsWaiting::class] ?? 0)->toBe(1);

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(TeamServiceProvider::class);

    $this->get('/admin')
        ->assertOk()
        ->assertSeeInOrder([
            'Drafts waiting',
            'Customers waiting while you handle it',
            'New leads',
            "Today's conversations",
        ]);
});
