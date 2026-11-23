<?php

use App\Icm\Walks\ScratchWorld;
use App\Icm\Walks\WalkFile;
use App\Icm\Walks\WalkOutcome;
use App\Icm\Walks\WalkRunner;
use App\Icm\Walks\WalkTape;
use App\Models\Appointment;
use App\Models\BookingSend;
use Carbon\CarbonImmutable;

require_once __DIR__.'/WalkHelpers.php';

/**
 * A walk with a customer text that should be answered on its own and one that should be held for the owner. The checks
 * under the second text are the two the plan names, in the order the owner wrote them.
 *
 * @param  int  $turns  How many customer texts the walk has: 2, or 3 to prove a later turn is never played.
 */
function intakeWalk(int $turns = 2): WalkFile
{
    $texts = [
        "  - says: \"My AC is blowing warm air\"\n    expect: [sent on its own, labeled intake_question]",
        "  - says: \"Can you guarantee it is fixed today?\"\n    expect: [held for the owner, labeled promise]",
        '  - says: "Thanks, see you then"',
    ];

    $script = implode("\n", array_slice($texts, 0, $turns));

    return walkFileFrom("---\ntype: walk_test\nname: Two turns that should hold\npersona: A customer who texts twice\nchannel: sms\nstart: \"2026-12-01 10:00\"\nscript:\n{$script}\n---\nA walk through the front desk.\n");
}

/**
 * The tape for the walk above: turn 1 asks an intake question, turn 2 is answered with the label given.
 */
function intakeTape(string $secondLabel): WalkTape
{
    return walkTape([
        1 => [
            'agent' => [walkReply(['reply' => 'Thanks for texting. What is the AC doing?', 'label' => 'intake_question', 'service_slug' => 'ac-repair'])],
            'readings' => [walkScores()],
            'words' => [walkScores()],
        ],
        2 => [
            'agent' => [walkReply(['reply' => 'A technician looks at it during the visit.', 'label' => $secondLabel, 'service_slug' => 'ac-repair'])],
            'readings' => [walkScores()],
            'words' => [walkScores()],
        ],
    ]);
}

it('fails at the first check that fails, naming its turn (D-09, ruling 2)', function () {
    $compiled = activateExampleFolder()->compiled;

    $result = app(WalkRunner::class)->replay(intakeWalk(), $compiled, intakeTape('faq_answer'));

    expect($result->outcome)->toBe(WalkOutcome::Failed)
        ->and($result->failure)->not->toBeNull()
        ->and($result->failure->turn)->toBe(2)
        ->and($result->failure->words)->toBe('the reply was sent on its own, not held for the owner')
        ->and($result->turns)->toHaveCount(2)
        ->and($result->turns[1]->label)->toBe('faq_answer');
});

it('plays no turn after the one that failed (D-09)', function () {
    $compiled = activateExampleFolder()->compiled;

    $result = app(WalkRunner::class)->replay(intakeWalk(3), $compiled, intakeTape('faq_answer'));

    expect($result->outcome)->toBe(WalkOutcome::Failed)
        ->and($result->exhausted)->toBeNull()
        ->and($result->turns)->toHaveCount(2);
});

it('passes when every check holds (D-09)', function () {
    $compiled = activateExampleFolder()->compiled;

    $result = app(WalkRunner::class)->replay(intakeWalk(), $compiled, intakeTape('promise'));

    expect($result->outcome)->toBe(WalkOutcome::Passed)
        ->and($result->failure)->toBeNull()
        ->and($result->turns)->toHaveCount(2)
        ->and($result->turns[0]->sentOnItsOwn)->toBeTrue()
        ->and($result->turns[0]->label)->toBe('intake_question')
        ->and($result->turns[1]->heldForOwner)->toBeTrue()
        ->and($result->turns[1]->sentOnItsOwn)->toBeFalse()
        ->and($result->turns[1]->label)->toBe('promise');
});

