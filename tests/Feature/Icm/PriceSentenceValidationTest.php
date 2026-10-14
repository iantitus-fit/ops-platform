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
    'an amount spelled as dollars' => ['business/services/ac-repair.md', 'an $89 diagnostic visit', 'an 89 dollars diagnostic visit', 'business/services/ac-repair.md: price_sentences item 1: write every amount as $ and digits, such as $5,000, not dollars'],
    'an amount in k shorthand' => ['business/services/system-replacement.md', '$5,000 to $10,000 installed.', '$5k to $10,000 installed.', 'business/services/system-replacement.md: price_sentences item 2: write every amount as $ and digits, such as $5,000, not $5k'],
    'no amount at all' => ['business/services/ac-repair.md', '$89', '', 'business/services/ac-repair.md: price_sentences item 1: the sentence needs at least one amount written as $ and digits'],
    'a bare number beside a price word' => ['business/services/ac-repair.md', '$89 diagnostic visit', 'diagnostic fee of 89', 'business/services/ac-repair.md: price_sentences item 1: write every amount with a $ sign (89)'],
    'a promise' => ['business/services/ac-repair.md', 'visit, and', 'visit, guaranteed, and', 'business/services/ac-repair.md: price_sentences item 1: makes a promise (guaranteed); a price sentence goes out without you, so it cannot make a promise'],
    'a phrase the voice file bans' => ['business/services/ac-repair.md', 'AC repair starts', 'No problem. AC repair starts', 'business/services/ac-repair.md: price_sentences item 1: uses a phrase the voice file says never to use (No problem)'],
    'a fill-in' => ['business/services/ac-repair.md', 'AC repair starts', 'AC repair {owner_name} starts', 'business/services/ac-repair.md: price_sentences item 1: a price sentence cannot use fill-ins ({owner_name})'],
    'a price policy of none' => ['business/services/furnace-tune-up.md', "price_policy: none\n", "price_policy: none\nprice_sentences:\n  - sentence: \"Furnace tune-up starts at \$89.\"\n", 'business/services/furnace-tune-up.md: price_sentences: Furnace tune-up has price_policy none, so you quote it yourself; remove the price sentences'],
    'an answer key that is not an intake question' => ['business/services/system-replacement.md', 'unit_size: "Small (under 3 tons)"', 'size: "Small (under 3 tons)"', "business/services/system-replacement.md: price_sentences item 1: answers uses size, which is not one of this service's intake questions"],
    'an answer that is not one of the options' => ['business/services/system-replacement.md', 'unit_size: "Small (under 3 tons)"', 'unit_size: Medium', "business/services/system-replacement.md: price_sentences item 1: answers gives unit_size the option Medium, which is not one of that question's options"],
    'answers written twice' => ['business/services/system-replacement.md', 'unit_size: "Large (3 tons and up)"', 'unit_size: "Small (under 3 tons)"', 'business/services/system-replacement.md: price_sentences item 2: answers repeats item 1'],
    'answers on different questions' => ['business/services/system-replacement.md', 'unit_size: "Large (3 tons and up)"', 'condition: "Still running"', 'business/services/system-replacement.md: price_sentences item 2: answers must use the same questions as item 1'],
    'keyed answers with no amounts' => ['business/services/system-replacement.md', "    amounts_cents: [200000, 500000]\n", '', 'business/services/system-replacement.md: price_sentences item 1: amounts_cents is missing, and a sentence keyed to answers needs it'],
    'a second sentence beside an unkeyed one' => ['business/services/ac-repair.md', "visit.\"\n---", "visit.\"\n  - sentence: \"AC repair starts with an \$89 diagnostic visit, so you know what to expect.\"\n---", 'business/services/ac-repair.md: price_sentences item 2: a sentence without answers must be the only price sentence'],
    'a default beside an unkeyed sentence' => ['business/services/ac-repair.md', "visit.\"\n---", "visit.\"\nprice_default:\n  sentence: \"AC repair starts with an \$89 diagnostic visit.\"\n  amounts_cents: [8900]\n---", 'business/services/ac-repair.md: price_default: a default sentence needs price sentences keyed to answers'],
    'a default that leaves out an amount' => ['business/services/system-replacement.md', 'amounts_cents: [200000, 500000, 1000000]', 'amounts_cents: [200000, 500000]', "business/services/system-replacement.md: price_default: the sentence's amounts (\$2,000, \$5,000, \$10,000) do not match amounts_cents (\$2,000, \$5,000)"],
    'a default with no amounts' => ['business/services/system-replacement.md', "  amounts_cents: [200000, 500000, 1000000]\n", '', 'business/services/system-replacement.md: price_default: amounts_cents is missing, and it is required'],
    'a default with a promise' => ['business/services/system-replacement.md', "We'll lock in", 'We definitely lock in', 'business/services/system-replacement.md: price_default: makes a promise (definitely); a price sentence goes out without you, so it cannot make a promise'],
    'answers that are not a group' => ['business/services/system-replacement.md', "answers:\n      unit_size: \"Small (under 3 tons)\"", 'answers: Small', 'business/services/system-replacement.md: price_sentences item 1: answers must be intake questions, each with one of its options (you wrote Small)'],
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

it('removes the default sentence and refuses a default with no sentence', function () {
    breakFile('business/services/system-replacement.md', fn (string $text) => preg_replace('/(price_default:\n)  sentence: .*\n/', '$1', $text));

    $this->artisan('icm:sync')->assertExitCode(1);

    expect(rejectedErrors())->toBe(['business/services/system-replacement.md: price_default: sentence is missing, and it is required']);
});

it('lets a price sentence say take the amount off or waived, because the owner may name their own terms', function () {
    breakFile('business/services/ac-repair.md', fn (string $text) => str_replace('and we take the $89 off if', 'and the $89 is waived if', $text));

    activateExampleFolder();

    expect(app(ActiveConfig::class)->service('ac-repair')['price_sentences'][0]['sentence'])->toContain('is waived');
});

it('puts the Replacement system live with sentences keyed to unit size and a default', function () {
    activateExampleFolder();

    $service = app(ActiveConfig::class)->service('system-replacement');

    expect($service['name'])->toBe('Replacement system')
        ->and(array_column($service['price_sentences'], 'amounts_cents'))->toBe([[200000, 500000], [500000, 1000000]])
        ->and($service['price_sentences'][0]['answers'])->toBe(['unit_size' => 'Small (under 3 tons)'])
        ->and($service['price_default']['amounts_cents'])->toBe([200000, 500000, 1000000])
        ->and($service['price_default']['sentence'])->toStartWith("That's fine if you're not sure what you have.");
});

it('keeps the furnace tune-up without price sentences and never edits the voice file', function () {
    activateExampleFolder();

    $config = app(ActiveConfig::class);

    expect($config->service('furnace-tune-up')['price_policy'])->toBe('none')
        ->and($config->service('furnace-tune-up'))->not->toHaveKey('price_sentences');
});
