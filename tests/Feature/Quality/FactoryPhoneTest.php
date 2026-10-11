<?php

use App\Models\Contact;
use App\Support\PhoneNumber;

it('keeps factory phone numbers out of the 555-01XX block that tests write by hand', function () {
    $phones = Contact::factory()->count(150)->make()->pluck('phone_e164');

    expect($phones->unique())->toHaveCount(150)
        ->and($phones->filter(fn (string $phone) => preg_match('/^\+1(202|312)55501\d\d$/', $phone) === 1))->toBeEmpty();
});

it('gives factory contacts phone numbers libphonenumber accepts, already in E.164', function () {
    $phones = Contact::factory()->count(150)->make()->pluck('phone_e164');

    expect($phones->every(fn (string $phone) => PhoneNumber::toE164($phone) === $phone))->toBeTrue();
});
