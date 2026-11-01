<?php

use App\Enums\ProjectWord;
use App\Icm\ActiveConfig;
use Illuminate\Support\Facades\File;

/*
| Each row: the word as the owner writes it, and the stored value of the case the app reads it as.
*/
dataset('written words', [
    'Job as written' => ['Job', 'job'],
    'JOB in capitals' => ['JOB', 'job'],
    'project in lower case' => ['project', 'project'],
    'matter in lower case' => ['matter', 'matter'],
    'Case as written' => ['Case', 'case'],
    'work order in lower case' => ['work order', 'work order'],
    'Work order with two spaces and capitals' => ['Work  ORDER', 'work order'],
    'Engagement as written' => ['Engagement', 'engagement'],
]);

/*
| Each row: the line that replaces "project_word: Job" (null deletes the line), the line the owner must see, and
| whether that line is the whole report line, only how it starts or only how it ends.
*/
dataset('refused words', [
    'line deleted' => [null, 'business/profile.md: project_word is missing, and it is required; write one of Job, Project, Matter, Case, Work order, Engagement', 'whole'],
    'empty text' => ['project_word: ""', 'business/profile.md: project_word is missing, and it is required; write one of Job, Project, Matter, Case, Work order, Engagement', 'whole'],
    'nothing after the colon' => ['project_word:', 'business/profile.md: project_word is missing, and it is required; write one of Job, Project, Matter, Case, Work order, Engagement', 'whole'],
    'only spaces' => ['project_word: "   "', 'business/profile.md: project_word is missing, and it is required; write one of Job, Project, Matter, Case, Work order, Engagement', 'whole'],
    'a word that is not on the list' => ['project_word: Widget', 'business/profile.md: project_word must be one of Job, Project, Matter, Case, Work order, Engagement (you wrote Widget)', 'whole'],
    'a number' => ['project_word: 5', '(you wrote 5)', 'ends'],
    'a list' => ['project_word: [Job]', '(you wrote a list)', 'ends'],
    'a Cyrillic letter standing in for J' => ["project_word: \u{0408}ob", 'business/profile.md: project_word must be one of Job, Project, Matter, Case, Work order, Engagement (you wrote', 'starts'],
    'a zero-width character inside the word' => ["project_word: Jo\u{200B}b", 'business/profile.md: project_word must be one of Job, Project, Matter, Case, Work order, Engagement (you wrote', 'starts'],
]);

it('reads the word the example business wrote', function () {
    activateExampleFolder();

    $word = app(ActiveConfig::class)->projectWord();

    expect($word->value)->toBe('job')
        ->and($word->singular())->toBe('Job')
        ->and($word->plural())->toBe('Jobs');
});

it('accepts each listed word in any letter case', function (string $written, string $expected) {
    expect(File::get(icmPath('business/profile.md')))->toContain('project_word: Job');

    breakFile('business/profile.md', fn (string $text) => str_replace('project_word: Job', "project_word: {$written}", $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->projectWord()->value)->toBe($expected);
})->with('written words');

it('refuses a missing or unlisted word and keeps the previous version live', function (?string $replacement, string $line, string $match) {
    $first = activateExampleFolder();

    breakFile('business/profile.md', fn (string $text) => $replacement === null
        ? str_replace("project_word: Job\n", '', $text)
        : str_replace('project_word: Job', $replacement, $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    $errors = rejectedErrors();

    expect($errors)->toHaveCount(1);

    match ($match) {
        'whole' => expect($errors[0])->toBe($line),
        'starts' => expect($errors[0])->toStartWith($line),
        'ends' => expect($errors[0])->toEndWith($line),
    };

    app(ActiveConfig::class)->refresh();

    expect(app(ActiveConfig::class)->versionId())->toBe($first->id)
        ->and(app(ActiveConfig::class)->projectWord()->value)->toBe('job');
})->with('refused words');

it('never throws when asked for the word', function () {
    expect(app(ActiveConfig::class)->projectWord()->value)->toBe('project');

    $version = activateExampleFolder();
    $compiled = $version->compiled;
    $path = $compiled['index']['business'];
    unset($compiled['files'][$path]['front_matter']['project_word']);
    $version->update(['compiled' => $compiled]);

    app(ActiveConfig::class)->refresh();

    expect(app(ActiveConfig::class)->projectWord()->value)->toBe('project');
});

it('lists the words in a fixed order with their plurals', function () {
    expect(array_map(fn (ProjectWord $word): string => $word->singular(), ProjectWord::cases()))
        ->toBe(['Job', 'Project', 'Matter', 'Case', 'Work order', 'Engagement'])
        ->and(array_map(fn (ProjectWord $word): string => $word->plural(), ProjectWord::cases()))
        ->toBe(['Jobs', 'Projects', 'Matters', 'Cases', 'Work orders', 'Engagements'])
        ->and(ProjectWord::choices())->toBe(['Job', 'Project', 'Matter', 'Case', 'Work order', 'Engagement']);
});

it('finds no word in a value that is not text', function () {
    expect(ProjectWord::fromProfile(null))->toBeNull()
        ->and(ProjectWord::fromProfile(5))->toBeNull()
        ->and(ProjectWord::fromProfile(['Job']))->toBeNull();
});
