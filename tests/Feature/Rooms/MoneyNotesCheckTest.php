<?php

use App\Icm\ActiveConfig;
use Illuminate\Support\Facades\File;

/**
 * The money notes check (ROOM-14; 05 D-16, D-22, D-26, D-28): the sync refuses a notes file that holds what looks like a card
 * number (13 to 19 digits passing the card check digit, phones skipped) or any 9-digit number however it is grouped
 * (bank routing, Social Security, employer ID), except a ZIP+4 written 60601-1234. The refusal is one fixed sentence that
 * never repeats a digit of what was found, and the previous version stays live.
 *
 * Every number in this file is fictional or a published test number (the card test numbers are the payment networks' own).
 */
const MONEY_NOTES_REFUSAL = 'rooms/money/notes.md: this file has what looks like a card, bank routing, Social Security or tax ID number; account, card and ID numbers never go in the folder';

/**
 * The line for a notes file the check cannot finish reading (D-30): a scan that cannot finish refuses.
 */
const MONEY_NOTES_TOO_LONG = 'rooms/money/notes.md: this file is too long for the number check to read; shorten it or split it';

/**
 * Write rooms/money/notes.md with the given body and, optionally, one extra front matter line.
 */
function writeMoneyNotes(string $body, string $extraFrontMatter = ''): void
{
    $extra = $extraFrontMatter === '' ? '' : $extraFrontMatter."\n";

    writeIcmFile('rooms/money/notes.md', "---\ntype: money_notes\n{$extra}---\n\n{$body}\n");
}

it('refuses a number that looks like a card, routing, Social Security or tax ID number', function (string $number) {
    $live = activateExampleFolder();
    writeMoneyNotes("We keep the reference {$number} on the deposit slip.");

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([MONEY_NOTES_REFUSAL])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
})->with([
    'visa together' => '4111111111111111',
    'visa spaces' => '4111 1111 1111 1111',
    'visa dashes' => '4111-1111-1111-1111',
    'amex together' => '378282246310005',
    'amex spaced' => '3782 822463 10005',
    'discover' => '6011111111111117',
    'mastercard' => '5555555555554444',
    'diners' => '30569309025904',
    'visa thirteen digits' => '4222222222222',
    'nine together' => '123456789',
    'social security shape' => '123-45-6789',
    'employer id shape' => '12-3456789',
    'social security with spaces' => '123 45 6789',
    'nine in threes' => '123-456-789',
    'routing shape' => '021000021',
    'nine digits libphonenumber reads as a phone' => '113108819',
    'zip plus four with a space' => '60601 1234',
    'zip plus four together' => '606011234',
    'nine digits with a plus that is not a phone' => '+123456789',
]);

it('lets through what is not a card or a 9-digit number', function (string $text) {
    writeMoneyNotes("Reach the office at {$text} any weekday.");

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->filesOfType('money_notes'))->toHaveCount(1);
})->with([
    'phone with dashes' => '312-555-0147',
    'phone with brackets' => '(312) 555-0147',
    'phone with country code and spaces' => '+1 312 555 0147',
    'phone in e164' => '+13125550147',
    'spanish phone with a nine digit part' => '+34 912345678',
    'faroese phone with a plus and nine digits' => '+298 302010',
    // D-28 changed this input: 312-555-0147 773-555-0100 is one 20-digit span, and its first four groups (3125550147773)
    // pass the card check digit and are not a phone, so it is now refused. Two phones whose groups make no such run still pass.
    'two phones side by side' => '312-555-0100 773-555-0147',
    'zip plus four' => '60601-1234',
    'a date' => '2026-10-25',
    'sixteen digits failing the check digit' => '4111111111111112',
    'twenty two digits' => '1234567890123456789012',
    'eight digits' => '12345678',
]);

it('lets through an empty body and a body with no digit', function (string $body) {
    writeMoneyNotes($body);

    $this->artisan('icm:sync')->assertExitCode(0);
})->with([
    'empty body' => [''],
    'no digit' => ['Customers pay by card or by check.'],
]);

