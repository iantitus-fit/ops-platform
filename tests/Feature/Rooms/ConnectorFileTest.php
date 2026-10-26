<?php

use App\Icm\ActiveConfig;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;
use Tests\Rooms\PretendRoom\AnnexRoom;
use Tests\Rooms\PretendRoom\PretendFeed;

const PRETEND_CONNECTOR_PATH = 'connectors/pretend-feed.md';

/**
 * The pretend room's connector file as the fixture lays it, with one front matter line replaced by $line, or removed
 * when $line is null. Only the first line starting with "$field:" changes.
 */
function pretendConnectorWith(string $field, ?string $line): string
{
    $connector = File::get(base_path('tests/Fixtures/pretend-room/'.PRETEND_CONNECTOR_PATH));

    return (string) preg_replace('/^'.preg_quote($field, '/').':.*\R/m', $line === null ? '' : $line."\n", $connector, 1);
}

/**
 * The pretend room's connector file with its owns and reads lines both replaced.
 */
function pretendConnectorWithLists(string $owns, string $reads): string
{
    $connector = pretendConnectorWith('owns', null);

    return (string) preg_replace('/^reads:.*\R/m', $owns."\n".$reads."\n", $connector, 1);
}

/**
 * The compiled front matter of the pretend connector file in the live version.
 *
 * @return array<string, mixed>
 */
function pretendConnectorCompiledFrontMatter(): array
{
    return app(ActiveConfig::class)->version()->compiled['files'][PRETEND_CONNECTOR_PATH]['front_matter'] ?? [];
}

/**
 * The connector files the live version should list: the ones examples/example-hvac ships plus the ones a test laid,
 * sorted the way the sync sorts them.
 *
 * @param  list<string>  $laid
 * @return list<string>
 */
function expectedConnectorPaths(array $laid): array
{
    $paths = [...exampleConnectorPaths(), ...$laid];
    sort($paths, SORT_STRING);

    return $paths;
}

/**
 * The sentence's list of connector slots, built from the registry the way the refusal builds it.
 */
function connectorSlotsSentence(): string
{
    return implode(', ', app(RoomRegistry::class)->slots());
}

it('checks one connector file and compiles it into the live version', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', PRETEND_CONNECTOR_PATH);

    $version = activateExampleFolder();

    expect($version->file_count)->toBe(exampleFolderFileCount() + 2)
        ->and($version->notices)->toBe([])
        ->and($version->compiled['index']['connector'] ?? null)->toBe(expectedConnectorPaths([PRETEND_CONNECTOR_PATH]));
});

dataset('connector fields the sync requires', [
    'slot deleted' => ['slot', 'connectors/pretend-feed.md: slot is missing, and it is required'],
    'owns deleted' => ['owns', 'connectors/pretend-feed.md: owns is missing, and it is required'],
    'reads deleted' => ['reads', 'connectors/pretend-feed.md: reads is missing, and it is required'],
]);

it('refuses a connector file with slot, owns or reads missing, naming the file and the field, and keeps the previous version live', function (string $field, string $line) {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    $live = activateExampleFolder();
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith($field, null));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
})->with('connector fields the sync requires');

dataset('connector fields the sync passes through', [
    'system deleted' => ['system', null],
    'direction deleted' => ['direction', null],
    'schedule deleted' => ['schedule', null],
    'direction anything at all' => ['direction', 'direction: sideways'],
    'schedule anything at all' => ['schedule', 'schedule: when the moon is full'],
    'system anything at all' => ['system', 'system: 42'],
]);

it('passes system, direction and schedule through unchecked until CONN-01', function (string $field, ?string $line) {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith($field, $line));

    $version = activateExampleFolder();

    expect($version->compiled['index']['connector'] ?? null)->toBe(expectedConnectorPaths([PRETEND_CONNECTOR_PATH]));
})->with('connector fields the sync passes through');

dataset('connector lists written as none or as an empty list', [
    'owns none' => ['owns', 'owns: none'],
    'owns empty list' => ['owns', 'owns: []'],
    'reads none' => ['reads', 'reads: none'],
    'reads empty list' => ['reads', 'reads: []'],
]);

it('reads none and [] as the same empty list in owns and reads, as the room contract does', function (string $field, string $line) {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith($field, $line));

    activateExampleFolder();

    expect(pretendConnectorCompiledFrontMatter()[$field] ?? 'not compiled')->toBe([]);
})->with('connector lists written as none or as an empty list');

