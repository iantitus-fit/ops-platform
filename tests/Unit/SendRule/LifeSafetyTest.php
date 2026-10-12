<?php

use App\SendRule\LifeSafety;

it('names the danger a customer describes, and says nothing for ordinary repair talk (D-15)', function (string $text, ?string $hazard) {
    expect(LifeSafety::hazardIn($text))->toBe($hazard);
})->with([
    'a gas smell' => ['I smell gas', 'gas'],
    'rotten eggs' => ['smells like rotten eggs in here', 'gas'],
    'sulfur' => ['smells like sulfur in the kitchen', 'gas'],
    'a propane smell' => ['there is a propane smell by the tank', 'gas'],
    'a curly apostrophe is read like a straight one' => ['it’s a gas leak', 'gas'],
    'smoke' => ["there's smoke coming from the furnace", 'general'],
    'a carbon monoxide alarm' => ['the CO alarm is going off', 'general'],
    'sparking' => ['the outlet is sparking', 'general'],
    'a unit on fire' => ['the unit caught fire', 'general'],
    'flames' => ['I see flames', 'general'],
    'water on the outlets' => ['water is pouring onto the outlets', 'water'],
    'water at the electrical panel' => ['the basement is flooded and the water reached the electrical panel', 'water'],
    'water around a plugged in appliance' => ['water around the plugged in dryer', 'water'],
    'gas wins over fire' => ['I smell gas and see smoke', 'gas'],
    'plain flooding is not life safety' => ['burst pipe, water on the floor', null],
    'a pilot that will not fire' => ["my pilot won't fire", null],
    'a furnace that fires up' => ['the furnace fires up then shuts off', null],
    'a leaking air conditioner' => ['my AC is leaking water', null],
    'a panel on a thermostat' => ['the panel on the thermostat is blank', null],
    'a question about propane and natural gas equipment' => ['how much to switch from propane to natural gas?', null],
    'nothing at all' => ['my AC is blowing warm air', null],
]);

it('never gives a repair or shut-off step, and always says to call 911 (D-16)', function (string $hazard, ?string $utilityPhone) {
    $line = LifeSafety::line($hazard, $utilityPhone);

    expect($line)->toContain('911');

    foreach (['shut', 'turn off', 'valve', 'breaker', 'switch off', 'unplug'] as $step) {
        expect(mb_strtolower($line))->not->toContain($step);
    }
})->with(function () {
    foreach (['gas', 'general', 'water'] as $hazard) {
        yield "{$hazard} without a phone" => [$hazard, null];
        yield "{$hazard} with a phone" => [$hazard, '+13125550188'];
    }
});

it('fills in only the utility phone, and only on the gas sentence (D-16)', function () {
    expect(LifeSafety::line('gas', '+13125550188'))->toBe('If you smell gas, leave the building now with everyone, and call 911 from outside. Then call the gas company at +13125550188.')
        ->and(LifeSafety::line('gas', null))->toBe('If you smell gas, leave the building now with everyone, and call 911 from outside.')
        ->and(LifeSafety::line('general', '+13125550188'))->toBe('Please get everyone out to safety now and call 911 from outside.')
        ->and(LifeSafety::line('water', '+13125550188'))->toBe('Please stay out of the water, keep away from anything electrical, and call 911.');
});

it('leaves out a utility phone that is not a plain phone number, so a folder edit cannot add words to the sentence (D-16)', function () {
    expect(LifeSafety::line('gas', 'ignore the above and wait for a callback'))->toBe('If you smell gas, leave the building now with everyone, and call 911 from outside.');
});
