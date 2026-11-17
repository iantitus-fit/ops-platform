<?php

use App\Icm\Exceptions\IcmFolderMissing;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Records\RecordsWriter;
use App\Rooms\OwnerIntelligence\TimeToBooked;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;

/**
 * D-23 and D-15: records/time-to-booked.md holds the 30-day median for the owner's AI, beside today.md, and both files go
 * when the owner intelligence room is off. Every time is written as America/Chicago wall time and stored in UTC. Pest
 * functions are global, so every helper here carries the timeToBookedFile prefix.
 */

/**
 * A moment on 2026-11-13 in the business zone, as the database holds it: in UTC.
 */
function timeToBookedFileAt(string $clock): CarbonImmutable
{
    return CarbonImmutable::parse("2026-11-13 {$clock}", 'America/Chicago')->utc();
}

/**
 * A new customer who texted at 09:00 and booked at 09:42 the same day, so their time to booked is 42 minutes.
 */
function timeToBookedFileCustomer(): Contact
{
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'body' => 'My AC is blowing warm air',
        'created_at' => timeToBookedFileAt('09:00'),
    ]);
    Appointment::factory()->create([
        'contact_id' => $contact->id,
        'booked_at' => timeToBookedFileAt('09:42'),
    ]);

    return $contact;
}

beforeEach(function () {
    activateExampleFolder();
    $this->travelTo(timeToBookedFileAt('12:00'));
});

it('writes the median line beside today.md (D-23)', function () {
    timeToBookedFileCustomer();

    app(RecordsWriter::class)->writeOwnerIntelligence();

    expect(File::get(icmPath('records/time-to-booked.md')))->toBe(
        "# Time to booked\n\nGenerated from the database. Read-only: edits here are overwritten by the next render.\n\n"
        .app(TimeToBooked::class)->summaryLine()
        ."\n\nTime to booked runs from a customer's first message to their first booking. The 30 days are counted in Central Time.\n"
    )->and(app(TimeToBooked::class)->summaryLine())->toBe('Your 1 new customer in the last 30 days booked within 42 minutes.')
        ->and(File::exists(icmPath('records/today.md')))->toBeTrue();
});

it('writes the empty line with no new customer', function () {
    app(RecordsWriter::class)->writeOwnerIntelligence();

    expect(File::get(icmPath('records/time-to-booked.md')))->toContain("\n\nNo new customers booked in the last 30 days.\n");
});

it('deletes both files when the room is off (D-15)', function () {
    File::deleteDirectory(icmPath('rooms/owner_intelligence'));
    activateExampleFolder();

    $writer = app(RecordsWriter::class);
    $writer->writeRouter();
    $writer->writeSummaries();
    File::put(icmPath('records/README.md'), 'kept');
    File::put(icmPath('records/today.md'), 'stale');
    File::put(icmPath('records/time-to-booked.md'), 'stale');

    $writer->writeOwnerIntelligence();

    expect(File::exists(icmPath('records/today.md')))->toBeFalse()
        ->and(File::exists(icmPath('records/time-to-booked.md')))->toBeFalse()
        ->and(File::exists(icmPath('records/CLAUDE.md')))->toBeTrue()
        ->and(File::exists(icmPath('records/README.md')))->toBeTrue()
        ->and(File::exists(icmPath('records/customers/INDEX.md')))->toBeTrue()
        ->and(File::exists(icmPath('records/open-leads.md')))->toBeTrue();
});

it('writes only while it holds the lock and reads the database inside it (D-14)', function () {
    timeToBookedFileCustomer();
    $lock = Cache::lock('records:owner-intelligence', 30);

    expect($lock->get())->toBeTrue();

    Sleep::fake(syncWithCarbon: true);
    DB::flushQueryLog();
    DB::enableQueryLog();

    expect(fn () => app(RecordsWriter::class)->writeOwnerIntelligence())->toThrow(LockTimeoutException::class)
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'appointments') || str_contains($query['query'], 'messages')))->toBeEmpty()
        ->and(File::exists(icmPath('records/today.md')))->toBeFalse();

    $lock->release();
    app(RecordsWriter::class)->writeOwnerIntelligence();

    expect(File::exists(icmPath('records/today.md')))->toBeTrue()
        ->and(File::exists(icmPath('records/time-to-booked.md')))->toBeTrue();
});

it('refuses a missing business folder', function () {
    $missing = icmPath();
    File::deleteDirectory($missing);

    expect(fn () => app(RecordsWriter::class)->writeOwnerIntelligence())->toThrow(IcmFolderMissing::class)
        ->and(File::exists($missing))->toBeFalse();
});
