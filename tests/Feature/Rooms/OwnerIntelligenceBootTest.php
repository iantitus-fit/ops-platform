<?php

use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Rooms\OwnerIntelligence\OwnerIntelligenceSection;
use App\Rooms\OwnerIntelligence\OwnerIntelligenceServiceProvider;
use App\Rooms\RoomRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Tests\Rooms\SpecTables;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * The owner intelligence room's boot test (ROOM-13), in the shape the leads room set: the room boots and each boot
 * proves the registered room and its contract against SPEC's rows (04 D-14). The room owns no file, so its empty folder
 * and its example folder are the same folder and there is one boot case (04 D-21). Its section holds the one
 * time-to-booked line (D-07, D-24). There is no database-driver guard and no skip: CI runs this on SQLite and Postgres.
 * Pest functions are global, so every helper here carries the ownerIntelligence prefix.
 */

/**
 * A moment on 2026-11-13 in the business zone, stored the way the database holds it: in UTC.
 */
function ownerIntelligenceAt(string $clock): CarbonImmutable
{
    return CarbonImmutable::parse("2026-11-13 {$clock}", 'America/Chicago')->utc();
}

/**
 * A contact who texted at the first time and was booked at the second, both on 2026-11-13 Chicago time.
 */
function ownerIntelligenceBookedContact(string $name, string $texted, string $booked): Contact
{
    $contact = Contact::factory()->create(['name' => $name]);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'body' => 'My AC is blowing warm air',
        'created_at' => ownerIntelligenceAt($texted),
    ]);

    Appointment::factory()->create([
        'contact_id' => $contact->id,
        'booked_at' => ownerIntelligenceAt($booked),
    ]);

    return $contact;
}

it('boots the owner intelligence room', function () {
    $version = activateExampleFolder();

    $config = app(ActiveConfig::class);
    $room = app(RoomRegistry::class)->get('owner_intelligence');

    expect($version->file_count)->toBe(exampleFolderFileCount())
        ->and($config->hasRoom('owner_intelligence'))->toBeTrue();

    expect($room->label)->toBe('owner intelligence')
        ->and($room->handles)->toBe([])
        ->and($room->fileTypes)->toBe([])
        ->and($room->slot)->toBeNull()
        ->and($room->slotInterface)->toBeNull()
        ->and($room->sections)->toBe([OwnerIntelligenceSection::class])
        ->and($room->tables)->toBe([])
        ->and($room->timelineEntries)->toBe([])
        ->and($room->morphMap)->toBe([])
        ->and($room->neverLeftOutBecause)->toBeNull()
        ->and($room->claimedWidgets)->toBe([]);

    $contract = $config->roomContract('owner_intelligence');

    expect($contract['owns'])->toBe([])
        ->and($contract['owns'])->toBe(SpecTables::patternsOwnedBy(File::get(base_path('docs/SPEC.md')), 'owner intelligence'))
        ->and($contract['reads'])->toBe(['business/profile.md'])
        ->and($contract['writes_records'])->toBe(['records/today.md', 'records/time-to-booked.md'])
        ->and($contract['connector'])->toBe('none');

    expect($config->roomOwnedFiles('owner_intelligence'))->toBe([]);

    $context = File::get(icmPath('rooms/owner_intelligence/CONTEXT.md'));

    expect($context)->toContain('records/today.md')
        ->and($context)->toContain('records/time-to-booked.md')
        ->and($context)->toContain('It owns no file.')
        ->and($context)->toContain('It has no outside system.');

    expect(File::directories(icmPath('rooms/owner_intelligence')))->toBe([])
        ->and(array_map('basename', File::files(icmPath('rooms/owner_intelligence'))))->toBe(['CONTEXT.md']);

    expect(app(RoomRegistry::class)->widgetClasses())->toContain(OwnerIntelligenceSection::class);

    $providers = require base_path('bootstrap/providers.php');

    expect($providers[0])->toBe(AppServiceProvider::class)
        ->and($providers)->toContain(OwnerIntelligenceServiceProvider::class);
});

it('shows the median time to booked on the dashboard', function () {
    activateExampleFolder();
    $this->travelTo(ownerIntelligenceAt('12:00'));

    ownerIntelligenceBookedContact('Dana Whitfield', '09:00', '09:42');

    $this->get('/admin')
        ->assertOk()
        ->assertSee('data-room-section="owner_intelligence"', false)
        ->assertSee('Owner intelligence')
        ->assertSee('Your 1 new customer in the last 30 days booked within 42 minutes.');
});