/**
 * The example folder's compiled copy with one file's front matter changed in place, the way an owner's edit would arrive.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>  $change  Takes the file's front matter and returns it changed.
 * @return array<string, mixed>
 */
function candidateWith(string $type, ?string $slug, callable $change): array
{
    $compiled = activateExampleFolder()->compiled;
    $path = $slug === null ? $compiled['index'][$type] : $compiled['index'][$type][$slug];
    $compiled['files'][$path]['front_matter'] = $change($compiled['files'][$path]['front_matter']);

    return $compiled;
}

it('books the named time and queues its reminder (D-09)', function () {
    $compiled = activateExampleFolder()->compiled;
    $seen = [];

    $result = app(WalkRunner::class)->replay(bookingWalk('2026-12-02 11:00', ['booked', 'owner alerted', 'reminder queued']), $compiled, bookingTape(), function (ScratchWorld $world) use (&$seen): void {
        $seen = [
            'visits' => Appointment::query()->get()->map(fn (Appointment $visit): string => $visit->starts_at->setTimezone('America/Chicago')->format('Y-m-d H:i'))->all(),
            'reminders' => BookingSend::query()->where('lead_minutes', 1440)->whereNull('sent_at')->count(),
        ];
    });

    expect($result->outcome)->toBe(WalkOutcome::Passed)
        ->and($result->failure)->toBeNull()
        ->and($seen['visits'])->toBe(['2026-12-02 11:00'])
        ->and($seen['reminders'])->toBe(1);
});

it('fails when the expected time is no longer offered (criterion 2, D-09)', function () {
    $compiled = candidateWith('hours', null, function (array $hours): array {
        $hours['weekly']['wed'] = 'closed';

        return $hours;
    });
    $visits = null;

    $result = app(WalkRunner::class)->replay(bookingWalk('2026-12-02 11:00', ['booked']), $compiled, bookingTape(), function () use (&$visits): void {
        $visits = Appointment::query()->count();
    });

    expect($result->outcome)->toBe(WalkOutcome::Failed)
        ->and($result->failure->turn)->toBe(4)
        ->and($result->failure->words)->toBe('Wednesday, December 2 at 11:00 AM was not offered')
        ->and($visits)->toBe(0);
});

it('books the earliest time offered (D-09)', function () {
    $compiled = activateExampleFolder()->compiled;
    $this->travelTo(CarbonImmutable::parse('2026-12-01 07:00', 'America/Chicago'));
    $visits = [];

    $result = app(WalkRunner::class)->replay(bookingWalk('the earliest time offered', ['booked'], null), $compiled, bookingTape(), function () use (&$visits): void {
        $visits = Appointment::query()->get()->map(fn (Appointment $visit): string => $visit->starts_at->setTimezone('America/Chicago')->format('Y-m-d H:i'))->all();
    });

    expect($result->outcome)->toBe(WalkOutcome::Passed)
        ->and($visits)->toBe(['2026-12-01 12:00']);
});

it('fails when no booking link was sent (D-09)', function () {
    $compiled = activateExampleFolder()->compiled;
    $walk = walkFileFrom("---\ntype: walk_test\nname: A booking with no link\npersona: A customer who books too soon\nchannel: sms\nstart: \"2026-12-01 10:00\"\nscript:\n  - says: \"Hi, is anyone there?\"\n  - books: the earliest time offered\n---\nA walk.\n");
    $tape = walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Thanks for texting. What can we help with?', 'label' => 'faq_answer'])],
        'readings' => [walkScores('faq')],
        'words' => [walkScores('faq')],
    ]]);

    $result = app(WalkRunner::class)->replay($walk, $compiled, $tape);

    expect($result->outcome)->toBe(WalkOutcome::Failed)
        ->and($result->failure->turn)->toBe(2)
        ->and($result->failure->words)->toBe('no booking link had been sent to book with');
});

