<?php

use App\Ai\AgentFile;
use App\Ai\Agents\FrontDeskAgent;
use App\Ai\FrontDeskPrompt;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * D-39 as amended (Ian, 2026-11-11, option A; UAT gaps G-08-4 and G-08-4b): what the model reads is corrected, and a
 * Booking section, built from the same conditions the send uses, tells it what code adds to a reply. A sentence in the
 * section is there only when code will do what it says.
 */
it('tells a booked customer\'s run that the system adds the visit\'s day and time (D-39 amended)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));
    User::factory()->withPhone('+12025550199')->create();

    frontDeskCustomerTexts('What time are you coming?');

    Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();

    fakeClassifier()->answerReading('scheduling');
    scriptFrontDeskReplies(['reply' => 'Thanks for checking.', 'label' => 'scheduling', 'service_slug' => 'ac-repair']);
    runQueuedFrontDeskJobs();

    FrontDeskAgent::assertPrompted(function ($prompt): bool {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, "## Booking\n\n".FrontDeskPrompt::BOOKING_VISIT_BOOKED)
            && str_contains($instructions, 'When you label a reply scheduling and the reply is sent as it is, the system adds a line after your words, in the owner\'s words, with that visit\'s service, day and time, so your own words do not give the day or the time. When the reply is held for the owner, the owner decides whether that line is added.')
            && str_contains($instructions, 'A visit the customer card lists as booked was recorded by the business\'s own booking system, so it is the business\'s booking.')
            && strpos($instructions, '## Customer card') < strpos($instructions, '## Booking')
            && strpos($instructions, '## Booking') < strpos($instructions, '## Right now')
            && stripos($instructions, '/book/') === false
            && ! str_contains($instructions, 'The business books these services online');
    });
});

/**
 * The text from the "## Booking" heading up to the next section heading, or null when the instructions have none.
 */
function bookingSectionOf(string $instructions): ?string
{
    $start = strpos($instructions, '## Booking');

    if ($start === false) {
        return null;
    }

    $rest = substr($instructions, $start);
    $end = strpos($rest, "\n\n## ");

    return $end === false ? $rest : substr($rest, 0, $end);
}

/**
 * The service files of the temporary folder, set to book online or not by slug: every slug in $online books online and
 * every other service does not. Call before the folder is activated.
 *
 * @param  list<string>  $online
 */
function bookingSectionBookOnlineOnly(array $online): void
{
    foreach (File::files(icmPath('business/services')) as $file) {
        $relative = 'business/services/'.$file->getFilename();
        $books = in_array($file->getBasename('.md'), $online, true);

        breakFile($relative, fn (string $text): string => preg_replace('/^book_online: (true|false)$/m', 'book_online: '.($books ? 'true' : 'false'), $text));
    }
}

/**
 * The live example folder and Dana, who has an open conversation and no messages yet. Returns the sms agent file, Dana
 * and the instructions-building prompt.
 *
 * @return array{AgentFile, Contact, FrontDeskPrompt}
 */
function bookingSectionDana(): array
{
    activateExampleFolder();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    Conversation::factory()->for($contact)->create();

    return [AgentFile::forChannel(app(ActiveConfig::class), 'sms'), $contact, app(FrontDeskPrompt::class)];
}

it('tells an unbooked customer\'s run which services book online and that the page books at once (G-08-4b)', function () {
    [$agent, $contact, $prompt] = bookingSectionDana();

    $instructions = $prompt->instructions($agent, $contact);
    $section = bookingSectionOf($instructions);

    expect($section)->toBe("## Booking\n\n".str_replace('{services}', 'ac-repair, furnace-repair and furnace-tune-up', FrontDeskPrompt::BOOKING_ONLINE))
        ->and($section)->toContain('When you label a reply scheduling, it is about one of them and the reply is sent as it is, the system adds a line after your words, in the owner\'s words, that invites the customer to pick a time and holds the booking link. When the reply is held for the owner, the owner decides whether that line is added.')
        ->and($section)->not->toContain('system-replacement')
        ->and($instructions)->not->toContain(FrontDeskPrompt::BOOKING_VISIT_BOOKED);
});

