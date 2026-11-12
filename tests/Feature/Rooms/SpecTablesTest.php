<?php

use App\Enums\OwnableKind;
use App\Enums\PlatformKind;
use App\Icm\ActiveConfig;
use App\Icm\Loader;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Str;
use Tests\Rooms\SpecTables;

/**
 * The text of docs/SPEC.md.
 */
function specMarkdown(): string
{
    return (string) file_get_contents(base_path('docs/SPEC.md'));
}

/**
 * The owner of a pattern in SPEC's "Who owns each file" table, or null when the table has no such row.
 */
function specOwnerOf(string $pattern): ?string
{
    foreach (SpecTables::ownership(specMarkdown()) as $row) {
        if ($row['pattern'] === $pattern) {
            return $row['owner'];
        }
    }

    return null;
}

dataset('D-12 rows', [
    'business/profile.md' => ['business/profile.md', 'company knowledge'],
    'business/hours.md' => ['business/hours.md', 'company knowledge'],
    'business/services/*.md' => ['business/services/*.md', 'company knowledge'],
    'business/faq.md' => ['business/faq.md', 'company knowledge'],
    'voice/how-we-talk.md' => ['voice/how-we-talk.md', 'company knowledge'],
    'business/lead-sources.md' => ['business/lead-sources.md', 'marketing'],
    'business/offers/*.md' => ['business/offers/*.md', 'marketing'],
    'rules/sending.md' => ['rules/sending.md', 'front desk'],
    'rules/replies.md' => ['rules/replies.md', 'front desk'],
    'rules/triage.md' => ['rules/triage.md', 'front desk'],
    'agents/*.md' => ['agents/*.md', 'the room the agent serves'],
    'connectors/*.md' => ['connectors/*.md', 'the room whose slot the file fills'],
    'rooms/*/CONTEXT.md' => ['rooms/*/CONTEXT.md', 'the room whose folder it is'],
    'CLAUDE.md' => ['CLAUDE.md', 'the house'],
    'START-HERE.md' => ['START-HERE.md', 'the house'],
    '*CONTEXT.md' => ['*CONTEXT.md', 'the house'],
    'tests/*.md' => ['tests/*.md', 'the house'],
]);

it('has a row in SPEC for each file in the ownership map, with its owner', function (string $pattern, string $owner) {
    expect(specOwnerOf($pattern))->toBe($owner);
})->with('D-12 rows');

it('keeps rules/sending.md whole, owned by the front desk with the team reading owner_alert', function () {
    $rows = array_values(array_filter(
        SpecTables::rows(specMarkdown(), '### Who owns each file'),
        fn (array $cells): bool => str_contains($cells[0], '`rules/sending.md`'),
    ));

    expect($rows)->toHaveCount(1)
        ->and($rows[0][1])->toBe('front desk')
        ->and($rows[0][2])->toContain('team (reads `owner_alert`)');
});

it('names only owners from the floor plan, the house, or one of the three per-file phrases', function () {
    $owners = array_unique(array_column(SpecTables::ownership(specMarkdown()), 'owner'));

    $allowed = [
        'front desk', 'leads', 'company knowledge', 'marketing', 'content generation', 'Google Business Profile',
        'schedule and booking', 'owner intelligence', 'money', 'team',
        'the house',
        'the room the agent serves', 'the room whose slot the file fills', 'the room whose folder it is',
    ];

    expect($owners)->not->toBeEmpty()
        ->and(array_values(array_diff($owners, $allowed)))->toBe([]);
});

it('gives each row exactly one backticked pattern', function () {
    $rows = SpecTables::rows(specMarkdown(), '### Who owns each file');

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $cells) {
        expect(preg_match_all('/`[^`]+`/', $cells[0]))->toBe(1);
    }
});

it('matches every typed and router file of the example business to a row', function () {
    $patterns = array_column(SpecTables::ownership(specMarkdown()), 'pattern');
    $files = app(Loader::class)->load(base_path('examples/example-hvac'))->files;

    $unmatched = [];

    foreach ($files as $file) {
        if (! Str::is($patterns, $file->path)) {
            $unmatched[] = $file->path;
        }
    }

    expect($files)->not->toBeEmpty()
        ->and($unmatched)->toBe([]);
});

it('reads only the rows under the heading, up to the next heading', function () {
    $markdown = (string) file_get_contents(base_path('tests/Fixtures/pretend-room-spec.md'));

    expect(SpecTables::rows($markdown, '### Who owns each file'))->toBe([['`rooms/pretend/notes/*.md`', 'pretend', 'business/profile.md']])
        ->and(SpecTables::ownership($markdown))->toBe([['pattern' => 'rooms/pretend/notes/*.md', 'owner' => 'pretend']])
        ->and(SpecTables::patternsOwnedBy($markdown, 'pretend'))->toBe(['rooms/pretend/notes/*.md'])
        ->and(SpecTables::patternsOwnedBy($markdown, 'annex'))->toBe([]);
});

