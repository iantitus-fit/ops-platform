<?php

use App\Channels\TextChannel;
use App\Icm\IcmFile;
use App\Rooms\Room;
use App\Rooms\RoomFileType;
use App\Rooms\RoomRegistry;
use App\Rooms\TimelineEntryType;

/**
 * A room file type with the given name and no rules, for registry tests.
 */
function registryTestFileType(string $type): RoomFileType
{
    return new class($type) implements RoomFileType
    {
        public function __construct(private readonly string $type) {}

        public function type(): string
        {
            return $this->type;
        }

        public function rules(IcmFile $file): array
        {
            return [];
        }

        public function checks(IcmFile $file): array
        {
            return [];
        }
    };
}

/**
 * A registry holding one room that defines the given new file type, for tests of what a second room may not reuse.
 */
function registryWithTypeOwner(string $type): RoomRegistry
{
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'owner', label: 'owner', fileTypes: [registryTestFileType($type)]));

    return $registry;
}

it('refuses a second room with the same key', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'pretend', label: 'pretend'));

    expect(fn () => $registry->add(new Room(key: 'pretend', label: 'other')))
        ->toThrow(LogicException::class, 'pretend');
});

it('refuses a key that is not lower snake case', function (string $key) {
    expect(fn () => (new RoomRegistry)->add(new Room(key: $key, label: 'x')))
        ->toThrow(LogicException::class, 'lower_snake_case');
})->with(['Pretend', 'pretend-room', 'pretend room', 'pretend__room', '_pretend', 'pretend_', '']);

it('refuses a new file type that clashes with a built-in type', function (string $type) {
    expect(fn () => (new RoomRegistry)->add(new Room(key: 'quiet', label: 'quiet', fileTypes: [registryTestFileType($type)])))
        ->toThrow(LogicException::class, $type);
})->with(['knowledge', 'service', 'room', 'connector', 'automation', 'walk_test']);

it('refuses a new file type that is not lower snake case', function () {
    expect(fn () => (new RoomRegistry)->add(new Room(key: 'quiet', label: 'quiet', fileTypes: [registryTestFileType('Pretend-Note')])))
        ->toThrow(LogicException::class, 'Pretend-Note');
});

it('refuses a new file type another room already defines', function () {
    $registry = registryWithTypeOwner('pretend_note');

    expect(fn () => $registry->add(new Room(key: 'second', label: 'second', fileTypes: [registryTestFileType('pretend_note')])))
        ->toThrow(LogicException::class, 'pretend_note');
});

it('refuses a slot name another room already uses', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'first', label: 'first', slot: 'feed', slotInterface: TextChannel::class));

    expect(fn () => $registry->add(new Room(key: 'second', label: 'second', slot: 'feed', slotInterface: TextChannel::class)))
        ->toThrow(LogicException::class, 'feed');
});

it('refuses a slot without an interface, or an interface without a slot', function () {
    expect(fn () => (new RoomRegistry)->add(new Room(key: 'quiet', label: 'quiet', slot: 'feed')))
        ->toThrow(LogicException::class, 'feed')
        ->and(fn () => (new RoomRegistry)->add(new Room(key: 'quiet', label: 'quiet', slotInterface: TextChannel::class)))
        ->toThrow(LogicException::class, TextChannel::class);
});

it('refuses a slot interface that is not an existing interface', function () {
    expect(fn () => (new RoomRegistry)->add(new Room(key: 'quiet', label: 'quiet', slot: 'feed', slotInterface: 'App\Nowhere\NoSuchFeed')))
        ->toThrow(LogicException::class, 'App\Nowhere\NoSuchFeed');
});

it('refuses a slot name that is not lower snake case', function () {
    expect(fn () => (new RoomRegistry)->add(new Room(key: 'quiet', label: 'quiet', slot: 'Feed-Slot', slotInterface: TextChannel::class)))
        ->toThrow(LogicException::class, 'Feed-Slot');
});

it('accepts a slot interface from outside the room folder', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'front_desk', label: 'front desk', slot: 'text', slotInterface: TextChannel::class));

    expect($registry->roomWithSlot('text')?->key)->toBe('front_desk')
        ->and($registry->slots())->toBe(['text']);
});

it('accepts a room with only a key and a label', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'quiet', label: 'quiet'));

    expect($registry->has('quiet'))->toBeTrue()
        ->and($registry->get('quiet')?->label)->toBe('quiet')
        ->and($registry->registeredTypes())->toBe([])
        ->and($registry->slots())->toBe([])
        ->and($registry->widgetClasses())->toBe([]);
});

it('returns rooms in key order whatever order they were added in', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'zeta', label: 'zeta', sections: ['App\Zeta\ZetaSection']));
    $registry->add(new Room(key: 'alpha', label: 'alpha', sections: ['App\Alpha\AlphaSection']));

    expect(array_map(fn (Room $room): string => $room->key, $registry->all()))->toBe(['alpha', 'zeta'])
        ->and($registry->keys())->toBe(['alpha', 'zeta'])
        ->and($registry->widgetClasses())->toBe(['App\Alpha\AlphaSection', 'App\Zeta\ZetaSection']);
});

it('looks up file types, handlers, slots and timeline entries, and answers null or empty for unknown names', function () {
    $note = registryTestFileType('pretend_note');
    $entry = new TimelineEntryType(key: 'noted', label: 'Note added', intro: 'System note:');
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'beta', label: 'beta', handles: ['agent'], fileTypes: [$note], slot: 'feed', slotInterface: TextChannel::class, timelineEntries: [$entry]));
    $registry->add(new Room(key: 'alpha', label: 'alpha', handles: ['agent']));

    expect(array_map(fn (Room $room): string => $room->key, $registry->roomsHandling('agent')))->toBe(['alpha', 'beta'])
        ->and(array_map(fn (Room $room): string => $room->key, $registry->roomsHandling('pretend_note')))->toBe(['beta'])
        ->and($registry->roomsHandling('offer'))->toBe([])
        ->and($registry->registeredTypes())->toBe(['pretend_note'])
        ->and($registry->fileType('pretend_note'))->toBe($note)
        ->and($registry->fileType('nothing'))->toBeNull()
        ->and($registry->roomWithSlot('feed')?->key)->toBe('beta')
        ->and($registry->roomWithSlot('nothing'))->toBeNull()
        ->and($registry->slots())->toBe(['feed'])
        ->and($registry->timelineEntry('beta', 'noted'))->toBe($entry)
        ->and($registry->timelineEntry('beta', 'nothing'))->toBeNull()
        ->and($registry->timelineEntry('nothing', 'noted'))->toBeNull()
        ->and($registry->get('nothing'))->toBeNull();
});
