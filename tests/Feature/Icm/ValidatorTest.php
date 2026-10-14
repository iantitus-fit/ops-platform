<?php

use App\Enums\SyncOutcome;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Icm\FolderSync;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

/*
| Each row: file to change, text to find, text to put in its place, and the exact line the owner must see.
*/
dataset('broken fields', [
    'business: timezone deleted' => ['business/profile.md', "timezone: America/Chicago\n", '', 'business/profile.md: timezone is missing, and it is required'],
    'business: phone without quotes' => ['business/profile.md', 'phone: "+13125550147"', 'phone: +13125550147', 'business/profile.md: phone must be text; wrap it in quotes (you wrote 13125550147)'],
    'business: timezone that is not a zone' => ['business/profile.md', 'timezone: America/Chicago', 'timezone: Chicago', 'business/profile.md: timezone must be a time zone such as America/Chicago (you wrote Chicago)'],
    'hours: lead minutes in quotes' => ['business/hours.md', 'booking_lead_minutes: 120', 'booking_lead_minutes: "90"', 'business/hours.md: booking_lead_minutes must be a whole number (you wrote "90")'],
    'hours: opening time without a leading zero' => ['business/hours.md', 'mon: {open: "08:00"', 'mon: {open: "8:00"', 'business/hours.md: weekly mon: open must be a 24-hour time such as 08:00 (you wrote 8:00)'],
    'hours: a day that is neither closed nor times' => ['business/hours.md', 'tue: {open: "08:00", close: "17:00"}', 'tue: sometimes', 'business/hours.md: weekly tue must be closed or have open and close times (you wrote sometimes)'],
    'hours: closing before opening' => ['business/hours.md', 'sat: {open: "09:00", close: "13:00"}', 'sat: {open: "13:00", close: "09:00"}', 'business/hours.md: weekly sat: close must be later than open (you wrote 13:00 to 09:00)'],
    'hours: a day deleted' => ['business/hours.md', "  sun: closed\n", '', 'business/hours.md: weekly sun is missing, and it is required'],
    'hours: closure that is not a date' => ['business/hours.md', 'closures: ["2026-11-26", "2026-12-25"]', 'closures: ["Dec 25"]', 'business/hours.md: closures item 1 must be a date such as 2026-12-25 (you wrote Dec 25)'],
    'lead sources: cost in quotes' => ['business/lead-sources.md', 'cost_cents_per_lead: 3200', 'cost_cents_per_lead: "32.50"', 'business/lead-sources.md: sources item 2: cost_cents_per_lead must be a whole number (you wrote "32.50")'],
    'lead sources: cost too large for the database' => ['business/lead-sources.md', 'cost_cents_per_lead: 3200', 'cost_cents_per_lead: 3000000000', 'business/lead-sources.md: sources item 2: cost_cents_per_lead must be 100000000 or less (you wrote 3000000000)'],
    'lead sources: the same key twice' => ['business/lead-sources.md', 'key: thumbtack', 'key: angi', 'business/lead-sources.md: sources has more than one entry with key angi'],
    'service: yes instead of true' => ['business/services/ac-repair.md', 'book_online: true', 'book_online: yes', 'business/services/ac-repair.md: book_online must be true or false (you wrote yes)'],
    'service: price deleted' => ['business/services/ac-repair.md', "price_cents: 8900\n", '', 'business/services/ac-repair.md: price_cents is missing; it is required unless price_policy is none'],
    'service: price too large for the database' => ['business/services/ac-repair.md', 'price_cents: 8900', 'price_cents: 3000000000', 'business/services/ac-repair.md: price_cents must be 100000000 or less (you wrote 3000000000)'],
    'service: slug with capitals and a space' => ['business/services/ac-repair.md', 'slug: ac-repair', 'slug: AC Repair', 'business/services/ac-repair.md: slug must use only lowercase letters, numbers, dashes or underscores (you wrote AC Repair)'],
    'service: the same intake key twice' => ['business/services/ac-repair.md', 'key: system_age', 'key: symptom', 'business/services/ac-repair.md: intake has more than one question with key symptom'],
    'voice: never_say deleted' => ['voice/how-we-talk.md', "never_say: [guaranteed, cheapest, no problem]\n", '', 'voice/how-we-talk.md: never_say is missing, and it is required'],
    'sending rules: owner_alert deleted' => ['rules/sending.md', "owner_alert:\n  channels: [text]\n  right_away: [complaint, asked_for_person, life_safety, paused_message, opted_out_message, front_desk_failed, reply_cap, check_in]\n  after_hours: next_opening\n", '', 'rules/sending.md: owner_alert is missing, and it is required'],
]);

it('rejects a broken field with a sentence that names the file, the field and what was written', function (string $file, string $search, string $replace, string $line) {
    expect(File::get(icmPath($file)))->toContain($search);

    breakFile($file, fn (string $text) => str_replace($search, $replace, $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toContain($line)
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(0);
})->with('broken fields');

it('rejects a second service file that uses the same slug and names the file that has it first', function () {
    writeIcmFile('business/services/zz-copy.md', File::get(icmPath('business/services/ac-repair.md')));

    $this->artisan('icm:sync')
        ->expectsOutputToContain('business/services/zz-copy.md: slug ac-repair is already used by business/services/ac-repair.md')
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe(['business/services/zz-copy.md: slug ac-repair is already used by business/services/ac-repair.md']);
});

it('rejects a file whose type or front matter cannot be understood', function (string $file, string $contents, string $line) {
    writeIcmFile($file, $contents);

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line]);
})->with([
    'a type nobody knows' => ['notes/recipe.md', "---\ntype: recipe\n---\nBody", 'notes/recipe.md: type must be one of business, hours, lead_sources, knowledge, service, voice, sending_rules, agent, set_replies, triage (you wrote recipe)'],
    'front matter without a type' => ['notes/x.md', "---\nname: Notes\n---\nBody", 'notes/x.md: type is missing, and it is required'],
    'no front matter at all' => ['notes/y.md', 'Just some words.', 'notes/y.md: the file must start with front matter (a --- line, then type: and its fields, then another --- line)'],
    'empty front matter' => ['notes/z.md', "---\n---\nBody", 'notes/z.md: the front matter is empty; add type: and the fields for that type'],
]);

