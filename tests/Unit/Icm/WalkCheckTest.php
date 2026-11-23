<?php

use App\Enums\ReplyLabel;
use App\Icm\Walks\WalkCheck;
use App\Icm\Walks\WalkCheckKind;
use App\Icm\Walks\WalkStepKind;
use App\Icm\Walks\WalkTurnObservation;
use App\Icm\Walks\WalkWorldState;

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

it('finds a day, a date or a clock time named in a phrase', function (string $phrase, string $found) {
    expect(WalkCheck::namedTime($phrase))->toBe($found);
})->with([
    'a clock time' => ['booked by 3pm', '3pm'],
    'a clock time with a space' => ['booked at 3 pm', '3 pm'],
    'a clock time with minutes' => ['booked at 3:30pm', '3:30pm'],
    'a 24-hour time' => ['booked at 15:00', '15:00'],
    'a weekday' => ['booked on Friday', 'Friday'],
    'a short weekday' => ['booked on fri', 'fri'],
    'a date' => ['booked on 2026-12-02', '2026-12-02'],
    'the first one when there are two' => ['booked monday at 3pm', 'monday'],
]);

it('finds no time in a phrase from the list', function (string $phrase) {
    expect(WalkCheck::namedTime($phrase))->toBeNull();
})->with([
    'sent on its own',
    'booked',
    'the earliest time offered',
    'sunshine and satisfied customers',
]);

/**
 * What the walk saw in one turn, with every fact false or empty unless a case says otherwise.
 *
 * @param  array<string, mixed>  $with
 */
function evaluateSeen(array $with = []): WalkTurnObservation
{
    return new WalkTurnObservation(...[
        'turn' => 1,
        'kind' => WalkStepKind::Says,
        'sentOnItsOwn' => false,
        'heldForOwner' => false,
        'handedToOwner' => false,
        'label' => null,
        'parts' => [],
        'sentText' => '',
        'ownerAlerted' => false,
        ...$with,
    ]);
}

/**
 * What the walk's world held at the point of the check, with every fact false unless a case says otherwise.
 *
 * @param  array<string, bool>  $with
 */
function evaluateWorld(array $with = []): WalkWorldState
{
    return new WalkWorldState(...[
        'booked' => false,
        'reminderQueued' => false,
        'cardShowsVisit' => false,
        'todayListsVisit' => false,
        'ownerAlertedInWalk' => false,
        ...$with,
    ]);
}

/**
 * The folder's services as the runner hands them over, keyed by slug.
 *
 * @return array<string, array<string, mixed>>
 */
function evaluateServices(): array
{
    return [
        'ac-repair' => [
            'name' => 'AC repair',
            'price_sentences' => [['sentence' => evaluatePriceSentence()]],
        ],
    ];
}

/**
 * The example business's one AC repair price sentence, as its service file holds it.
 */
function evaluatePriceSentence(): string
{
    return 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';
}

