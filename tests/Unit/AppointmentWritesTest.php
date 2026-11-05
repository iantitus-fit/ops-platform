<?php

/**
 * This is the architecture test Phase 8 D-15 names: only named actions write the appointments table, which holds both
 * appointments and blocks (A3), and no agent code can reach them. Pest's arch() can say where a class is used but not
 * whether a use writes, so a source scan reads every PHP file under app/ for the ways Laravel code writes a table. The
 * snippets below prove each rule fires, so the scan cannot pass because it never matches (the ProjectWritesTest and
 * RoomAccessibilityTest precedent). The day an agent books, cancels or marks done, the plan that adds it adds a
 * chokepoint (CLAUDE.md, the OutboundSender pattern), not another allowed file here.
 *
 * Each action plan adds its own file to the allowed list below. BookAppointment is the first.
 *
 * Unit tests do not boot Laravel, so paths come from dirname(__DIR__, 2) and plain PHP file functions. Pest functions
 * are global, so these helpers carry their own names beside projectWritesIn().
 */

/**
 * The names of the rules that find a write to the appointments table in this source.
 *
 * @return list<string>
 */
function appointmentWritesIn(string $relativePath, string $source): array
{
    $call = static fn (string $methods): string => '(?:'.$methods.')\s*\(';

    $instanceWrites = 'save|saveQuietly|update|updateQuietly|delete|deleteQuietly|forceDelete|forceDeleteQuietly|restore|forceFill|touch|increment|decrement|push';
    $model = '(?:Appointment|ScheduleBlock)';

    $rules = [
        'a static model write' => '/(?<![A-Za-z0-9_])'.$model.'::'.$call('create|forceCreate|insert|insertOrIgnore|insertGetId|upsert|updateOrCreate|firstOrCreate|createOrFirst|updateOrInsert|update|delete|forceDelete|destroy|truncate').'/',
        'a write at the end of a static chain' => '/(?<![A-Za-z0-9_])'.$model.'::\w+\s*\([^;]*->'.$call('update|updateQuietly|delete|deleteQuietly|forceDelete|forceDeleteQuietly|restore|save|saveQuietly|insert|upsert|increment|decrement|create|forceCreate|firstOrCreate|updateOrCreate|createOrFirst|touch|push').'/',
        'a new model' => '/\bnew\s+\\\\?(?:App\\\\Models\\\\)?'.$model.'\s*\(/',
        'a relation write' => '/->appointments\(\)[^;]*->'.$call('create|createMany|save|saveMany|update|delete|forceCreate|firstOrCreate|updateOrCreate|createOrFirst').'/',
        'an instance write' => '/\$(?:appointment|block)->'.$call($instanceWrites.'|fill').'/',
        'raw table access' => '/DB::table\(\s*[\'"]appointments[\'"]|\b(?:insert\s+into|update|delete\s+from)\s+[`"]?appointments\b/i',
    ];

    // A file that imports either model may hold a row under any name, so any Eloquent write in it counts.
    if (preg_match('/^use\s+App\\\\Models\\\\(?:Appointment|ScheduleBlock)\s*;/m', $source) === 1) {
        $rules['an Eloquent write in a file that imports an appointment model'] = '/->'.$call($instanceWrites).'/';
    }

    $watched = str_starts_with($relativePath, 'app/Rooms/ScheduleAndBooking/')
        || $relativePath === 'app/Livewire/BookingPage.php'
        || (str_starts_with($relativePath, 'app/Filament/')
            && (str_contains(basename($relativePath), 'Appointment') || str_contains(basename($relativePath), 'Schedule')));

    if ($watched) {
        $rules['an Eloquent write in watched code'] = '/->'.$call('save|update|delete|forceDelete|fill|forceFill|touch|create|forceCreate').'/';
        $rules['a Filament write action'] = '/\b(?:CreateAction|EditAction|DeleteAction|ForceDeleteAction|ReplicateAction|AssociateAction|DissociateAction|AttachAction|DetachAction|DeleteBulkAction|ForceDeleteBulkAction|DissociateBulkAction|DetachBulkAction)\b/';
    }

    return array_keys(array_filter($rules, static fn (string $pattern): bool => preg_match($pattern, $source) === 1));
}

