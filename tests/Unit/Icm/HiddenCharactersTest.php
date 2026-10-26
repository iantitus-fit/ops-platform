<?php

use App\Icm\HiddenCharacters;
use App\Icm\ValidationReport;

dataset('names with a hidden character', [
    'a line break' => ["a\nb"],
    'a tab' => ["a\tb"],
    'a text-direction override' => ["a\u{202E}b"],
    'a zero-width space' => ["a\u{200B}b"],
    'a zero-width joiner (D-25)' => ["a\u{200D}b"],
    'a zero-width non-joiner (D-25)' => ["a\u{200C}b"],
    'a line separator' => ["a\u{2028}b"],
    'text that is not valid UTF-8' => ["a\xFFb"],
]);

dataset('names that are fine', [
    'café composed (NFC)' => ["caf\u{00E9}"],
    'café decomposed (NFD)' => ["cafe\u{0301}"],
    'a no-break space, which is a space and not a hidden character' => ["a\u{00A0}b"],
    'an emoji with the variation selector' => ["sun\u{2600}\u{FE0F}"],
    'a plain name' => ['business/hours.md'],
]);

it('finds a hidden character in a name', function (string $name) {
    expect(HiddenCharacters::found($name))->toBeTrue();
})->with('names with a hidden character');

it('lets an ordinary name pass, composed or decomposed', function (string $name) {
    expect(HiddenCharacters::found($name))->toBeFalse();
})->with('names that are fine');

it('marks every kind of line break as [line break]', function (string $text) {
    expect(HiddenCharacters::marked($text))->toBe('a[line break]b');
})->with([
    'CR LF as one break' => ["a\r\nb"],
    'LF' => ["a\nb"],
    'CR' => ["a\rb"],
    'NEL' => ["a\u{0085}b"],
    'line separator' => ["a\u{2028}b"],
    'paragraph separator' => ["a\u{2029}b"],
]);

it('marks every other hidden character as [hidden character]', function (string $text) {
    expect(HiddenCharacters::marked($text))->toBe('a[hidden character]b');
})->with([
    'a tab' => ["a\tb"],
    'a text-direction override' => ["a\u{202E}b"],
    'a zero-width space' => ["a\u{200B}b"],
    'a byte order mark' => ["a\u{FEFF}b"],
]);

it('leaves text without a hidden character exactly as it was', function () {
    expect(HiddenCharacters::marked("caf\u{00E9} and cafe\u{0301}"))->toBe("caf\u{00E9} and cafe\u{0301}");
});

it('turns text that is not valid UTF-8 into valid, non-empty text', function () {
    $marked = HiddenCharacters::marked("x\xFF");

    expect($marked)->not->toBe('')
        ->and(mb_check_encoding($marked, 'UTF-8'))->toBeTrue();
});

it('never prints an empty or invalid line for a file name that is not valid UTF-8', function () {
    $lines = (new ValidationReport([['file' => "bad\xFF.md", 'field' => null, 'message' => 'm']]))->lines();

    expect($lines)->toHaveCount(1)
        ->and($lines[0])->not->toBe('')
        ->and(mb_check_encoding($lines[0], 'UTF-8'))->toBeTrue();
});
