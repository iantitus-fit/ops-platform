<?php

/**
 * This is the architecture test Phase 7 D-11 names: only OpenProject, CloseProject, ReopenProject and RenameProject
 * write the projects table, and no agent code can reach them. Pest's arch() can say where a class is used but not
 * whether a use writes, so a source scan reads every PHP file under app/ for the ways Laravel code writes a table. The
 * snippets below prove each rule fires, so the scan cannot pass because it never matches (the RoomAccessibilityTest
 * precedent). The day an agent opens, closes, reopens or renames a project, the plan that adds it adds a chokepoint
 * (CLAUDE.md, the OutboundSender pattern), not another allowed file here.
 *
 * Unit tests do not boot Laravel, so paths come from dirname(__DIR__, 2) and plain PHP file functions.
 */

/**
 * The names of the rules that find a write to the projects table in this source.
 *
 * @return list<string>
 */
function projectWritesIn(string $relativePath, string $source): array
{
    return [];
}

/**
 * Every file under app/ that the scan finds writing projects, as sorted paths relative to the project root.
 *
 * @return list<string>
 */
function projectWritersUnderApp(): array
{
    $root = dirname(__DIR__, 2);
    $writers = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = 'app/'.str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root.'/app/')));

        if (projectWritesIn($relativePath, (string) file_get_contents($file->getPathname())) !== []) {
            $writers[] = $relativePath;
        }
    }

    sort($writers);

    return $writers;
}

it('only the four project actions write the projects table', function () {
    expect(projectWritersUnderApp())->toBe([
        'app/Actions/CloseProject.php',
        'app/Actions/OpenProject.php',
        'app/Actions/RenameProject.php',
        'app/Actions/ReopenProject.php',
    ]);
});

it('flags every kind of write, so the scan is not vacuous', function (string $path, string $snippet) {
    expect(projectWritesIn($path, $snippet))->not->toBe([]);
})->with([
    'a static create' => ['app/X.php', 'Project::create([]);'],
    'a query update' => ['app/X.php', 'Project::query()->whereKey(1)->update([]);'],
    'a new model' => ['app/X.php', '$project = new Project([]);'],
    'a relation create' => ['app/X.php', '$contact->projects()->create([]);'],
    'an instance update' => ['app/X.php', '$project->update([]);'],
    'a raw table insert' => ['app/X.php', "DB::table('projects')->insert([]);"],
    'a raw sql delete' => ['app/X.php', 'DB::delete("delete from projects where id = 1");'],
    'a Filament update on the contact screen' => ['app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php', '$record->update([]);'],
    'a Filament delete action on the contact screen' => ['app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php', 'DeleteAction::make()'],
    'an Eloquent save in the leads room' => ['app/Rooms/Leads/AnyClass.php', '$model->save();'],
]);

it('passes reads', function (string $snippet) {
    expect(projectWritesIn('app/X.php', $snippet))->toBe([]);
})->with([
    'a numbered read' => ["Project::query()->withNumber()->whereNull('closed_at')->get();"],
    'a relation read' => ["\$contact->projects()->orderBy('id')->get();"],
    'a locked read' => ['Project::query()->lockForUpdate()->find(1);'],
]);

it('passes a Filament form and an event name that only look like writes', function () {
    $path = 'app/Filament/Resources/Contacts/RelationManagers/ProjectsRelationManager.php';

    expect(projectWritesIn($path, '$action->fillForm(fn () => []); $this->created(fn () => null);'))->toBe([]);
});

arch('only the contact screen and the leads room call the four project actions')
    ->expect([
        'App\Actions\OpenProject',
        'App\Actions\CloseProject',
        'App\Actions\ReopenProject',
        'App\Actions\RenameProject',
    ])
    ->toOnlyBeUsedIn([
        'App\Actions',
        'App\Filament\Resources\Contacts\RelationManagers',
        'App\Rooms\Leads',
        'Tests',
    ]);
