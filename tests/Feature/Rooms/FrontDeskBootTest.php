<?php

use App\Channels\FakeTextChannel;
use App\Channels\TextChannel;
use App\Filament\Widgets\NewLeads;
use App\Filament\Widgets\PausedConversationsWaiting;
use App\Filament\Widgets\TodaysConversations;
use App\Icm\ActiveConfig;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\FrontDesk\FrontDeskServiceProvider;
use App\Rooms\RoomRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The front desk room's boot test (ROOM-06), in the shape the pretend room and the earlier real rooms set: the room boots
 * empty and with its example folder, and each boot proves the registered room and its contract against SPEC's rows
 * (04 D-14). The room adds no dashboard section, so there is no axe call here; 04 D-27 starts the accessibility check
 * for this room's screens in Phase 11. There is no database-driver guard and no skip: CI runs this on SQLite and
 * Postgres.
 *
 * "Empty" removes agents/front-desk.md, the one front desk file the sync does not require, as marketing's empty row
 * removes its offer. The sync accepts a folder with no agent file.
 */
it('boots the front desk room', function (bool $empty) {
    if ($empty) {
        File::delete(icmPath('agents/front-desk.md'));
    }

    $version = activateExampleFolder();

    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('front_desk');

    expect($version->file_count)->toBe(exampleFolderFileCount() - ($empty ? 1 : 0))
        ->and($config->hasRoom('front_desk'))->toBeTrue();

    expect($room->label)->toBe('front desk')
        ->and($room->handles)->toBe(['sending_rules', 'set_replies', 'triage', 'agent'])
        ->and($room->fileTypes)->toBe([])
        ->and($room->slot)->toBe('text')
        ->and($room->slotInterface)->toBe(TextChannel::class)
        ->and($room->sections)->toBe([])
        ->and($room->tables)->toBe(['messages', 'conversations'])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([]);

    $contract = $config->roomContract('front_desk');

    expect($contract['owns'])->toBe([...SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'front desk'), 'agents/*.md'])
        ->and($contract['reads'])->toBe([
            'business/profile.md',
            'business/hours.md',
            'business/services/*.md',
            'business/faq.md',
            'voice/how-we-talk.md',
            'business/lead-sources.md',
            'business/offers/*.md',
        ])
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('text');

    $owned = $config->roomOwnedFiles('front_desk');

    expect($owned)->toContain('rules/sending.md', 'rules/replies.md', 'rules/triage.md')
        ->and($owned)->not->toContain('rules/CONTEXT.md');

    if ($empty) {
        expect($owned)->not->toContain('agents/front-desk.md');
    } else {
        expect($owned)->toContain('agents/front-desk.md');
    }

    expect($room->claimedWidgets)->toBe([TodaysConversations::class, PausedConversationsWaiting::class])
        ->and(app(RoomRegistry::class)->widgetClasses())->not->toContain(TodaysConversations::class)
        ->and(app(RoomRegistry::class)->widgetClasses())->not->toContain(PausedConversationsWaiting::class);

    $panelWidgets = Filament::getPanel('admin')->getWidgets();

    expect(array_count_values($panelWidgets)[TodaysConversations::class] ?? 0)->toBe(1)
        ->and(array_count_values($panelWidgets)[PausedConversationsWaiting::class] ?? 0)->toBe(1);

    foreach (app(RoomRegistry::class)->all() as $registered) {
        expect(in_array(NewLeads::class, $registered->claimedWidgets, true))->toBe($registered->key === 'leads');
    }

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(FrontDeskServiceProvider::class);

    expect(app(TextChannel::class))->toBeInstanceOf(FakeTextChannel::class);

    foreach (File::glob(icmPath('connectors').'/*.md') as $connector) {
        expect(File::get($connector))->not->toContain('slot: text');
    }

    $this->get('/admin')
        ->assertOk()
        ->assertSeeInOrder([
            'Drafts waiting',
            'Customers waiting while you handle it',
            'New leads',
            "Today's conversations",
        ]);
})->with([
    'empty' => [true],
    'with its example folder' => [false],
]);

/**
 * The contract's body says what the room is never without and what it writes (plain words the owner reads).
 */
it('says in its contract what it is never without and that it writes nothing to records', function () {
    $contract = File::get(icmPath('rooms/front_desk/CONTEXT.md'));

    expect($contract)
        ->toContain('It is never empty of rules/sending.md, rules/replies.md and rules/triage.md, which the sync requires.')
        ->toContain('It writes nothing to records/.');
});

/**
 * 04 D-09 with the front desk: a folder without rooms/front_desk/ is refused in one line naming the room and why, and the
 * version that was live stays live.
 */
it('refuses a folder with no front desk room in one line and keeps the previous version live', function () {
    $live = activateExampleFolder();
    File::deleteDirectory(icmPath('rooms/front_desk'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['rooms/front_desk/CONTEXT.md: the front desk room can never be left out, because it owns the sending, replies and triage files the sync requires; add rooms/front_desk/CONTEXT.md'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});
