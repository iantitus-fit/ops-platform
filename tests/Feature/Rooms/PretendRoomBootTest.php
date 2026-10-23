<?php

use App\Enums\ActivityType;
use App\Icm\ActiveConfig;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Rooms\PretendRoom\FakePretendFeed;
use Tests\Rooms\PretendRoom\PretendPing;
use Tests\Rooms\PretendRoom\ReceivePing;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The compiled copy of one file in the live version, or null when the sync did not keep it.
 *
 * @return array{type: ?string, front_matter: array<string, mixed>, body: string, router: bool}|null
 */
function roomBootCompiledFile(string $path): ?array
{
    return app(ActiveConfig::class)->version()->compiled['files'][$path] ?? null;
}

it('turns the pretend room on when its folder holds only the contract, from the sync to the dashboard', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');

    $version = activateExampleFolder();

    expect(roomBootCompiledFile('rooms/pretend/CONTEXT.md'))->toMatchArray(['type' => 'room', 'router' => false])
        ->and($version->file_count)->toBe(exampleFolderFileCount() + 1);

    $config = app(ActiveConfig::class);

    expect($config->hasRoom('pretend'))->toBeTrue()
        ->and($config->roomKeys())->toBe(['pretend']);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="pretend"', false)
        ->assertSee('Pretend');
});

it('draws no section for a room whose folder is not in the live version', function () {
    enablePretendRoom();

    activateExampleFolder();

    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeFalse();

    $this->get('/admin')
        ->assertOk()
        ->assertDontSee('data-room-section="pretend"', false);
});

it('turns the room on and off at each sync in the same running app', function () {
    enablePretendRoom();

    activateExampleFolder();
    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeFalse();
    $this->get('/admin')->assertOk()->assertDontSee('data-room-section="pretend"', false);

    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();
    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeTrue();
    $this->get('/admin')->assertOk()->assertSee('data-room-section="pretend"', false);

    File::deleteDirectory(icmPath('rooms/pretend'));
    activateExampleFolder();
    expect(app(ActiveConfig::class)->hasRoom('pretend'))->toBeFalse();
    $this->get('/admin')->assertOk()->assertDontSee('data-room-section="pretend"', false);
});

it('reads only a CONTEXT.md directly inside rooms/<room>/ as a room contract', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile('CONTEXT.md', 'Points at the folders.');
    writeIcmFile('rooms/CONTEXT.md', 'Points at the rooms.');
    writeIcmFile('rooms/pretend/notes/CONTEXT.md', 'Points at the notes.');

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount() + 1)
        ->and(roomBootCompiledFile('rooms/pretend/CONTEXT.md')['router'])->toBeFalse();

    foreach ([
        'CLAUDE.md',
        'START-HERE.md',
        'CONTEXT.md',
        'business/CONTEXT.md',
        'business/services/CONTEXT.md',
        'rules/CONTEXT.md',
        'rooms/CONTEXT.md',
        'rooms/pretend/notes/CONTEXT.md',
    ] as $router) {
        expect(roomBootCompiledFile($router))->toMatchArray(['type' => null, 'router' => true], $router);
    }
});

it('matches the room folder name exactly, so Rooms/pretend/CONTEXT.md stays a router', function () {
    writeIcmFile('Rooms/pretend/CONTEXT.md', File::get(base_path('tests/Fixtures/pretend-room/rooms/pretend/CONTEXT.md')));

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount())
        ->and(roomBootCompiledFile('Rooms/pretend/CONTEXT.md'))->toMatchArray(['type' => null, 'router' => true])
        ->and(app(ActiveConfig::class)->hasRoom('pretend'))->toBeFalse();
});

it('answers that no room is on before the first sync, without throwing', function () {
    $config = app(ActiveConfig::class);

    expect($config->hasRoom('pretend'))->toBeFalse()
        ->and($config->roomKeys())->toBe([]);
});

it('never lists the pretend room in bootstrap/providers.php', function () {
    expect(require base_path('bootstrap/providers.php'))->toBe([
        AppServiceProvider::class,
        AdminPanelProvider::class,
    ])
        ->and(app(RoomRegistry::class)->has('pretend'))->toBeFalse();

    enablePretendRoom();

    expect(app(RoomRegistry::class)->has('pretend'))->toBeTrue();
});

it('has the pretend room\'s table only inside the test that enabled the room', function () {
    expect(Schema::hasTable('pretend_pings'))->toBeFalse();

    enablePretendRoom();

    $tables = app(RoomRegistry::class)->get('pretend')->tables;

    expect($tables)->toBe(['pretend_pings']);

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }
});

it('sends a ping through the slot, stores it in the room\'s table and puts it on the timeline', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();
    $contact = Contact::factory()->create();

    $ping = app(ReceivePing::class)->handle($contact, 'Hello from the feed');
    $pushed = app(FakePretendFeed::class)->pushed();

    expect($ping)->toBeInstanceOf(PretendPing::class)
        ->and($pushed)->toHaveCount(1)
        ->and($pushed[0]['note'])->toBe('Hello from the feed')
        ->and($ping->feed_id)->toBe($pushed[0]['id'])
        ->and($ping->contact_id)->toBe($contact->id);

    $entry = Activity::query()->sole();

    expect($entry->type)->toBe(ActivityType::RoomEntry)
        ->and($entry->room)->toBe('pretend')
        ->and($entry->entry)->toBe('ping_received')
        ->and($entry->subject_type)->toBe('pretend_ping')
        ->and($entry->subject_id)->toBe($ping->id)
        ->and($entry->subject)->toBeInstanceOf(PretendPing::class);
});

it('does nothing with a ping while the pretend room is not in the live version', function () {
    enablePretendRoom();
    activateExampleFolder();
    $contact = Contact::factory()->create();

    $ping = app(ReceivePing::class)->handle($contact, 'Hello from the feed');

    expect($ping)->toBeNull()
        ->and(app(FakePretendFeed::class)->pushed())->toBe([])
        ->and(PretendPing::query()->count())->toBe(0)
        ->and(Activity::query()->count())->toBe(0);
});