it('fails the comparison when a room\'s contract and the SPEC rows disagree', function () {
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();

    $owns = app(ActiveConfig::class)->file('rooms/pretend/CONTEXT.md')['front_matter']['owns'];
    $spec = (string) file_get_contents(base_path('tests/Fixtures/pretend-room-spec.md'));

    expect(SpecTables::patternsOwnedBy($spec, 'pretend'))->toBe($owns)
        ->and(SpecTables::patternsOwnedBy(str_replace('notes/*.md', 'files/*.md', $spec), 'pretend'))->not->toBe($owns);
});

it('links BLUEPRINT to the SPEC table instead of copying it', function () {
    $blueprint = (string) file_get_contents(base_path('docs/BLUEPRINT.md'));

    expect($blueprint)->toContain('SPEC.md#who-owns-each-file')
        ->and($blueprint)->not->toContain('| File | Owner |')
        ->and($blueprint)->not->toContain('| `business/faq.md` |');
});

it('reads the kinds of record from the two kind tables, and only the rows under their headings', function () {
    $markdown = <<<'MD'
    ### Kinds of record a connector may own

    | Key | Kind of record |
    |---|---|
    | `customers` | Customers |
    | `sales_activity` | Sales activity |

    ### Kinds of record the platform owns

    | Key | Kind of record |
    |---|---|
    | `projects` | Projects |

    ### Something else

    | `decoy` | Not a kind |
    MD;

    expect(SpecTables::ownableKinds($markdown))->toBe(['customers', 'sales_activity'])
        ->and(SpecTables::platformKinds($markdown))->toBe(['projects']);
});

it('lists in SPEC exactly the kinds a connector may own, in the order of OwnableKind', function () {
    expect(SpecTables::ownableKinds(specMarkdown()))->toBe(array_map(fn (OwnableKind $kind): string => $kind->value, OwnableKind::cases()))
        ->and(SpecTables::ownableKinds(specMarkdown()))->not->toBeEmpty();
});

it('lists in SPEC exactly the kinds the platform owns, in the order of PlatformKind', function () {
    expect(SpecTables::platformKinds(specMarkdown()))->toBe(array_map(fn (PlatformKind $kind): string => $kind->value, PlatformKind::cases()))
        ->and(SpecTables::platformKinds(specMarkdown()))->not->toBeEmpty();
});

it('never puts one kind of record on both lists', function () {
    expect(SpecTables::ownableKinds(specMarkdown()))->not->toBeEmpty()
        ->and(SpecTables::platformKinds(specMarkdown()))->not->toBeEmpty()
        ->and(array_intersect(SpecTables::ownableKinds(specMarkdown()), SpecTables::platformKinds(specMarkdown())))->toBe([]);
});

it('reads the File column of the file types table, and only the rows under its heading', function () {
    $markdown = <<<'MD'
    ### File types (the front matter contract)

    | type | File | Required front matter |
    |---|---|---|
    | business | business/profile.md | name |
    | money_notes | rooms/money/notes.md | none beyond type |

    ### Something else

    | decoy | decoy.md | not a type |
    MD;

    expect(SpecTables::fileTypeFiles($markdown))->toBe(['business' => 'business/profile.md', 'money_notes' => 'rooms/money/notes.md']);
});

/**
 * D-29: a single-file type declares its default path, and the SPEC file types table says the same path, so the code and
 * the document cannot drift. A type that keeps any number of files declares no default path.
 */
it('declares for each single-file type the default path that SPEC gives it', function () {
    $specFiles = SpecTables::fileTypeFiles(specMarkdown());
    $single = [];

    foreach (app(RoomRegistry::class)->all() as $room) {
        foreach ($room->fileTypes as $fileType) {
            expect($fileType->singleFileDefaultPath() !== null)->toBe($fileType->singleFileLabel() !== null);

            if ($fileType->singleFileDefaultPath() !== null) {
                $single[$fileType->type()] = $fileType->singleFileDefaultPath();
            }
        }
    }

    ksort($single);

    expect($single)->toBe([
        'booking' => $specFiles['booking'],
        'content_brief' => $specFiles['content_brief'],
        'gbp_categories' => $specFiles['gbp_categories'],
        'money_notes' => $specFiles['money_notes'],
    ]);
});
