<?php

use App\Filament\Widgets\DraftsWaiting;
use App\Filament\Widgets\NewLeads;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;
use Tests\Rooms\PretendRoom\PretendRoomSection;

/**
 * 06 D-18: a room's claimedWidgets is a pointer at dashboard widgets the panel already discovers. The registry checks each
 * claim when the room is added (the class exists, it is a Filament widget, no other room claims it) and never hands a
 * claim to the panel, so the panel keeps its widgets and their order. Each case uses a fresh registry.
 */
it('gives a room built without the argument no claims', function () {
    $room = new Room(key: 'plain', label: 'plain');

    expect($room->claimedWidgets)->toBe([]);
});

it('accepts a claim, keeps it on the room and never feeds it to the panel', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'claimer', label: 'claimer', claimedWidgets: [DraftsWaiting::class]));

    expect($registry->get('claimer')->claimedWidgets)->toBe([DraftsWaiting::class])
        ->and($registry->widgetClasses())->toBe([]);
});

it('refuses a claim of a class that does not exist', function () {
    (new RoomRegistry)->add(new Room(key: 'claimer', label: 'claimer', claimedWidgets: ['App\Nowhere\GhostWidget']));
})->throws(LogicException::class, 'does not exist');

it('refuses a claim of a class that is not a Filament widget', function () {
    (new RoomRegistry)->add(new Room(key: 'claimer', label: 'claimer', claimedWidgets: [RoomRegistry::class]));
})->throws(LogicException::class, 'not a Filament widget');

it('refuses a widget another room already claims and names both', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'first', label: 'first', claimedWidgets: [NewLeads::class]));

    expect(fn () => $registry->add(new Room(key: 'second', label: 'second', claimedWidgets: [NewLeads::class])))
        ->toThrow(LogicException::class, 'room [first] already claims');

    expect($registry->has('second'))->toBeFalse();
});

it('refuses a room that claims one widget twice (06-REVIEW WR-04)', function () {
    $registry = new RoomRegistry;

    expect(fn () => $registry->add(new Room(key: 'claimer', label: 'claimer', claimedWidgets: [DraftsWaiting::class, DraftsWaiting::class])))
        ->toThrow(LogicException::class, 'more than once');

    expect($registry->has('claimer'))->toBeFalse();
});

it('refuses a room that both adds a widget in its sections and claims it (06-REVIEW WR-04)', function () {
    $registry = new RoomRegistry;

    expect(fn () => $registry->add(new Room(key: 'pretend', label: 'pretend', sections: [PretendRoomSection::class], claimedWidgets: [PretendRoomSection::class])))
        ->toThrow(LogicException::class, 'both adds and claims');

    expect($registry->has('pretend'))->toBeFalse();
});

it('refuses a claim entry that is not a class name with a readable error (06-REVIEW IN-03)', function (mixed $entry) {
    $registry = new RoomRegistry;

    expect(fn () => $registry->add(new Room(key: 'claimer', label: 'claimer', claimedWidgets: [$entry])))
        ->toThrow(LogicException::class, 'Room [claimer] claims a widget that is not a class name.');

    expect($registry->has('claimer'))->toBeFalse();
})->with([
    'an array' => [[DraftsWaiting::class]],
    'a number' => [42],
]);