it('keeps spans apart when a comma separates them', function () {
    $live = activateExampleFolder();
    writeMoneyNotes('Examples: 123-45-6789, 312-555-0147.');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([MONEY_NOTES_REFUSAL])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('gives one digit-free line for a file holding several refused numbers and keeps them out of the changelog', function () {
    activateExampleFolder();
    writeMoneyNotes('One is 4111 1111 1111 1111, another 123-45-6789 and a third 12-3456789.');

    $this->artisan('icm:sync')->assertExitCode(1);

    $errors = rejectedErrors();

    expect($errors)->toBe([MONEY_NOTES_REFUSAL])
        ->and($errors[0])->not->toMatch('/\d/');

    $changelog = File::get(icmPath('changelog.md'));

    expect($changelog)->not->toContain('4111')
        ->and($changelog)->not->toContain('6789')
        ->and($changelog)->not->toContain('3456789');
});

it('checks only the money notes file', function () {
    $live = activateExampleFolder();

    breakFile('business/faq.md', fn (string $contents): string => $contents."\nThe fictional reference 123-45-6789 is only an example.\n");

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->versionId())->not->toBe($live->id);
});

it('catches a number written in the front matter', function () {
    $live = activateExampleFolder();
    writeMoneyNotes('Customers pay by card or by check.', 'account: 123456789');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([MONEY_NOTES_REFUSAL])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('refuses a card or a 9-digit number whose groups are joined by a dot, tab, Unicode space, Unicode dash or one line break (D-28)', function (string $text) {
    $live = activateExampleFolder();
    writeMoneyNotes($text);

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([MONEY_NOTES_REFUSAL])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
})->with([
    'card with dots' => '4111.1111.1111.1111',
    'card wrapped across one line break' => "4111 1111\n1111 1111",
    'card wrapped across a windows line break' => "4111 1111\r\n1111 1111",
    'card with non-breaking spaces' => "4111\u{00A0}1111\u{00A0}1111\u{00A0}1111",
    'card with en dashes' => "4111\u{2013}1111\u{2013}1111\u{2013}1111",
    'card with tabs' => "4111\t1111\t1111\t1111",
    'nine digits with dots' => '123.45.6789',
    'nine digits with a non-breaking space' => "123\u{00A0}45\u{00A0}6789",
]);

it('refuses a card or a 9-digit number that follows another number in the same span (D-28)', function (string $text) {
    $live = activateExampleFolder();
    writeMoneyNotes($text);

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([MONEY_NOTES_REFUSAL])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
})->with([
    'card after a date' => 'Paid 2026-10-25 4111 1111 1111 1111',
    'card after a date, line break between groups' => "Paid 2026-10-25 4111 1111\n1111 1111",
    'nine digits after two dates' => '2026-10-25 2026-10-26 123 45 6789',
    'nine digits and a card after a date' => 'Paid 2026-10-25 123-45-6789 4111 1111 1111 1111',
    'two phones whose first four groups pass the check digit (the accepted false refusal, D-22 and D-28)' => '312-555-0147 773-555-0100',
]);

it('refuses a card or a 9-digit number whose groups are joined by a run of horizontal whitespace holding at most one line break (D-28)', function (string $text) {
    $live = activateExampleFolder();
    writeMoneyNotes($text);

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([MONEY_NOTES_REFUSAL])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
})->with([
    'two spaces inside a card' => '4111  1111  1111  1111',
    'two spaces inside nine digits' => '123  45  6789',
    'spaces, tabs and non-breaking spaces mixed inside a card' => "4111 \t 1111\u{00A0} 1111\t\t1111",
    'a markdown hard break inside a card' => "4111 1111  \n1111 1111",
    'a list item whose next line is indented' => "- Deposit reference 4111 1111\n  1111 1111",
    'nine digits across an indented line' => "- Reference 123 45\n    6789",
]);

it('still splits spans at a blank line, at two different separators in a row, and at a comma (D-28)', function (string $text) {
    writeMoneyNotes($text);

    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->filesOfType('money_notes'))->toHaveCount(1);
})->with([
    'a space and a dot' => '4111 .1111 .1111 .1111',
    'a blank line' => "4111 1111\n\n1111 1111",
    'a blank line holding spaces' => "4111 1111\n  \n1111 1111",
    'a blank line after a markdown hard break' => "4111 1111  \n\n1111 1111",
    'zip plus four alone' => '60601-1234',
    'a date followed by a phone' => '2026-10-25 2026-10-26 312-555-0100',
    'a faroese phone before two dates' => '+298 302010 2026-10-25 2026-10-26',
]);

it('refuses a file the number check cannot finish reading, even one with a card in it, and keeps the previous version live (D-30)', function () {
    $live = activateExampleFolder();

    // 100,000 two-digit groups joined by single spaces make one 300 KB span, past what the pattern engine can match
    // with or without its JIT (it stops near 10,000 groups with JIT, near 50,000 without). The card at the end used to
    // pass with it.
    writeMoneyNotes('Deposit slips: '.str_repeat('12 ', 100_000).'4111 1111 1111 1111');

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe([MONEY_NOTES_TOO_LONG])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('lets the example money notes through the widened check', function () {
    $this->artisan('icm:sync')->assertExitCode(0);

    expect(app(ActiveConfig::class)->filesOfType('money_notes'))->toHaveCount(1);
});
