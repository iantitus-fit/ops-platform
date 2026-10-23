<?php

use App\Enums\ActivityType;
use App\Events\CustomerRecordChanged;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Icm\FolderSync;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\User;
use App\Records\CustomerCard;
use App\Rooms\RecordRoomEntry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * Register the pretend room, lay its contract and sync, so the live version has the room.
 */
function roomTimelinePretendRoomOn(): void
{
    enablePretendRoom();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();
}

it('writes a room entry and the customer card draws it with the label and intro the room registered', function () {
    roomTimelinePretendRoomOn();
    $contact = Contact::factory()->create(['name' => 'Dana Whitfield']);

    $activity = app(RecordRoomEntry::class)->handle($contact, 'pretend', 'ping_received', 'Ping from the pretend feed');

    expect($activity)->toBeInstanceOf(Activity::class)
        ->and($activity->type)->toBe(ActivityType::RoomEntry)
        ->and($activity->room)->toBe('pretend')
        ->and($activity->entry)->toBe('ping_received');

    $card = app(CustomerCard::class)->render($contact);

    expect($card)->toContain('· Pretend ping')
        ->and(strpos($card, 'System note:'))->toBeGreaterThan(strpos($card, '· Pretend ping'))
        ->and(strpos($card, '> Ping from the pretend feed'))->toBeGreaterThan(strpos($card, 'System note:'));
});

it('draws a room entry its room no longer registers as a plain room entry, without throwing', function () {
    roomTimelinePretendRoomOn();
    $contact = Contact::factory()->create();

    Activity::factory()->create([
        'contact_id' => $contact->id,
        'type' => ActivityType::RoomEntry,
        'room' => 'pretend',
        'entry' => 'retired_entry',
        'summary' => 'Something the room used to write',
    ]);

    $card = app(CustomerCard::class)->render($contact);

    expect($card)->toContain('· Room entry')
        ->and($card)->toContain('System note:')
        ->and($card)->toContain('> Something the room used to write');
});

it('writes nothing while the room is not in the live version', function () {
    enablePretendRoom();
    activateExampleFolder();
    $contact = Contact::factory()->create();

    $activity = app(RecordRoomEntry::class)->handle($contact, 'pretend', 'ping_received', 'Ping from the pretend feed');

    expect($activity)->toBeNull()
        ->and(Activity::query()->count())->toBe(0);
});

it('refuses an entry key the room never registered', function () {
    roomTimelinePretendRoomOn();
    $contact = Contact::factory()->create();

    expect(fn () => app(RecordRoomEntry::class)->handle($contact, 'pretend', 'not_registered', 'Nope'))
        ->toThrow(LogicException::class, 'not_registered');

    expect(Activity::query()->count())->toBe(0);
});

it('tells the card to be redrawn when a room entry is written', function () {
    roomTimelinePretendRoomOn();
    $contact = Contact::factory()->create();
    Event::fake([CustomerRecordChanged::class]);

    app(RecordRoomEntry::class)->handle($contact, 'pretend', 'ping_received', 'Ping from the pretend feed');

    Event::assertDispatched(CustomerRecordChanged::class, fn (CustomerRecordChanged $event): bool => $event->contactId === $contact->id);
});

/**
 * A customer with a ping_received entry from the pretend room and a v1.0-style note (room null).
 */
function roomTimelineContactWithEntryAndNote(string $name = 'Dana Whitfield'): Contact
{
    $contact = Contact::factory()->create(['name' => $name]);

    app(RecordRoomEntry::class)->handle($contact, 'pretend', 'ping_received', 'Ping from the pretend feed');
    Activity::factory()->create([
        'contact_id' => $contact->id,
        'type' => ActivityType::Note,
        'summary' => 'Called about the quote',
    ]);

    return $contact;
}

/**
 * The contact ids of every CustomerRecordChanged the faked dispatcher has seen, in order.
 *
 * @return list<int>
 */
function roomTimelineRedrawnContacts(): array
{
    return Event::dispatched(CustomerRecordChanged::class)
        ->map(fn (array $dispatched): int => $dispatched[0]->contactId)
        ->values()
        ->all();
}

it('hides a removed room\'s entries wherever the timeline is read and deletes nothing', function () {
    roomTimelinePretendRoomOn();
    $contact = roomTimelineContactWithEntryAndNote();
    $rows = Activity::query()->count();

    File::deleteDirectory(icmPath('rooms/pretend'));
    activateExampleFolder();

    $card = app(CustomerCard::class)->render($contact);

    expect($card)->toContain('> Called about the quote')
        ->and($card)->not->toContain('Pretend ping')
        ->and($contact->timeline()->get()->pluck('type')->all())->toBe([ActivityType::Note])
        ->and(Activity::query()->count())->toBe($rows);
});

