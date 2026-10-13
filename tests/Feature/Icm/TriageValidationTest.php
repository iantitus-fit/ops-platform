<?php

use App\Enums\VersionStatus;
use App\Models\IcmVersion;
use Illuminate\Support\Facades\File;

const TRIAGE_OTHER_INTENT = "  - key: other\n    means: \"Anything else\"\n    sendable: false\n";
const TRIAGE_FAQ_INTENT = "  - key: faq\n    means: \"Asks a general question about the business\"\n    sendable: true\n";

/*
| Each row: file to change, text to find, text to put in its place, and the exact line the owner must see.
*/
dataset('broken triage files', [
    'no other intent' => ['rules/triage.md', TRIAGE_OTHER_INTENT, '', 'rules/triage.md: intents must include other: a message that fits nothing is never sent on its own'],
    'other marked sendable' => ['rules/triage.md', "means: \"Anything else\"\n    sendable: false", "means: \"Anything else\"\n    sendable: true", 'rules/triage.md: intents other must have sendable: false'],
    'the same intent twice' => ['rules/triage.md', TRIAGE_FAQ_INTENT, TRIAGE_FAQ_INTENT.TRIAGE_FAQ_INTENT, 'rules/triage.md: intents has more than one entry with key faq'],
    'no asks_for_person question' => ['rules/triage.md', "  asks_for_person: \"Is `customer_message` asking to talk with a real person instead of the assistant?\"\n", '', 'rules/triage.md: questions asks_for_person is missing, and it is required'],
    'a question the send rule does not use' => ['rules/triage.md', "instead of the assistant?\"\n---", "instead of the assistant?\"\n  urgency: \"Is it urgent?\"\n---", 'rules/triage.md: questions has urgency, which the send rule does not use; use price, promise, discount, complaint, life_safety, asks_for_person'],
    'sendable written as yes' => ['rules/triage.md', "means: \"Wants to book, move or confirm a visit\"\n    sendable: true", "means: \"Wants to book, move or confirm a visit\"\n    sendable: yes", 'rules/triage.md: intents item 2: sendable must be true or false (you wrote yes)'],
]);

it('rejects a triage file that would loosen the safety check and keeps nothing live', function (string $file, string $search, string $replace, string $line) {
    expect(File::get(icmPath($file)))->toContain($search);

    breakFile($file, fn (string $text) => str_replace($search, $replace, $text));

    $this->artisan('icm:sync')
        ->expectsOutputToContain($line)
        ->assertExitCode(1);

    expect(rejectedErrors())->toBe([$line])
        ->and(IcmVersion::where('status', VersionStatus::Active->value)->count())->toBe(0);
})->with('broken triage files');

it('accepts the example triage file as it is', function () {
    $this->artisan('icm:sync')->assertExitCode(0);
});

it('accepts other listed last', function () {
    $lastIntent = "    means: \"Describes something urgent or dangerous\"\n    sendable: false\n";

    breakFile('rules/triage.md', fn (string $text) => str_replace($lastIntent, $lastIntent.TRIAGE_OTHER_INTENT, str_replace(TRIAGE_OTHER_INTENT, '', $text)));

    $this->artisan('icm:sync')->assertExitCode(0);
});