it('refuses owns written as a word that is not a list', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith('owns', 'owns: customers'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/pretend-feed.md: owns must be a list (you wrote customers)']);
});

const OWNABLE_KINDS_SENTENCE = 'customers, sales_activity, invoices, payments, expenses, work_orders, schedule, documents';

dataset('connector kinds the sync refuses', [
    'texts owned' => ['owns: [texts]', 'reads: []', 'connectors/pretend-feed.md: owns lists texts, which the platform owns; a connector can read it but never own it'],
    'projects owned' => ['owns: [projects]', 'reads: []', 'connectors/pretend-feed.md: owns lists projects, which the platform owns; a connector can read it but never own it'],
    'timeline owned' => ['owns: [timeline]', 'reads: []', 'connectors/pretend-feed.md: owns lists timeline, which the platform owns; a connector can read it but never own it'],
    'conversations owned' => ['owns: [conversations]', 'reads: []', 'connectors/pretend-feed.md: owns has conversations, which is not a kind of record; the kinds are: '.OWNABLE_KINDS_SENTENCE],
    'none as a one-entry list owned' => ['owns: [none]', 'reads: []', 'connectors/pretend-feed.md: owns has none, which is not a kind of record; the kinds are: customers, sales_activity, invoices, payments, expenses, work_orders, schedule, documents'],
    'drafts read' => ['owns: []', 'reads: [drafts]', 'connectors/pretend-feed.md: reads has drafts, which is not a kind of record; the kinds are: '.OWNABLE_KINDS_SENTENCE.', projects, texts, timeline'],
    'a kind owned twice' => ['owns: [customers, customers]', 'reads: []', 'connectors/pretend-feed.md: owns lists customers twice'],
    'a kind read twice' => ['owns: []', 'reads: [customers, customers]', 'connectors/pretend-feed.md: reads lists customers twice'],
    'a kind in both lists' => ['owns: [customers]', 'reads: [customers]', 'connectors/pretend-feed.md: customers is in both owns and reads; a system that owns a kind already reads it, so list it only under owns'],
    'a kind with a line break in it' => ['owns: ["customers\nand more"]', 'reads: []', 'connectors/pretend-feed.md: owns has customers and more, which is not a kind of record; the kinds are: '.OWNABLE_KINDS_SENTENCE],
    'an entry that is not text' => ['owns: [1]', 'reads: []', 'connectors/pretend-feed.md: owns item 1 must be text; wrap it in quotes (you wrote 1)'],
]);

it('refuses a kind in owns or reads that is not allowed there, in plain words, with one line each', function (string $owns, string $reads, string $line) {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    $live = activateExampleFolder();
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWithLists($owns, $reads));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
})->with('connector kinds the sync refuses');

it('accepts a kind the platform keeps no records of yet, and reading the platform\'s own kinds', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWithLists('owns: [invoices]', 'reads: [projects, texts, timeline]'));

    $version = activateExampleFolder();

    expect(pretendConnectorCompiledFrontMatter())->toMatchArray(['owns' => ['invoices'], 'reads' => ['projects', 'texts', 'timeline']])
        ->and($version->compiled['index']['connector'])->toBe(expectedConnectorPaths([PRETEND_CONNECTOR_PATH]));
});

it('refuses a connector file that still says objects, telling the owner to use owns and reads', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, (string) preg_replace('/^schedule:.*\R/m', "schedule: hourly\nobjects: [customers]\n", pretendConnectorWith('schedule', 'schedule: hourly'), 1));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/pretend-feed.md: objects is replaced by owns and reads: list under owns the kinds this system is the truth for, and under reads the kinds it only copies']);
});

/**
 * Switch the pretend and annex rooms on and lay both rooms' contracts, so each connector slot has a room that is here.
 */
function layTwoRoomsWithSlots(): void
{
    enablePretendRoom();
    app(RoomRegistry::class)->add(AnnexRoom::room());
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    layFixtureFolder('annex-room');
}

/**
 * A connector file for the tests that write their own: the pretend connector with its slot, system, owns and reads
 * replaced.
 */
function connectorFor(string $slot, string $owns = 'owns: []', string $reads = 'reads: []', string $system = 'Another Pretend System'): string
{
    $connector = (string) preg_replace('/^slot:.*\R/m', "slot: {$slot}\n", pretendConnectorWithLists($owns, $reads), 1);

    return (string) preg_replace('/^system:.*\R/m', "system: {$system}\n", $connector, 1);
}