it('shows the entries again when the room folder comes back', function () {
    roomTimelinePretendRoomOn();
    $contact = roomTimelineContactWithEntryAndNote();
    $rows = Activity::query()->count();

    File::deleteDirectory(icmPath('rooms/pretend'));
    activateExampleFolder();
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();

    expect(app(CustomerCard::class)->render($contact))->toContain('· Pretend ping')->toContain('> Called about the quote')
        ->and($contact->timeline()->get())->toHaveCount(2)
        ->and(Activity::query()->count())->toBe($rows);
});

it('shows rows with no room and hides room rows before any version is live, without throwing', function () {
    $contact = Contact::factory()->create();
    Activity::factory()->create(['contact_id' => $contact->id, 'type' => ActivityType::Note, 'summary' => 'Called about the quote']);
    Activity::factory()->create([
        'contact_id' => $contact->id,
        'type' => ActivityType::RoomEntry,
        'room' => 'pretend',
        'entry' => 'ping_received',
        'summary' => 'Ping from the pretend feed',
    ]);

    expect(app(CustomerCard::class)->render($contact))->toContain('> Called about the quote')->not->toContain('Pretend ping')
        ->and($contact->timeline()->count())->toBe(1);
});

it('badges a room entry on the contact page with its registered label and leaves other badges alone', function () {
    $this->actingAs(User::factory()->create());
    roomTimelinePretendRoomOn();
    $contact = roomTimelineContactWithEntryAndNote();
    Activity::factory()->create(['contact_id' => $contact->id, 'type' => ActivityType::TextReceived, 'summary' => 'Text received']);

    Livewire::test(ViewContact::class, ['record' => $contact->id])
        ->assertSee('Pretend ping')
        ->assertSee('text_received')
        ->assertSee('note')
        ->assertDontSee('room_entry');
});

it('draws the badge of an entry its room no longer registers as Room entry', function () {
    $this->actingAs(User::factory()->create());
    roomTimelinePretendRoomOn();
    $contact = Contact::factory()->create();
    Activity::factory()->create([
        'contact_id' => $contact->id,
        'type' => ActivityType::RoomEntry,
        'room' => 'pretend',
        'entry' => 'retired_entry',
        'summary' => 'Something the room used to write',
    ]);

    Livewire::test(ViewContact::class, ['record' => $contact->id])
        ->assertSee('Room entry')
        ->assertSee('Something the room used to write');
});

it('redraws the cards of the contacts holding a room\'s entries when a sync turns the room off or on, and no others', function () {
    roomTimelinePretendRoomOn();
    $withEntry = roomTimelineContactWithEntryAndNote('Dana Whitfield');
    $withNoteOnly = Contact::factory()->create();
    Activity::factory()->create(['contact_id' => $withNoteOnly->id, 'type' => ActivityType::Note, 'summary' => 'Only a note']);

    Event::fake([CustomerRecordChanged::class]);
    File::deleteDirectory(icmPath('rooms/pretend'));
    activateExampleFolder();

    expect(roomTimelineRedrawnContacts())->toBe([$withEntry->id]);

    Event::fake([CustomerRecordChanged::class]);
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();

    expect(roomTimelineRedrawnContacts())->toBe([$withEntry->id]);
});

it('redraws nothing when a sync leaves the room set as it was, or changes nothing at all', function () {
    roomTimelinePretendRoomOn();
    roomTimelineContactWithEntryAndNote();

    Event::fake([CustomerRecordChanged::class]);
    breakFile('business/profile.md', fn (string $contents): string => $contents."\nOne more line about the shop.\n");
    activateExampleFolder();

    Event::assertNotDispatched(CustomerRecordChanged::class);

    app(FolderSync::class)->run();

    Event::assertNotDispatched(CustomerRecordChanged::class);
});

it('keeps the stored card in records/ in step with the room: the entry leaves with the folder and returns with it', function () {
    roomTimelinePretendRoomOn();
    $contact = roomTimelineContactWithEntryAndNote();
    $card = icmPath('records/customers/'.app(CustomerCard::class)->filename($contact));

    expect(File::get($card))->toContain('Pretend ping')->toContain('Called about the quote');

    File::deleteDirectory(icmPath('rooms/pretend'));
    activateExampleFolder();

    expect(File::get($card))->not->toContain('Pretend ping')->toContain('Called about the quote');

    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    activateExampleFolder();

    expect(File::get($card))->toContain('Pretend ping')->toContain('Called about the quote');
});
