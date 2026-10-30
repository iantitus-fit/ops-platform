<?php

use App\Enums\ProjectWord;
use Illuminate\Support\Facades\File;
use Tests\Rooms\SpecTables;

/**
 * The word and its plural from each row under "What a business calls a project": the text between the backticks of
 * the first two cells.
 *
 * @return list<array{0: string, 1: string}>
 */
function projectWordRows(string $markdown): array
{
    $unticked = fn (string $cell): string => trim($cell, '` ');

    return array_map(
        fn (array $cells): array => [$unticked($cells[0]), $unticked($cells[1] ?? '')],
        SpecTables::rows($markdown, '### What a business calls a project'),
    );
}

it('lists in SPEC exactly the words in ProjectWord, with their plurals, in order', function () {
    $rows = projectWordRows(File::get(base_path('docs/SPEC.md')));

    expect($rows)->not->toBeEmpty()
        ->and($rows)->toBe(array_map(fn (ProjectWord $word): array => [$word->singular(), $word->plural()], ProjectWord::cases()));
});

it('reads only the rows under that heading', function () {
    $markdown = <<<'MD'
    ### What a business calls a project

    | Word | Plural |
    |---|---|
    | `Job` | `Jobs` |
    | `Project` | `Projects` |

    ### Other

    | `Decoy` | `Decoys` |
    MD;

    expect(projectWordRows($markdown))->toBe([['Job', 'Jobs'], ['Project', 'Projects']]);
});

it('names project_word in the business file type row', function () {
    expect(File::get(base_path('docs/SPEC.md')))
        ->toContain('| business | business/profile.md | name, phone, timezone, service_area (list), project_word');
});
