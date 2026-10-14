<?php

use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

const PRICE_SENTENCE_VALIDATION_AC_SENTENCE = 'AC repair starts with an $89 diagnostic visit, and we take the $89 off if you go ahead with the repair that visit.';

/*
| Each row: the service file, text to find in it, text to put in its place, and the exact line the owner must see.
*/
dataset('priceSentenceValidation broken sentences', [
    'an amount the file does not back' => ['business/services/ac-repair.md', '$89 diagnostic visit', '$99 diagnostic visit', "business/services/ac-repair.md: price_sentences item 1: the sentence's amounts (\$89, \$99) do not match price_cents (\$89)"],
    'a sentence that does not name its service' => ['business/services/ac-repair.md', 'AC repair starts', 'Repairs start', 'business/services/ac-repair.md: price_sentences item 1: the sentence must name the service (AC repair) so a wrong pick shows'],
]);

it('puts the diagnostic price sentence live on both repair services', function () {
    activateExampleFolder();

    $config = app(ActiveConfig::class);

    expect($config->service('ac-repair')['price_sentences'][0]['sentence'])->toBe(PRICE_SENTENCE_VALIDATION_AC_SENTENCE)
        ->and($config->service('furnace-repair')['price_sentences'][0]['sentence'])->toStartWith('Furnace repair starts with an $89 diagnostic visit');
});

it('refuses a price sentence its file does not back and keeps nothing live', function (string $file, string $search, string $replace, string $line) {
    expect(File::get(icmPath($file)))->toContain($search);

    breakFile($file, fn (string $text) => str_replace($search, $replace, $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line])
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(0);
})->with('priceSentenceValidation broken sentences');

it('finds the service name whatever the case and spacing', function () {
    breakFile('business/services/ac-repair.md', fn (string $text) => str_replace('AC repair starts', 'AC  REPAIR starts', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->service('ac-repair')['price_sentences'][0]['sentence'])->toStartWith('AC  REPAIR starts');
});