it('lists one service on its own', function () {
    bookingSectionBookOnlineOnly(['ac-repair']);
    [$agent, $contact, $prompt] = bookingSectionDana();

    $section = bookingSectionOf($prompt->instructions($agent, $contact));

    expect($section)->toContain('The business books these services online: ac-repair. ')
        ->and($section)->not->toContain('ac-repair and');
});

it('lists two services as a and b', function () {
    bookingSectionBookOnlineOnly(['ac-repair', 'furnace-repair']);
    [$agent, $contact, $prompt] = bookingSectionDana();

    expect(bookingSectionOf($prompt->instructions($agent, $contact)))->toContain('online: ac-repair and furnace-repair. ');
});

it('leaves the Booking section out when booking does not apply (D-39 amended)', function (string $case) {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));

    match ($case) {
        'the room off', 'a visit booked ahead with the room off' => File::deleteDirectory(icmPath('rooms/schedule_and_booking')),
        'no booking file' => File::delete(icmPath('rooms/schedule_and_booking/booking.md')),
        'no service books online and no visit is booked' => bookingSectionBookOnlineOnly([]),
        'a visit booked ahead and a version synced before visit_line' => null,
    };

    [$agent, $contact, $prompt] = bookingSectionDana();

    if (str_starts_with($case, 'a visit booked ahead')) {
        Appointment::factory()->for($contact)->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();
    }

    if ($case === 'a visit booked ahead and a version synced before visit_line') {
        $version = app(ActiveConfig::class)->version();
        $compiled = $version->compiled;
        $path = array_values($compiled['index']['booking'])[0];
        unset($compiled['files'][$path]['front_matter']['visit_line']);
        $version->update(['compiled' => $compiled]);
        app(ActiveConfig::class)->refresh();
    }

    expect($prompt->instructions($agent, $contact))->not->toContain('## Booking');
})->with([
    'the room off',
    'no booking file',
    'no service books online and no visit is booked',
    'a visit booked ahead with the room off',
    'a visit booked ahead and a version synced before visit_line',
]);

it('puts the Booking section after the card and before the question already with the owner', function () {
    [$agent, $contact, $prompt] = bookingSectionDana();

    $first = Message::factory()->for(Conversation::sole())->create(['body' => 'How much for a new unit?']);
    $waiting = Message::factory()->for(Conversation::sole())->draft()->create([
        'body' => 'Mike will text you a price.',
        'meta' => ['in_reply_to' => [$first->id]],
    ]);

    $instructions = $prompt->instructions($agent, $contact, $waiting);

    expect(strpos($instructions, '## Customer card'))->toBeLessThan(strpos($instructions, '## Booking'))
        ->and(strpos($instructions, '## Booking'))->toBeLessThan(strpos($instructions, '## Already with the owner'))
        ->and(strpos($instructions, '## Already with the owner'))->toBeLessThan(strpos($instructions, '## Right now'));
});

it('writes the Booking section with no booking address, exclamation point or em dash', function (string $text) {
    expect(stripos($text, '/book/'))->toBeFalse()
        ->and($text)->not->toContain('!')
        ->and($text)->not->toContain("\u{2014}");
})->with(fn (): array => [
    'the visit variant' => FrontDeskPrompt::BOOKING_VISIT_BOOKED,
    'the online variant' => FrontDeskPrompt::BOOKING_ONLINE,
    'the rendered visit section' => "## Booking\n\n".FrontDeskPrompt::BOOKING_VISIT_BOOKED,
    'the rendered online section' => "## Booking\n\n".str_replace('{services}', 'ac-repair, furnace-repair and furnace-tune-up', FrontDeskPrompt::BOOKING_ONLINE),
]);

it('keeps booking.md out of what the model reads', function () {
    [$agent, $contact, $prompt] = bookingSectionDana();

    $instructions = $prompt->instructions($agent, $contact);

    expect(collect($agent->loads())->filter(fn (string $path): bool => str_starts_with($path, 'rooms/')))->toBeEmpty()
        ->and($instructions)->not->toContain('link_line')
        ->and($instructions)->not->toContain('Pick a time that works for you here');
});
