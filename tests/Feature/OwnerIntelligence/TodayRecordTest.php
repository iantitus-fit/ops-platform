<?php

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\ScheduleBlock;
use App\Records\CustomerCard;
use App\Records\RecordsWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * RECS-02, D-12 and D-13: records/today.md lists today's visits and blocked time for the owner's AI. The test below draws
 * the fictional day Phase 9 settled on and checks that a reader can answer six questions from the file alone. Every time
 * is written as America/Chicago wall time and stored in UTC, because Eloquent saves a date as its wall time and drops the
 * zone. Pest functions are global, so every helper here carries the todayRecord prefix.
 */

/**
 * A moment on the given day in the business zone, as the database holds it: in UTC.
 */
function todayRecordAt(string $clock, string $day = '2026-11-13'): CarbonImmutable
{
    return CarbonImmutable::parse("{$day} {$clock}", 'America/Chicago')->utc();
}

/**
 * The link text a row uses for a contact: the card's own filename, never the raw name.
 */
function todayRecordCard(Contact $contact): string
{
    return 'customers/'.app(CustomerCard::class)->filename($contact);
}

/**
 * The file's contents after a draw.
 */
function todayRecordFile(): string
{
    app(RecordsWriter::class)->writeOwnerIntelligence();

    return File::get(icmPath('records/today.md'));
}

beforeEach(function () {
    activateExampleFolder();
    $this->travelTo(todayRecordAt('08:00'));
});

it('draws the day so a reader can answer six questions (D-13)', function () {
    $dana = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $marcus = Contact::factory()->create(['name' => 'Marcus Lee', 'phone_e164' => '+13125550147']);
    $lee = Contact::factory()->create(['name' => 'Lee Park']);

    Appointment::factory()->at(todayRecordAt('09:00'), 90)->done()->create(['contact_id' => $dana->id, 'service_name' => 'AC repair']);
    ScheduleBlock::factory()->at(todayRecordAt('11:00'), 60)->create(['note' => null]);
    Appointment::factory()->at(todayRecordAt('13:00'), 60)->create(['contact_id' => $marcus->id, 'service_name' => 'Furnace check', 'note' => '12 Elm Street, gate code 4411']);
    Appointment::factory()->at(todayRecordAt('15:30'), 60)->cancelled()->create(['contact_id' => $lee->id, 'service_name' => 'Tune-up']);
    Appointment::factory()->at(todayRecordAt('10:00', '2026-11-14'), 90)->create(['contact_id' => $marcus->id, 'service_name' => 'AC repair']);

    $file = todayRecordFile();
    $lines = explode("\n", $file);
    $marcusRow = '- 1:00 PM to 2:00 PM: Furnace check for ['.'Marcus Lee]('.todayRecordCard($marcus).'), booked';
    $danaRow = '- 9:00 AM to 10:30 AM: AC repair for [Dana Whitfield]('.todayRecordCard($dana).'), done';
    $leeRow = '- 3:30 PM to 4:30 PM: Tune-up for [Lee Park]('.todayRecordCard($lee).'), cancelled';

    expect($lines[0])->toBe('# Schedule for Friday, November 13')
        ->and(substr_count($file, 'Times are in Central Time.'))->toBe(1)
        ->and($lines)->toContain($marcusRow)
        ->and($lines)->toContain($danaRow)
        ->and($lines)->toContain($leeRow)
        ->and($lines)->toContain('- 11:00 AM to 12:00 PM: Blocked')
        ->and($file)->not->toContain('November 14')->not->toContain('Saturday')
        ->and($file)->not->toContain('Elm Street')->not->toContain('4411')->not->toContain('3125550147')->not->toContain('Job');

    $rows = array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, '- ')));

    expect($rows)->toBe([
        $danaRow,
        '- 11:00 AM to 12:00 PM: Blocked',
        $marcusRow,
        $leeRow,
    ]);
});