it('refuses two connector files that own the same kind with the exact sentence, and keeps the previous version live', function () {
    layTwoRoomsWithSlots();
    $live = activateExampleFolder();
    layFixtureFolder('connector-conflict');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/b.md: customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('blames each later file that owns a kind an earlier one owns', function () {
    layTwoRoomsWithSlots();
    app(RoomRegistry::class)->add(new Room(key: 'third', label: 'third', slot: 'third_feed', slotInterface: PretendFeed::class));
    writeIcmFile('rooms/third/CONTEXT.md', "---\ntype: room\nowns: []\nreads: []\nwrites_records: []\nconnector: third_feed\n---\nA third room that exists only in this test.\n");
    layFixtureFolder('connector-conflict');
    writeIcmFile('connectors/c.md', connectorFor('third_feed', 'owns: [customers]'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([
        'connectors/b.md: customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it',
        'connectors/c.md: customers is owned by both connectors/a.md and connectors/c.md; pick one, the other can read it',
    ]);
});

it('lets two connector files own different kinds, and one read what the other owns', function () {
    layTwoRoomsWithSlots();
    writeIcmFile('connectors/a.md', connectorFor('pretend_feed', 'owns: [customers]', 'reads: [documents]'));
    writeIcmFile('connectors/b.md', connectorFor('annex_feed', 'owns: [documents]', 'reads: [customers]'));

    $version = activateExampleFolder();

    expect($version->compiled['index']['connector'])->toBe(expectedConnectorPaths(['connectors/a.md', 'connectors/b.md']));
});

it('does not count a connector file whose owns already failed the kind check as an owner', function () {
    layTwoRoomsWithSlots();
    writeIcmFile('connectors/a.md', connectorFor('pretend_feed', 'owns: [customers]'));
    writeIcmFile('connectors/b.md', connectorFor('annex_feed', 'owns: [customers, texts]'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/b.md: owns lists texts, which the platform owns; a connector can read it but never own it']);
});

it('refuses a slot that no room has, naming the slots that exist', function () {
    layTwoRoomsWithSlots();
    writeIcmFile('connectors/x.md', connectorFor('nowhere'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/x.md: this file fills the nowhere slot, which no room has; the slots are: '.connectorSlotsSentence()])
        ->and(app(RoomRegistry::class)->slots())->toContain('pretend_feed', 'annex_feed');
});

it('treats slot: none as a slot name no room has, not as an empty value', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith('slot', 'slot: none'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/pretend-feed.md: this file fills the none slot, which no room has; the slots are: '.connectorSlotsSentence()])
        ->and(app(RoomRegistry::class)->slots())->toContain('pretend_feed');
});

it('refuses a connector file for a slot whose room is not in the folder', function () {
    enablePretendRoom();
    layPretendRoomFolder(PRETEND_CONNECTOR_PATH);

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/pretend-feed.md: this file fills the pretend_feed slot, but the pretend room is not in this folder']);
});

it('refuses a second connector file filling a slot that is already filled', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md', PRETEND_CONNECTOR_PATH);
    writeIcmFile('connectors/z.md', connectorFor('pretend_feed'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/z.md: the pretend_feed slot is already filled by connectors/pretend-feed.md; a slot takes one connector file']);
});

it('leaves a room with a slot and no connector file as a valid empty room', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');

    $version = activateExampleFolder();

    expect($version->compiled['index']['connector'])->toBe(expectedConnectorPaths([]))
        ->and(app(ActiveConfig::class)->hasRoom('pretend'))->toBeTrue();
});

it('shows an owner-written slot on one line', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile(PRETEND_CONNECTOR_PATH, pretendConnectorWith('slot', 'slot: "line one\nline two"'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['connectors/pretend-feed.md: this file fills the line one line two slot, which no room has; the slots are: '.connectorSlotsSentence()])
        ->and(app(RoomRegistry::class)->slots())->toContain('pretend_feed');
});

it('refuses a connector file outside connectors/, naming the file, and keeps the previous version live', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    $live = activateExampleFolder();
    writeIcmFile('notes/pretend-feed.md', pretendConnectorWith('slot', 'slot: pretend_feed'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toContain('notes/pretend-feed.md: type connector belongs only in connectors/, the folder for connector files')
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('does not let a misplaced connector file take the slot or the kind from the one in connectors/', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    writeIcmFile('a/crm.md', connectorFor('pretend_feed', 'owns: [customers]'));
    writeIcmFile('connectors/crm.md', connectorFor('pretend_feed', 'owns: [customers]'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['a/crm.md: type connector belongs only in connectors/, the folder for connector files']);
});
