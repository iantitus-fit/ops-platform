<?php

use App\Filament\Widgets\NewLeads;
use App\Rooms\Marketing\MarketingSection;
use App\Rooms\Room;
use App\Rooms\RoomRegistry;

/**
 * 07 D-15, closing 06-REVIEW WR-09: a widget has one owner room. A room may not claim a widget another room adds in its
 * sections, and a room may not add a section another room claims. Rooms register one at a time, so each guard compares a
 * new room with the rooms already registered and the refusal holds whichever room comes first. Each case uses a fresh
 * registry. MarketingSection is a real RoomSection and a real Filament widget, so it stands in for any section.
 */
it('refuses a claim of a widget another room adds in its sections', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'marketing', label: 'marketing', sections: [MarketingSection::class]));

    expect(fn () => $registry->add(new Room(key: 'other', label: 'other', claimedWidgets: [MarketingSection::class])))
        ->toThrow(LogicException::class, 'Room [other] claims widget [App\Rooms\Marketing\MarketingSection], which room [marketing] adds in its sections. A widget has one owner room.');

    expect($registry->has('other'))->toBeFalse();
});

it('refuses a section another room already claims', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'other', label: 'other', claimedWidgets: [MarketingSection::class]));

    expect(fn () => $registry->add(new Room(key: 'marketing', label: 'marketing', sections: [MarketingSection::class])))
        ->toThrow(LogicException::class, 'Room [marketing] adds widget [App\Rooms\Marketing\MarketingSection] in its sections, which room [other] claims. A widget has one owner room.');

    expect($registry->has('marketing'))->toBeFalse();
});

it('accepts rooms whose sections and claims do not meet', function () {
    $registry = new RoomRegistry;
    $registry->add(new Room(key: 'marketing', label: 'marketing', sections: [MarketingSection::class]));
    $registry->add(new Room(key: 'leads', label: 'leads', claimedWidgets: [NewLeads::class]));

    expect($registry->has('marketing'))->toBeTrue()
        ->and($registry->has('leads'))->toBeTrue()
        ->and($registry->widgetClasses())->toBe([MarketingSection::class]);
});
