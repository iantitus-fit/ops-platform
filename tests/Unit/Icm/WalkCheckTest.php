<?php

use App\Enums\ReplyLabel;
use App\Icm\Walks\WalkCheck;
use App\Icm\Walks\WalkCheckKind;

dataset('fixed check phrases', fn () => [
    'sent on its own' => ['sent on its own', WalkCheckKind::SentOnItsOwn, null],
    'held for the owner' => ['held for the owner', WalkCheckKind::HeldForTheOwner, null],
    'handed to the owner' => ['handed to the owner', WalkCheckKind::HandedToTheOwner, null],
    'carries the booking link' => ['carries the booking link', WalkCheckKind::CarriesBookingLink, null],
    'carries the visit line' => ['carries the visit line', WalkCheckKind::CarriesVisitLine, null],
    'owner alerted' => ['owner alerted', WalkCheckKind::OwnerAlerted, null],
    'booked' => ['booked', WalkCheckKind::Booked, null],
    'reminder queued' => ['reminder queued', WalkCheckKind::ReminderQueued, null],
    'card updated' => ['card updated', WalkCheckKind::CardUpdated, null],
    'records updated' => ['records updated', WalkCheckKind::RecordsUpdated, null],
    'price sentence, straight apostrophe' => ["carries the owner's price sentence for ac-repair", WalkCheckKind::CarriesPriceSentence, 'ac-repair'],
    'price sentence, curly apostrophe' => ["carries the owner\u{2019}s price sentence for ac-repair", WalkCheckKind::CarriesPriceSentence, 'ac-repair'],
    'price sentence, capitals' => ["CARRIES THE OWNER'S PRICE SENTENCE FOR AC-REPAIR", WalkCheckKind::CarriesPriceSentence, 'ac-repair'],
    'surrounding spaces' => ['  Sent On Its Own  ', WalkCheckKind::SentOnItsOwn, null],
]);

it('reads a phrase from the closed check list', function (string $phrase, WalkCheckKind $kind, ?string $argument) {
    $check = WalkCheck::parse($phrase);

    expect($check)->not->toBeNull()
        ->and($check->kind)->toBe($kind)
        ->and($check->argument)->toBe($argument)
        ->and($check->phrase)->toBe(trim($phrase));
})->with('fixed check phrases');

it('reads labeled with each reply label', function (string $label) {
    $check = WalkCheck::parse("labeled {$label}");

    expect($check->kind)->toBe(WalkCheckKind::Labeled)
        ->and($check->argument)->toBe($label);
})->with(ReplyLabel::values());

it('gives null for a phrase that is not in the list', function (string $phrase) {
    expect(WalkCheck::parse($phrase))->toBeNull();
})->with([
    'a made-up phrase' => ['sends'],
    'a label that does not exist' => ['labeled gossip'],
    'labeled with no label' => ['labeled'],
    'a price sentence with no service' => ["carries the owner's price sentence for"],
    'a price sentence for a slug with capitals and a space' => ["carries the owner's price sentence for AC Repair!"],
    'empty' => [''],
]);

it('lists the phrases an owner may write after a customer text', function () {
    $list = WalkCheck::vocabulary(true);

    expect($list)->toContain('sent on its own', 'held for the owner', 'handed to the owner', 'labeled <label>', "carries the owner's price sentence for <service slug>", 'carries the booking link', 'carries the visit line', 'owner alerted')
        ->and($list)->not->toContain('booked');
});

it('lists the phrases an owner may write after a booking, a wait or at the end', function () {
    $list = WalkCheck::vocabulary(false);

    expect($list)->toBe(['owner alerted', 'booked', 'reminder queued', 'card updated', 'records updated']);
});