dataset('check evaluations', fn () => [
    'sent on its own, and it was' => ['sent on its own', ['sentOnItsOwn' => true], [], null],
    'sent on its own, but it was held' => ['sent on its own', ['heldForOwner' => true], [], 'the reply was held for the owner, not sent on its own'],
    'sent on its own, but the conversation was handed over' => ['sent on its own', ['handedToOwner' => true], [], 'the conversation was handed to the owner, not answered on its own'],
    'sent on its own, but nothing was sent' => ['sent on its own', [], [], 'no reply was sent'],
    'held for the owner, and it was' => ['held for the owner', ['heldForOwner' => true], [], null],
    'held for the owner, but it was sent' => ['held for the owner', ['sentOnItsOwn' => true], [], 'the reply was sent on its own, not held for the owner'],
    'handed to the owner, and it was' => ['handed to the owner', ['handedToOwner' => true], [], null],
    'handed to the owner, but it was not' => ['handed to the owner', ['sentOnItsOwn' => true], [], 'the conversation was not handed to the owner'],
    'labeled promise, and it was' => ['labeled promise', ['label' => 'promise'], [], null],
    'labeled promise, but the label was faq_answer' => ['labeled promise', ['sentOnItsOwn' => true, 'label' => 'faq_answer'], [], 'the reply was labeled faq_answer, not promise'],
    'the owner price sentence, and it was carried' => ["carries the owner's price sentence for ac-repair", ['sentOnItsOwn' => true, 'sentText' => 'Hello. '.evaluatePriceSentence().' Thanks.'], [], null],
    'the owner price sentence, but it was not carried' => ["carries the owner's price sentence for ac-repair", ['sentOnItsOwn' => true, 'sentText' => 'It starts at $89.'], [], 'the reply did not carry the price sentence for AC repair'],
    'the owner price sentence for a service the folder has not' => ["carries the owner's price sentence for duct-cleaning", ['sentOnItsOwn' => true, 'sentText' => 'Hello.'], [], 'the folder has no service duct-cleaning for the price sentence check'],
    'the booking link, and it was carried' => ['carries the booking link', ['parts' => ['model_words', 'booking_link']], [], null],
    'the booking link, but it was not carried' => ['carries the booking link', ['parts' => ['model_words']], [], 'the reply did not carry the booking link'],
    'the visit line, and it was carried' => ['carries the visit line', ['parts' => ['visit_line']], [], null],
    'the visit line, but it was not carried' => ['carries the visit line', ['parts' => ['model_words']], [], 'the reply did not carry the visit line'],
    'owner alerted, and the owner was' => ['owner alerted', ['ownerAlerted' => true], [], null],
    'owner alerted, but the owner was not' => ['owner alerted', [], [], 'the owner was not alerted'],
    'booked, and it was' => ['booked', [], ['booked' => true], null],
    'booked, but it was not' => ['booked', [], [], 'no visit was booked'],
    'reminder queued, and it was' => ['reminder queued', [], ['reminderQueued' => true], null],
    'reminder queued, but it was not' => ['reminder queued', [], [], 'no reminder was queued for the visit'],
    'card updated, and it was' => ['card updated', [], ['cardShowsVisit' => true], null],
    'card updated, but it was not' => ['card updated', [], [], "the customer's card does not show the visit"],
    'records updated, and they were' => ['records updated', [], ['todayListsVisit' => true], null],
    'records updated, but they were not' => ['records updated', [], [], 'records/today.md does not list the visit'],
]);

it('judges each check against what the walk saw, and says so in plain words (D-09)', function (string $phrase, array $seen, array $world, ?string $words) {
    $check = WalkCheck::parse($phrase);

    expect($check->evaluate(evaluateSeen($seen), evaluateWorld($world), evaluateServices()))->toBe($words);
})->with('check evaluations');

/*
| Ian's ruling of 2026-11-23: the words for the two checks that read a reply when no reply was sent on its own and no
| draft was made. The case is told apart by what happened in the turn, the way the plan's own line for a sent reply is.
*/
dataset('rulings by Ian on a turn with no reply', fn () => [
    'held for the owner, when the conversation was handed over' => ['held for the owner', ['handedToOwner' => true], 'the conversation was handed to the owner, not held for the owner'],
    'held for the owner, when nothing was sent and no draft was made' => ['held for the owner', [], 'no reply was held for the owner'],
    'labeled promise, when the conversation was handed over' => ['labeled promise', ['handedToOwner' => true], 'the conversation was handed to the owner, so no reply was labeled promise'],
    'labeled promise, when nothing was sent and no draft was made' => ['labeled promise', [], 'no reply was made, so none was labeled promise'],
    'labeled scheduling, when nothing was sent and no draft was made' => ['labeled scheduling', [], 'no reply was made, so none was labeled scheduling'],
]);

it('uses the words Ian ruled when a turn has no reply to read (D-09)', function (string $phrase, array $seen, string $words) {
    expect(WalkCheck::parse($phrase)->evaluate(evaluateSeen($seen), evaluateWorld(), evaluateServices()))->toBe($words);
})->with('rulings by Ian on a turn with no reply');

it('reads the label of a held draft when no reply was sent (D-09)', function () {
    $seen = evaluateSeen(['heldForOwner' => true, 'label' => 'promise']);

    expect(WalkCheck::parse('labeled promise')->evaluate($seen, evaluateWorld(), evaluateServices()))->toBeNull();
});

it('never writes an exclamation point or a dash in a failure line (D-09)', function () {
    $lines = [];

    foreach (['sent on its own', 'held for the owner', 'handed to the owner', 'labeled promise', "carries the owner's price sentence for ac-repair", "carries the owner's price sentence for duct-cleaning", 'carries the booking link', 'carries the visit line', 'owner alerted', 'booked', 'reminder queued', 'card updated', 'records updated'] as $phrase) {
        foreach ([[], ['handedToOwner' => true], ['heldForOwner' => true], ['sentOnItsOwn' => true, 'label' => 'faq_answer', 'sentText' => 'Hello.']] as $seen) {
            $lines[] = WalkCheck::parse($phrase)->evaluate(evaluateSeen($seen), evaluateWorld(), evaluateServices());
        }
    }

    $words = implode("\n", array_filter($lines));

    expect($words)->not->toBe('')
        ->and($words)->not->toContain('!')
        ->and($words)->not->toContain("\u{2014}")
        ->and($words)->not->toContain("\u{2013}");
});