/**
 * A walk where the customer asks the price of an AC repair and the owner expects the owner's own sentence for it.
 */
function priceWalk(): WalkFile
{
    return walkFileFrom("---\ntype: walk_test\nname: Dana asks the price\npersona: A homeowner asking about cost\nchannel: sms\nstart: \"2026-12-01 10:00\"\nscript:\n  - says: \"How much is an AC repair?\"\n    expect: [carries the owner's price sentence for ac-repair]\n---\nA walk.\n");
}

/**
 * The one recorded reply for priceWalk(): the model's own words and a flag that the customer asked the price.
 */
function priceTape(): WalkTape
{
    return walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Happy to help with that.', 'label' => 'faq_answer', 'service_slug' => 'ac-repair', 'price_services' => ['ac-repair']])],
        'readings' => [walkScores('faq')],
        'words' => [walkScores('faq')],
    ]]);
}

it('passes the price sentence check against the example folder (criterion 2, D-09)', function () {
    $result = app(WalkRunner::class)->replay(priceWalk(), activateExampleFolder()->compiled, priceTape());

    expect($result->outcome)->toBe(WalkOutcome::Passed)
        ->and($result->turns[0]->sentText)->toContain('AC repair starts with an $89 diagnostic visit');
});

it('fails a price sentence the candidate no longer has, whatever words were recorded (criterion 2, D-09)', function () {
    $compiled = candidateWith('service', 'ac-repair', function (array $service): array {
        $service['price_policy'] = 'none';
        unset($service['price_cents'], $service['price_sentences']);

        return $service;
    });

    $result = app(WalkRunner::class)->replay(priceWalk(), $compiled, priceTape());

    expect($result->outcome)->toBe(WalkOutcome::Failed)
        ->and($result->failure->turn)->toBe(1)
        ->and($result->failure->words)->toBe('the reply did not carry the price sentence for AC repair');
});

it('passes a price sentence the candidate only reworded, because code sends its own sentence (D-09)', function () {
    $compiled = candidateWith('service', 'ac-repair', function (array $service): array {
        $service['price_sentences'] = [['sentence' => 'An AC repair visit begins at $89 for the diagnostic, taken off the repair if you go ahead.']];

        return $service;
    });

    $result = app(WalkRunner::class)->replay(priceWalk(), $compiled, priceTape());

    expect($result->outcome)->toBe(WalkOutcome::Passed)
        ->and($result->turns[0]->sentText)->toContain('An AC repair visit begins at $89 for the diagnostic');
});

it('shows the visit on the card and in records after a wait (D-09)', function () {
    $compiled = activateExampleFolder()->compiled;

    $result = app(WalkRunner::class)->replay(bookingWalk('2026-12-02 11:00', ['card updated', 'records updated'], '2026-12-01 10:00', '1 day'), $compiled, bookingTape());

    expect($result->outcome)->toBe(WalkOutcome::Passed)
        ->and($result->failure)->toBeNull();
});

it('fails the card and records checks when nothing was booked (D-09)', function () {
    $compiled = activateExampleFolder()->compiled;
    $walk = walkFileFrom("---\ntype: walk_test\nname: Nobody books\npersona: A customer who only texts\nchannel: sms\nstart: \"2026-12-01 10:00\"\nscript:\n  - says: \"Hi, is anyone there?\"\n  - wait: \"1 day\"\n    expect: [card updated]\n---\nA walk.\n");
    $tape = walkTape([1 => [
        'agent' => [walkReply(['reply' => 'Thanks for texting. What can we help with?', 'label' => 'faq_answer'])],
        'readings' => [walkScores('faq')],
        'words' => [walkScores('faq')],
    ]]);

    $result = app(WalkRunner::class)->replay($walk, $compiled, $tape);

    expect($result->outcome)->toBe(WalkOutcome::Failed)
        ->and($result->failure->turn)->toBe(2)
        ->and($result->failure->words)->toBe("the customer's card does not show the visit");
});
