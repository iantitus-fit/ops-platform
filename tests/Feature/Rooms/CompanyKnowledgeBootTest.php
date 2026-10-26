<?php

use App\Icm\ActiveConfig;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\CompanyKnowledge\CompanyKnowledgeServiceProvider;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Facades\File;
use Tests\Rooms\RoomSectionAxe;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The company knowledge room's boot test (ROOM-05, ROOM-08), in the shape the pretend room set: the room boots empty and
 * with its example folder, each boot proves the registered room, its contract against SPEC's rows (04 D-14), its
 * dashboard section and the axe check on that section (A11Y-01, D-18). The room has no slot, tables or timeline
 * entries (D-05), so those proofs are that it adds none. There is no database-driver guard and no skip: CI runs this
 * on SQLite and Postgres.
 *
 * "Empty" removes the room's own files that the sync does not require (the FAQ and the four services) and the two
 * front desk loads entries that name them. The room is never shown empty, because business/profile.md,
 * business/hours.md and voice/how-we-talk.md are always there (D-03, 04 D-06).
 */
it('boots the company knowledge room', function (bool $empty) {
    if ($empty) {
        File::delete(icmPath('business/faq.md'));

        foreach (File::files(icmPath('business/services')) as $file) {
            if ($file->getFilename() !== 'CONTEXT.md' && $file->getFilename() !== 'README.md') {
                File::delete($file->getPathname());
            }
        }

        breakFile('agents/front-desk.md', fn (string $contents): string => str_replace(
            ["  - business/faq.md\n", "  - business/services/\n"],
            '',
            $contents,
        ));
    }

    $version = activateExampleFolder();
    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('company_knowledge');

    expect($version->file_count)->toBe(exampleFolderFileCount() - ($empty ? 5 : 0))
        ->and($config->hasRoom('company_knowledge'))->toBeTrue();

    foreach (['business', 'hours', 'lead_sources', 'voice', 'sending_rules', 'set_replies', 'triage'] as $required) {
        expect($version->compiled['index'])->toHaveKey($required);
    }

    expect($room->label)->toBe('company knowledge')
        ->and($room->handles)->toBe(['business', 'hours', 'service', 'knowledge', 'voice'])
        ->and($room->fileTypes)->toBe([])
        ->and($room->slot)->toBeNull()
        ->and($room->slotInterface)->toBeNull()
        ->and($room->tables)->toBe([])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([]);

    $contract = $config->roomContract('company_knowledge');

    expect($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'company knowledge'))
        ->and($contract['writes_records'])->toBe([])
        ->and($contract['connector'])->toBe('none');

    $owned = $config->roomOwnedFiles('company_knowledge');

    expect($owned)->toContain('business/profile.md', 'business/hours.md', 'voice/how-we-talk.md')
        ->and($owned)->not->toContain('business/services/CONTEXT.md');

    if (! $empty) {
        expect($owned)->toContain('business/services/ac-repair.md', 'business/services/furnace-repair.md', 'business/services/furnace-tune-up.md', 'business/services/system-replacement.md');
    }

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(CompanyKnowledgeServiceProvider::class);

    $dashboard = $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="company_knowledge"', false)
        ->assertSee('Company knowledge')
        ->assertDontSee('Company knowledge: nothing set up yet');

    if ($empty) {
        $dashboard->assertSee('Services: 0')->assertSee('FAQ: not added yet');
    } else {
        $dashboard->assertSee('Services: 4')->assertSee('FAQ: in place');
    }

    $page = visit('/admin');
    $page->assertSee($empty ? 'FAQ: not added yet' : 'FAQ: in place');

    RoomSectionAxe::assertClean(RoomSectionAxe::report($page, 'company_knowledge'));
})->with([
    'empty' => [true],
    'with its example folder' => [false],
]);

/**
 * D-04: a knowledge file the room's contract does not list is refused with a line that names the room and the file to
 * edit, so the owner knows where to add it, and the version that was live stays live.
 */
it('refuses an extra knowledge file and names the company knowledge room', function () {
    $live = activateExampleFolder();
    writeIcmFile('business/warranty.md', "---\ntype: knowledge\n---\nParts carry a one year warranty.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['business/warranty.md: no room lists this knowledge file under owns; the company knowledge room handles it, so add it to rooms/company_knowledge/CONTEXT.md'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

/**
 * IN-02: the section decides on the FAQ by its type, not by whether any file sits at business/faq.md. A service file
 * written to that path is not the FAQ, and the section must not say it is in place. A removed FAQ still reads as not added.
 */
it('says the FAQ is not added when the file at its path is not a knowledge file', function () {
    $service = str_replace('slug: ac-repair', 'slug: faq-visit', File::get(icmPath('business/services/ac-repair.md')));
    File::delete(icmPath('business/faq.md'));
    breakFile('agents/front-desk.md', fn (string $contents): string => str_replace("  - business/faq.md\n", '', $contents));
    writeIcmFile('business/faq.md', $service);

    $version = activateExampleFolder();

    expect($version->compiled['index']['knowledge'] ?? [])->toBe([]);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Services: 5')
        ->assertSee('FAQ: not added yet')
        ->assertDontSee('FAQ: in place');
});
