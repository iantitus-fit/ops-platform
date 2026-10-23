<?php

use App\Enums\ActivityType;
use App\Events\CustomerRecordChanged;
use App\Models\Activity;
use App\Models\Contact;
use App\Records\CustomerCard;
use App\Rooms\RecordRoomEntry;
use Illuminate\Support\Facades\Event;

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
