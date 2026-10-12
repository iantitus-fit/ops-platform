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
    'a gas leak said as the gas is leaking' => ['the gas is leaking in the garage', 'gas'],
    'gas leaking from the stove' => ['I think there is gas leaking from the stove', 'gas'],
    'a kitchen that smells of gas' => ['the kitchen smells of gas', 'gas'],
    'a strong smell of propane' => ['strong smell of propane near the tank', 'gas'],
    'a gas odor' => ['there is a gas odor upstairs', 'gas'],
    'a gas odour' => ['a gas odour in the hall', 'gas'],
    'an electrical fire' => ['I think it is an electrical fire', 'general'],
    'a house that is burning' => ['help the house is burning', 'general'],
    'a burnt smell' => ['a burnt smell from the vents', 'general'],
    'a furnace that smells burnt' => ['the furnace smells burnt', 'general'],
    'a dishwasher appliance that leaks' => ['my dishwasher appliance is leaking', null],
    'a washing machine whose appliances leak water' => ['my washing machine appliances are leaking water', null],
    'a water heater access panel' => ['the water heater access panel is loose', null],
    'the wiring on a water heater is not water' => ['the wiring on my water heater is bad', null],
    'water onto the wiring of a water heater is water' => ['water is pouring onto the water heater wiring', 'water'],
    'water around a plugged-in fridge' => ['water around the plugged-in fridge', 'water'],
    'water near the breaker panel' => ['water near the breaker panel', 'water'],
    'water dripping onto the wiring' => ['water is dripping onto the wiring', 'water'],
    'water in the socket' => ['water in the socket', 'water'],
    'a question about servicing propane furnaces' => ['do you service propane furnaces', null],
    'a question comparing natural gas and propane cost' => ['is natural gas cheaper than propane', null],
    'an empty text is never a hazard' => ['', null],
    'a text of spaces is never a hazard' => ['   ', null],
    'upper case reads like lower case' => ['GAS IS LEAKING', 'gas'],
    'runs of spaces and line breaks read like one space' => ["gas  is\nleaking", 'gas'],
    'a hyphenated plugged-in in capitals' => ['WATER AROUND THE PLUGGED-IN DRYER', 'water'],
]);

it('keeps the gas list to phrases, never a bare fuel word (93ce078)', function () {
    foreach (['propane', 'natural gas', 'sulfur', 'sulphur', 'gas'] as $bareWord) {
        expect(LifeSafety::GAS_PHRASES)->not->toContain($bareWord);
    }

    foreach (['panel', 'appliance', 'appliances'] as $bareWord) {
        expect(LifeSafety::ELECTRICAL_WORDS)->not->toContain($bareWord);
    }
});

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
