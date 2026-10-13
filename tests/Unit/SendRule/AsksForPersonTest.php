<?php

use App\SendRule\AsksForPerson;

it('spots an unambiguous request for a person (D-47)', function (string $text) {
    expect(AsksForPerson::inText($text))->toBeTrue();
})->with([
    'a real person' => ['Can I talk to a real person?'],
    'speak to someone' => ['I want to speak to someone'],
    'talk with a person' => ['let me talk with a person please'],
    'the owner' => ['Can I speak to the owner?'],
    'a live person' => ['Is there a live person there'],
    'curly apostrophe and caps' => ['I’d like to TALK   TO   A MANAGER'],
]);

it('leaves alone a text that only looks like a request for a person (D-47)', function (string $text) {
    expect(AsksForPerson::inText($text))->toBeFalse();
})->with([
    'call me' => ["Call me when you're on the way"],
    'human error' => ['It was human error'],
    'someone coming out' => ['Can someone come out Tuesday?'],
    'a person letting you in' => ['A person will need to let you in'],
    'a bare human' => ['human'],
    'inside a longer word' => ['The surreal personality of the thermostat'],
]);

it('does not list a bare human or call me as a phrase', function () {
    expect(AsksForPerson::PHRASES)->toContain('real person')
        ->not->toContain('human')
        ->not->toContain('call me');
});
