<?php

use App\Enums\ActivityType;
use App\Icm\ActiveConfig;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Records\CustomerCard;
use App\Rooms\RecordRoomEntry;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;
use App\Rooms\RoomServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Rooms\PretendRoom\FakePretendFeed;
use Tests\Rooms\PretendRoom\PretendPing;
use Tests\Rooms\PretendRoom\ReceivePing;
use Tests\Rooms\SpecTables;

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

it('refuses a room whose morph alias would replace the app\'s own nickname', function () {
    $provider = new class(app()) extends RoomServiceProvider
    {
        protected function room(): Room
        {
            return new Room(key: 'greedy', label: 'greedy', morphMap: ['message' => PretendPing::class]);
        }
    };

    expect(fn () => $provider->boot())->toThrow(LogicException::class, 'message')
        ->and(Relation::getMorphedModel('message'))->toBe(Message::class);
});

it('refuses a room that maps a model the app already stores under another nickname', function () {
    $provider = new class(app()) extends RoomServiceProvider
    {
        protected function room(): Room
        {
            return new Room(key: 'greedy', label: 'greedy', morphMap: ['customer' => Contact::class]);
        }
    };

    expect(fn () => $provider->boot())->toThrow(LogicException::class, 'contact')
        ->and(Relation::getMorphedModel('customer'))->toBeNull()
        ->and((new Contact)->getMorphClass())->toBe('contact');
});

it('checks a room\'s nicknames after every provider has booted, so listing order does not matter', function () {
    $callbacks = [];
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('booted')->once()->andReturnUsing(function (Closure $callback) use (&$callbacks): void {
        $callbacks[] = $callback;
    });
    $provider = new class($app) extends RoomServiceProvider
    {
        protected function room(): Room
        {
            return new Room(key: 'early', label: 'early', morphMap: ['message' => PretendPing::class]);
        }
    };

    Relation::morphMap([], false);
    $provider->boot();
    (new AppServiceProvider(app()))->boot();

    expect($callbacks)->toHaveCount(1)
        ->and(fn () => $callbacks[0]())->toThrow(LogicException::class, 'message')
        ->and(Relation::getMorphedModel('message'))->toBe(Message::class);
});

it('adds the pretend room\'s morph alias only inside the test that enabled the room', function () {
    expect(Relation::getMorphedModel('pretend_ping'))->toBeNull();

    enablePretendRoom();

    expect(Relation::getMorphedModel('pretend_ping'))->toBe(PretendPing::class)
        ->and(Relation::getMorphedModel('message'))->toBe(Message::class);
});

it('does not carry the pretend room\'s morph alias into the next test', function () {
    expect(Relation::getMorphedModel('pretend_ping'))->toBeNull()
        ->and(Relation::getMorphedModel('message'))->toBe(Message::class);
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

it('stores no ping row when the timeline entry cannot be written', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();
    $contact = Contact::factory()->create();
    $this->mock(RecordRoomEntry::class)->shouldReceive('handle')->once()->andThrow(new LogicException('No such entry'));

    expect(fn () => app(ReceivePing::class)->handle($contact, 'Hello from the feed'))->toThrow(LogicException::class, 'No such entry')
        ->and(PretendPing::query()->count())->toBe(0);
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

/**
 * The room standard's boot test (ROOM-05): the pretend room boots twice, empty and with its example folder, and each
 * boot proves the file type, the table, the dashboard section, a timeline entry and the connector slot together, with
 * the seven files the sync requires kept (D-06) and the contract checked against its SPEC-shaped row (D-14). Later
 * rooms copy this shape in their own phases. A real room's boot test also runs Tests\Rooms\RoomSectionAxe on its own
 * section (Plan 04-09 adds that helper; the pretend room's own axe check lives in RoomAccessibilityTest, which runs in
 * the same CI step) (D-27). There is no database-driver guard and no skip: CI runs this on SQLite and Postgres.
 */
it('boots the pretend room', function (array $paths, int $extraTypedFiles, bool $furnished) {
    enablePretendRoom();
    layPretendRoomFolder(...$paths);

    $version = activateExampleFolder();
    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('pretend');

    expect($version->file_count)->toBe(exampleFolderFileCount() + $extraTypedFiles)
        ->and($config->hasRoom('pretend'))->toBeTrue();

    foreach (['business', 'hours', 'lead_sources', 'voice', 'sending_rules', 'set_replies', 'triage'] as $required) {
        expect($version->compiled['index'])->toHaveKey($required);
    }

    foreach ($room->tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }

    expect(app($room->slotInterface))->toBeInstanceOf(FakePretendFeed::class)
        ->and($config->roomContract('pretend')['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('tests/Fixtures/pretend-room-spec.md')), 'pretend'));

    $dashboard = $this->get('/admin')->assertOk()->assertSee('data-room-section="pretend"', false);

    if ($furnished) {
        $dashboard->assertSee('Pinned: First note')->assertDontSee('nothing set up yet');
    } else {
        $dashboard->assertSee('Pretend: nothing set up yet. Add a file to rooms/pretend/notes/ to start.');
    }

    $contact = Contact::factory()->create();
    $ping = app(ReceivePing::class)->handle($contact, 'Hello from the feed');

    expect($ping)->toBeInstanceOf(PretendPing::class)
        ->and(Activity::query()->sole()->entry)->toBe('ping_received')
        ->and(app(CustomerCard::class)->render($contact))->toContain('Pretend ping');
})->with([
    'empty' => [['rooms/pretend/CONTEXT.md'], 1, false],
    'with its example folder' => [[], 3, true],
]);

it('shows the room\'s own content once a ping has arrived and its pinned note is live', function () {
    enablePretendRoom();
    layPretendRoomFolder();
    activateExampleFolder();
    app(ReceivePing::class)->handle(Contact::factory()->create(), 'Hello from the feed');

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Pings so far: 1')
        ->assertSee('Pinned: First note');
});