/**
 * Every file under app/ that the scan finds writing appointments, as sorted paths relative to the project root.
 *
 * @return list<string>
 */
function appointmentWritersUnderApp(): array
{
    $root = dirname(__DIR__, 2);
    $writers = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = 'app/'.str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root.'/app/')));

        if (appointmentWritesIn($relativePath, (string) file_get_contents($file->getPathname())) !== []) {
            $writers[] = $relativePath;
        }
    }

    sort($writers);

    return $writers;
}

it('only the named actions write the appointments table', function () {
    // Each action plan adds its own file here: 08-06 BookAppointment; 08-13 CancelAppointment, MarkAppointmentDone,
    // EditVisitNote; 08-14 BlockTime, RemoveBlock.
    expect(appointmentWritersUnderApp())->toBe(['app/Actions/BookAppointment.php']);
});

it('flags every kind of write, so the scan is not vacuous', function (string $path, string $snippet) {
    expect(appointmentWritesIn($path, $snippet))->not->toBe([]);
})->with([
    'a static appointment create' => ['app/X.php', 'Appointment::create([]);'],
    'a static block create' => ['app/X.php', 'ScheduleBlock::create([]);'],
    'a query update' => ['app/X.php', 'Appointment::query()->whereKey(1)->update([]);'],
    'a new appointment' => ['app/X.php', '$appointment = new Appointment([]);'],
    'a new block' => ['app/X.php', '$block = new ScheduleBlock([]);'],
    'a contact relation create' => ['app/X.php', '$contact->appointments()->create([]);'],
    'a project relation update' => ['app/X.php', '$project->appointments()->update([]);'],
    'an instance update' => ['app/X.php', '$appointment->update([]);'],
    'a block delete' => ['app/X.php', '$block->delete();'],
    'a raw table insert' => ['app/X.php', "DB::table('appointments')->insert([]);"],
    'a raw sql update' => ['app/X.php', "DB::update(\"update appointments set state = 'done'\");"],
    'a Filament update on the schedule page' => ['app/Filament/Pages/Schedule.php', '$record->update([]);'],
    'a Filament delete action on the contact screen' => ['app/Filament/Resources/Contacts/RelationManagers/AppointmentsRelationManager.php', 'DeleteAction::make()'],
    'an Eloquent save in the schedule room' => ['app/Rooms/ScheduleAndBooking/AnyClass.php', '$model->save();'],
    'an Eloquent save on the booking page' => ['app/Livewire/BookingPage.php', '$model->save();'],
    'an update on an appointment held under another name' => ['app/X.php', "use App\\Models\\Appointment;\n\n\$visit = \$contact->appointments()->latest('id')->first();\n\$visit->update(['note' => null]);"],
]);

it('passes reads', function (string $snippet) {
    expect(appointmentWritesIn('app/X.php', $snippet))->toBe([]);
})->with([
    'a booked and ahead read' => ['Appointment::query()->booked()->ahead()->get();'],
    'a relation read' => ["\$contact->appointments()->orderBy('starts_at')->get();"],
    'a locked block read' => ['ScheduleBlock::query()->lockForUpdate()->find(1);'],
    'a read in a file that imports Appointment' => ["use App\\Models\\Appointment;\n\n\$visit = Appointment::query()->find(1);\n\$note = \$visit->note;"],
]);

it('passes a Filament form and an event name that only look like writes', function () {
    $path = 'app/Filament/Pages/Schedule.php';

    expect(appointmentWritesIn($path, '$action->fillForm(fn () => []); $this->created(fn () => null);'))->toBe([]);
});

// Each later action (cancel, mark done, edit the note, block, remove a block) joins this list when its plan adds it.
arch('no agent code books, cancels or marks a visit done')
    ->expect(['App\Ai', 'App\SendRule'])
    ->not->toUse(['App\Actions\BookAppointment']);