it('names the file and the file line when the YAML itself cannot be read', function () {
    breakFile('business/hours.md', fn (string $text) => str_replace('  mon:', "\tmon:", $text));

    $tabLine = collect(explode("\n", File::get(icmPath('business/hours.md'))))
        ->search(fn (string $text) => str_starts_with($text, "\tmon:")) + 1;

    $this->artisan('icm:sync')->assertExitCode(1);

    $errors = rejectedErrors();

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toStartWith("business/hours.md: the front matter could not be read at line {$tabLine}");
});

it('requires each of the five single-instance types to exist exactly once', function () {
    File::delete(icmPath('business/hours.md'));
    File::copy(icmPath('business/profile.md'), icmPath('business/profile-old.md'));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([
        'agents/front-desk.md: loads item 2: business/hours.md matches no file in the folder',
        'business/hours.md: no file has type: hours, and the folder needs exactly one',
        'business/profile-old.md: type business is already used by business/profile.md; the folder needs exactly one',
    ]);
});

it('names every missing single-instance type when the folder has no typed files', function () {
    foreach (['business/profile.md', 'business/hours.md', 'business/lead-sources.md', 'business/faq.md', 'business/services/ac-repair.md', 'business/services/furnace-repair.md', 'business/services/furnace-tune-up.md', 'voice/how-we-talk.md', 'rules/sending.md'] as $file) {
        File::delete(icmPath($file));
    }

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([
        'agents/front-desk.md: loads item 1: business/profile.md matches no file in the folder',
        'agents/front-desk.md: loads item 2: business/hours.md matches no file in the folder',
        'agents/front-desk.md: loads item 3: business/faq.md matches no file in the folder',
        'agents/front-desk.md: loads item 4: business/services/ matches no file in the folder',
        'agents/front-desk.md: loads item 5: voice/how-we-talk.md matches no file in the folder',
        'agents/front-desk.md: loads item 6: rules/sending.md matches no file in the folder',
        'business/hours.md: no file has type: hours, and the folder needs exactly one',
        'business/lead-sources.md: no file has type: lead_sources, and the folder needs exactly one',
        'business/profile.md: no file has type: business, and the folder needs exactly one',
        'rules/sending.md: no file has type: sending_rules, and the folder needs exactly one',
        'voice/how-we-talk.md: no file has type: voice, and the folder needs exactly one',
    ]);
});

it('reports every error in the folder grouped by file in path order, not just the first', function () {
    breakFile('voice/how-we-talk.md', fn (string $text) => str_replace("never_say: [guaranteed, cheapest, no problem]\n", '', $text));
    breakFile('business/profile.md', fn (string $text) => str_replace("timezone: America/Chicago\n", '', $text));
    breakFile('business/profile.md', fn (string $text) => str_replace("name: Example Heating & Air\n", '', $text));
    breakFile('business/services/ac-repair.md', fn (string $text) => str_replace('book_online: true', 'book_online: yes', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    $errors = rejectedErrors();

    expect($errors)->toBe([
        'business/profile.md: name is missing, and it is required',
        'business/profile.md: timezone is missing, and it is required',
        'business/services/ac-repair.md: book_online must be true or false (you wrote yes)',
        'voice/how-we-talk.md: never_say is missing, and it is required',
    ]);

    foreach ($errors as $error) {
        expect($error)->not->toContain('validation.')->not->toContain(' field ');
    }
});

it('accepts empty lists where a list is allowed to be empty', function () {
    breakFile('voice/how-we-talk.md', fn (string $text) => str_replace('never_say: [guaranteed, cheapest, no problem]', 'never_say: []', $text));
    breakFile('business/hours.md', fn (string $text) => str_replace('closures: ["2026-11-26", "2026-12-25"]', 'closures: []', $text));

    $version = activateExampleFolder();
    $config = app(ActiveConfig::class);

    expect($version->file_count)->toBe(exampleFolderFileCount())
        ->and($config->voice()['never_say'])->toBe([])
        ->and($config->hours()['closures'])->toBe([]);
});

it('treats a file saved with Windows line endings and a byte-order mark the same as its plain copy', function () {
    $plain = activateExampleFolder();

    foreach (File::allFiles(icmPath()) as $file) {
        if ($file->getExtension() === 'md') {
            File::put($file->getPathname(), "\xEF\xBB\xBF".str_replace("\n", "\r\n", File::get($file->getPathname())));
        }
    }

    $result = app(FolderSync::class)->run();

    expect($result->outcome)->toBe(SyncOutcome::Activated)
        ->and($result->fileCount)->toBe(exampleFolderFileCount());

    $frontMatter = fn (IcmVersion $version) => collect($version->compiled['files'])->map(fn (array $file) => $file['front_matter'])->all();

    expect($frontMatter($result->version))->toBe($frontMatter($plain));
});

it('ignores keys it does not know and accepts an unquoted closure date as text', function () {
    breakFile('business/profile.md', fn (string $text) => str_replace("type: business\n", "type: business\ncolor: blue\n", $text));
    breakFile('business/hours.md', fn (string $text) => str_replace('closures: ["2026-11-26", "2026-12-25"]', 'closures: [2026-11-26, 2026-12-25]', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->hours()['closures'])->toBe(['2026-11-26', '2026-12-25']);
});
