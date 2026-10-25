<?php

use App\Icm\ActiveConfig;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\ContentGeneration\ContentBriefType;
use App\Rooms\ContentGeneration\ContentGenerationServiceProvider;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;
use Tests\Rooms\RoomSectionAxe;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The content generation room's boot test (ROOM-05, ROOM-10), in the shape the pretend room and the first two real rooms
 * set: the room boots empty and with its example folder, each boot proves the registered room, its own file type, its
 * contract against SPEC's rows (04 D-14), its dashboard section and the axe check on that section (A11Y-01, D-18). The
 * room has no slot, tables or timeline entries (D-05), so those proofs are that it adds none. There is no database-driver
 * guard and no skip: CI runs this on SQLite and Postgres.
 *
 * "Empty" removes the one content brief. The room is then a valid empty room, and its section says which file to add
 * (research Pattern 3: the contract owns the exact path, so the empty line names it).
 */
it('boots the content generation room', function (bool $empty) {
    if ($empty) {
        File::delete(icmPath('rooms/content_generation/brief.md'));
    }

    $version = activateExampleFolder();
    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('content_generation');

    expect($version->file_count)->toBe(exampleFolderFileCount() - ($empty ? 1 : 0))
        ->and($config->hasRoom('content_generation'))->toBeTrue();

    expect($room->label)->toBe('content generation')
        ->and($room->handles)->toBe([])
        ->and($room->fileTypes)->toHaveCount(1)
        ->and($room->fileTypes[0])->toBeInstanceOf(ContentBriefType::class)
        ->and($room->fileTypes[0]->type())->toBe('content_brief')
        ->and($room->slot)->toBeNull()
        ->and($room->slotInterface)->toBeNull()
        ->and($room->tables)->toBe([])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([]);

    $contract = $config->roomContract('content_generation');

    expect($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'content generation'))
        ->and($contract['owns'])->toBe(['rooms/content_generation/brief.md'])
        ->and($contract['reads'])->toBe([
            'voice/how-we-talk.md',
            'business/profile.md',
            'business/services/*.md',
            'business/offers/*.md',
            'business/hours.md',
            'business/faq.md',
        ])
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('none');

    $briefs = $config->filesOfType('content_brief');

    if ($empty) {
        expect($briefs)->toBe([]);
    } else {
        expect($briefs)->toHaveCount(1)
            ->and($briefs[0]['path'])->toBe('rooms/content_generation/brief.md');
    }

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(ContentGenerationServiceProvider::class);

    $dashboard = $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="content_generation"', false)
        ->assertSee('Content generation');

    if ($empty) {
        $dashboard->assertSee('Content generation: nothing set up yet. Add rooms/content_generation/brief.md to start.')
            ->assertDontSee('Content brief: in place');
    } else {
        $dashboard->assertSee('Content brief: in place')
            ->assertDontSee('Content generation: nothing set up yet');
    }

    $page = visit('/admin');
    $page->assertSee($empty ? 'nothing set up yet' : 'Content brief: in place');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'content_generation'));
})->with([
    'empty' => [true],
    'with its example folder' => [false],
]);

/**
 * The line a second content brief earns (D-27), whatever the contract's owns says: it names the file the business keeps.
 */
function secondBriefRefusal(string $extra): string
{
    return $extra.': this business already has a content brief in rooms/content_generation/brief.md; keep one';
}

/**
 * D-27 under the shipped contract: a second brief in the room's folder is refused in one line naming both files, and the
 * version that was live stays live.
 */
it('refuses a second content brief under the shipped contract', function () {
    $live = activateExampleFolder();
    writeIcmFile('rooms/content_generation/second.md', "---\ntype: content_brief\n---\nAnother brief.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([secondBriefRefusal('rooms/content_generation/second.md')])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * D-27 under a contract the owner edited to own a second path: owning the file does not let a business keep two briefs.
 */
it('refuses a second content brief under a contract edited to own two paths', function () {
    $live = activateExampleFolder();

    breakFile('rooms/content_generation/CONTEXT.md', fn (string $contents): string => str_replace(
        'owns: [rooms/content_generation/brief.md]',
        'owns: [rooms/content_generation/brief.md, rooms/content_generation/second.md]',
        $contents,
    ));
    writeIcmFile('rooms/content_generation/second.md', "---\ntype: content_brief\n---\nAnother brief.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([secondBriefRefusal('rooms/content_generation/second.md')])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * D-27 under a contract the owner edited to own every file in the folder: the first brief in path order is kept.
 */
it('refuses a second content brief under a contract edited to own a pattern', function () {
    $live = activateExampleFolder();

    breakFile('rooms/content_generation/CONTEXT.md', fn (string $contents): string => str_replace(
        'owns: [rooms/content_generation/brief.md]',
        'owns: [rooms/content_generation/*.md]',
        $contents,
    ));
    writeIcmFile('rooms/content_generation/older.md', "---\ntype: content_brief\n---\nAn older brief.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([secondBriefRefusal('rooms/content_generation/older.md')])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * D-27: owning two paths is fine while only one brief exists, so the contract can be edited ahead of the file.
 */
it('accepts a contract that owns two paths while only one brief is there', function () {
    activateExampleFolder();

    breakFile('rooms/content_generation/CONTEXT.md', fn (string $contents): string => str_replace(
        'owns: [rooms/content_generation/brief.md]',
        'owns: [rooms/content_generation/brief.md, rooms/content_generation/second.md]',
        $contents,
    ));

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->filesOfType('content_brief'))->toHaveCount(1);
});