it('refuses to hold a turn that both handed over and made a draft (D-09)', function () {
    expect(fn () => evaluateSeen(['heldForOwner' => true, 'handedToOwner' => true]))->toThrow(LogicException::class);
});

/*
| Ian's ruling of 2026-11-23: the price sentence check also takes the service's default sentence, because code sends it
| as the owner's own sentence when no keyed sentence fits the customer's answers (PriceSentencePicker::pickDefault).
*/

/**
 * The folder's services with a keyed service that has a default sentence beside the one that has none.
 *
 * @param  array<string, mixed>|null  $default  The replacement service's price_default, or null for none.
 * @return array<string, array<string, mixed>>
 */
function evaluateServicesWithDefault(?array $default): array
{
    $replacement = [
        'name' => 'Replacement system',
        'price_sentences' => [
            ['answers' => ['unit_size' => 'Small (under 3 tons)'], 'sentence' => evaluateKeyedPriceSentence()],
        ],
    ];

    if ($default !== null) {
        $replacement['price_default'] = $default;
    }

    return [...evaluateServices(), 'system-replacement' => $replacement];
}

/**
 * The example business's keyed sentence for a smaller replacement system, as its service file holds it.
 */
function evaluateKeyedPriceSentence(): string
{
    return 'For a smaller system, under 3 tons, a replacement system typically runs $2,000 to $5,000 installed.';
}

/**
 * The example business's default replacement sentence, as its service file holds it.
 */
function evaluateDefaultPriceSentence(): string
{
    return "That's fine if you're not sure what you have. A replacement system typically runs \$2,000 to \$5,000 installed for a smaller unit and \$5,000 to \$10,000 for a larger one. We'll lock in an accurate quote once we see your setup.";
}

dataset('price checks with a default sentence', fn () => [
    'the default sentence, and it was carried' => ['system-replacement', 'Thanks. '.evaluateDefaultPriceSentence(), evaluateServicesWithDefault(['sentence' => evaluateDefaultPriceSentence()]), null],
    'a keyed sentence, beside a default' => ['system-replacement', evaluateKeyedPriceSentence(), evaluateServicesWithDefault(['sentence' => evaluateDefaultPriceSentence()]), null],
    'neither a keyed sentence nor the default' => ['system-replacement', 'It runs a few thousand dollars.', evaluateServicesWithDefault(['sentence' => evaluateDefaultPriceSentence()]), 'the reply did not carry the price sentence for Replacement system'],
    'the default reworded, not carried exactly' => ['system-replacement', str_replace('$2,000', '$2000', evaluateDefaultPriceSentence()), evaluateServicesWithDefault(['sentence' => evaluateDefaultPriceSentence()]), 'the reply did not carry the price sentence for Replacement system'],
    'an empty default sentence' => ['system-replacement', 'Hello.', evaluateServicesWithDefault(['sentence' => '']), 'the reply did not carry the price sentence for Replacement system'],
    'a default sentence that is not words' => ['system-replacement', 'Hello.', evaluateServicesWithDefault(['sentence' => ['nope']]), 'the reply did not carry the price sentence for Replacement system'],
    'no default, and no keyed sentence carried' => ['system-replacement', evaluateDefaultPriceSentence(), evaluateServicesWithDefault(null), 'the reply did not carry the price sentence for Replacement system'],
    'a service with no default, carrying its keyed sentence' => ['ac-repair', evaluatePriceSentence(), evaluateServicesWithDefault(['sentence' => evaluateDefaultPriceSentence()]), null],
    "a service with no default, carrying another service's default" => ['ac-repair', evaluateDefaultPriceSentence(), evaluateServicesWithDefault(['sentence' => evaluateDefaultPriceSentence()]), 'the reply did not carry the price sentence for AC repair'],
]);

it("accepts the owner's default price sentence where no keyed sentence fits (D-09, Ian's ruling 2026-11-23)", function (string $slug, string $sentText, array $services, ?string $words) {
    $seen = evaluateSeen(['sentOnItsOwn' => true, 'sentText' => $sentText]);

    expect(WalkCheck::parse("carries the owner's price sentence for {$slug}")->evaluate($seen, evaluateWorld(), $services))->toBe($words);
})->with('price checks with a default sentence');
