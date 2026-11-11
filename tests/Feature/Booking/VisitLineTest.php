<?php

use App\Actions\OutboundSender;
use App\Enums\AlertReason;
use App\Enums\AppointmentState;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\ReplyLabel;
use App\Enums\SendOrigin;
use App\Icm\ActiveConfig;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\IcmVersion;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\CustomerReading;
use App\SendRule\ReplyParts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * D-39 as amended (Ian, 2026-11-11, UAT gaps G-08-4 and G-08-4b): a booked customer's scheduling reply carries the
 * owner's visit_line, added by code after the verdict; the model never writes the day or time. The line rides only under
 * the condition RunFrontDesk used for the D-34 alert, and never with a link.
 */
const VISIT_LINE_WORDS = 'Your visit for AC repair is on Thursday, November 5 at 10:00 AM. To change it, reply to this text.';

/**
 * Dana texts on Tuesday 2026-11-03 at 11:00 in the shop's zone, a visit is booked for her on Thursday 2026-11-05 at
 * 10:00, and the front desk's scripted reply is labeled scheduling. The queued job runs, so the reply is sent or held by
 * the real send path.
 *
 * @param  array<string, mixed>  $reply  Fields of the scripted reply that replace the scheduling defaults; the words may be a closure, run once the folder is live.
 * @param  ?Closure(): void  $beforeTexts  Runs before the folder is activated, to change the owner's files.
 * @param  ?Closure(Contact): void  $visit  Replaces the default booked visit; it gets Dana.
 */
function visitLineBookedDanaAsks(array $reply = [], string $intent = 'scheduling', string $text = 'What time are you coming?', ?Closure $beforeTexts = null, ?Closure $visit = null): void
{
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));
    User::factory()->withPhone('+12025550199')->create();

    if ($beforeTexts !== null) {
        $beforeTexts();
    }

    frontDeskCustomerTexts($text);

    if ($visit !== null) {
        $visit(Contact::sole());
    } else {
        Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();
    }

    fakeClassifier()->answerReading($intent);

    $reply = [...[
        'reply' => 'Thanks for checking.',
        'label' => 'scheduling',
        'service_slug' => 'ac-repair',
    ], ...$reply];

    if ($reply['reply'] instanceof Closure) {
        $reply['reply'] = ($reply['reply'])();
    }

    scriptFrontDeskReplies($reply);

    runQueuedFrontDeskJobs();
}

/**
 * The texts that reached Dana's phone, leaving out alerts to the owner.
 *
 * @return list<string>
 */
function visitLineTextsToDana(): array
{
    return array_values(array_map(
        fn (array $text): string => $text['body'],
        array_filter(textsSentToCustomers(), fn (array $text): bool => $text['to'] === '+12025550123'),
    ));
}

it('adds the owner\'s visit line to a booked customer\'s scheduling reply (D-39 amended)', function () {
    visitLineBookedDanaAsks();

    $message = theAgentMessage();
    $changeRequests = OwnerAlert::query()->where('reason', AlertReason::ChangeRequest->value)->get();

    expect(visitLineTextsToDana())->toBe([composedFirstReply('Thanks for checking. '.VISIT_LINE_WORDS)])
        ->and($message->kind)->toBe(MessageKind::AgentReply)
        ->and($message->label)->toBe(ReplyLabel::Scheduling)
        ->and($message->status)->toBe(MessageStatus::Sent)
        ->and($message->meta['parts'])->toBe(['model_words', 'visit_line'])
        ->and($message->meta['visit_line_appointment_id'])->toBe(Appointment::sole()->id)
        ->and($message->gate['inputs']['agent_reply'])->toBe('Thanks for checking.')
        ->and(BookingLink::query()->count())->toBe(0)
        ->and(collect(fakeClassifier()->calls())->filter(fn (array $call): bool => str_contains(json_encode($call), 'Your visit for')))->toBeEmpty()
        ->and($changeRequests)->toHaveCount(1)
        ->and($changeRequests[0]->body)->toContain('AC repair, Thursday, November 5 at 10:00 AM');
});

/**
 * Words that make the first automated text, with the visit line after them, exactly $length characters long.
 */
function visitLineWordsMakingText(int $length): string
{
    return str_repeat('a', $length - mb_strlen(composedFirstReply(' '.VISIT_LINE_WORDS)));
}

/**
 * A customer reading with the given verdict on the customer's text, built the way the sender reads it from a gate.
 */
function visitLineReading(bool $complaint): CustomerReading
{
    return new CustomerReading(
        ran: true,
        failed: false,
        error: null,
        model: 'fake-jev',
        answers: null,
        reading: [
            'sendable' => ! $complaint,
            'reason_code' => $complaint ? 'classifier:complaint' : null,
            'reasons' => $complaint ? ['classifier:complaint'] : [],
            'life_safety' => false,
            'asks_for_person' => false,
            'thresholds' => [],
        ],
    );
}

it('holds the visit line to a clean reading at the chokepoint (02.1 D-49)', function (bool $complaint) {
    activateExampleFolder();

    $conversation = Conversation::factory()->for(Contact::factory()->create())->create();
    Message::factory()->for($conversation)->create(['handled_at' => now()]);

    $send = fn (): Message => app(OutboundSender::class)->send($conversation, 'Thanks for checking. '.VISIT_LINE_WORDS, MessageAuthor::Agent, SendOrigin::Automated, [
        'kind' => MessageKind::AgentReply,
        'gate_outcome' => GateOutcome::Send,
        'gate' => [
            'version' => 1,
            'customer_reading' => visitLineReading($complaint)->toArray(),
            'checks' => ['classifier' => ['ran' => true, 'error' => null, 'reading' => ['sendable' => true]]],
        ],
        'meta' => ['parts' => ['model_words', 'visit_line']],
    ]);

    if ($complaint) {
        $refusal = null;

        try {
            $send();
        } catch (DomainException $exception) {
            $refusal = $exception->getMessage();
        }

        expect($refusal)->not->toBeNull('the sender sent a visit line over a complaint reading');

        expect($refusal)->toStartWith(OutboundSender::NO_READING)->toContain("a visit line needs a clean reading of the customer's text")
            ->and(Message::query()->where('direction', MessageDirection::Out)->count())->toBe(0);
    } else {
        expect($send()->status)->toBe(MessageStatus::Sent);
    }
})->with([
    'a complaint reading holds it' => true,
    'a clean reading lets it out' => false,
]);

it('puts the visit line after the fixed line and before the booking line', function () {
    $plain = new ReplyParts(modelWords: 'Sure.', visitLine: 'V.');
    $full = new ReplyParts(modelWords: 'Sure.', lineKind: MessageKind::CheckInReply, line: 'L.', bookingLine: 'B.', visitLine: 'V.');

    expect($plain->body())->toBe('Sure. V.')
        ->and($plain->partNames())->toBe(['model_words', 'visit_line'])
        ->and($plain->kind())->toBe(MessageKind::AgentReply)
        ->and($full->body())->toBe('Sure. L. V. B.')
        ->and($full->partNames())->toBe(['model_words', 'check_in_line', 'visit_line', 'booking_link']);
});

it('rides on the reading\'s scheduling intent when the label is faq_answer (D-34 condition)', function () {
    visitLineBookedDanaAsks(['label' => 'faq_answer', 'reply' => 'Thanks for checking.'], intent: 'scheduling');

    expect(visitLineTextsToDana())->toBe([composedFirstReply('Thanks for checking. '.VISIT_LINE_WORDS)])
        ->and(theAgentMessage()->meta['parts'])->toBe(['model_words', 'visit_line']);
});

it('adds no visit line when neither the reading nor the label is about scheduling', function () {
    visitLineBookedDanaAsks(['label' => 'faq_answer', 'reply' => 'We service Carrier units.'], intent: 'faq', text: 'Do you work on Carrier units?');

    expect(visitLineTextsToDana())->toBe([composedFirstReply('We service Carrier units.')])
        ->and(OwnerAlert::query()->where('reason', AlertReason::ChangeRequest->value)->count())->toBe(0);
});

it('carries the link line and no visit line when no visit is booked ahead (D-01)', function (string $case) {
    visitLineBookedDanaAsks(visit: function (Contact $contact) use ($case): void {
        if ($case === 'no visit') {
            return;
        }

        $visit = Appointment::factory()->for($contact)->at(CarbonImmutable::parse($case === 'started a minute ago' ? '2026-11-03 10:59' : '2026-11-05 10:00', 'America/Chicago'))->create();

        if ($case === 'cancelled') {
            $visit->forceFill(['state' => AppointmentState::Cancelled])->save();
        }

        if ($case === 'done') {
            $visit->forceFill(['state' => AppointmentState::Done])->save();
        }
    });

    expect(visitLineTextsToDana())->toHaveCount(1)
        ->and(visitLineTextsToDana()[0])->toContain('Thanks for checking. Pick a time that works for you here: ')->not->toContain('Your visit for')
        ->and(theAgentMessage()->meta['parts'])->toBe(['model_words', 'booking_link']);
})->with(['no visit', 'cancelled', 'done', 'started a minute ago']);

it('adds no visit line and changes nothing else with the room off, no booking file, or a version compiled before visit_line (D-03)', function (string $case) {
    visitLineBookedDanaAsks(
        beforeTexts: match ($case) {
            'room off' => fn () => File::deleteDirectory(icmPath('rooms/schedule_and_booking')),
            'no booking file' => fn () => File::delete(icmPath('rooms/schedule_and_booking/booking.md')),
            'older version' => fn () => null,
        },
        visit: function (Contact $contact) use ($case): void {
            Appointment::factory()->for($contact)->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();

            if ($case !== 'older version') {
                return;
            }

            $version = IcmVersion::query()->findOrFail(app(ActiveConfig::class)->versionId());
            $compiled = $version->compiled;
            $path = $compiled['index']['booking'];
            $path = is_array($path) ? $path[0] : $path;
            unset($compiled['files'][$path]['front_matter']['visit_line']);
            $version->update(['compiled' => $compiled]);

            app(ActiveConfig::class)->refresh();
        },
    );

    expect(visitLineTextsToDana())->toBe([composedFirstReply('Thanks for checking.')])
        ->and(BookingLink::query()->count())->toBe(0)
        ->and(OwnerAlert::query()->where('reason', AlertReason::ChangeRequest->value)->count())->toBe(1);
})->with(['room off', 'no booking file', 'older version']);

it('never puts the visit line in a held draft', function () {
    visitLineBookedDanaAsks(['reply' => 'We will move you to Wednesday.', 'label' => 'promise'], text: 'Can you come Wednesday instead?');

    $draft = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    expect($draft->body)->toBe('We will move you to Wednesday.')
        ->and(collect(visitLineTextsToDana())->filter(fn (string $body): bool => str_contains($body, 'Your visit for')))->toBeEmpty();
});

it('adds the visit line to a scheduling reply sent while a price draft waits (D-20)', function () {
    test()->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'America/Chicago'));
    User::factory()->withPhone('+12025550199')->create();
    frontDeskCustomerTexts('How much for a new unit?');
    scriptFrontDeskReplies(['reply' => 'Mike will text you a price for a new unit.', 'label' => 'price']);
    runQueuedFrontDeskJobs();

    $waiting = Message::query()->where('status', MessageStatus::Draft->value)->sole();

    Appointment::factory()->for(Contact::sole())->at(CarbonImmutable::parse('2026-11-05 10:00', 'America/Chicago'))->create();

    test()->travel(1)->minutes();
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_pad('1', 32, 'c', STR_PAD_LEFT), 'Body' => 'What time are you coming?']))->assertOk();
    fakeClassifier()->answerReading('scheduling');
    scriptFrontDeskReplies(['reply' => 'Thanks for checking.', 'label' => 'scheduling', 'service_slug' => 'ac-repair', 'touches_waiting' => false]);
    runQueuedFrontDeskJobs();

    expect(collect(visitLineTextsToDana())->last())->toContain('Thanks for checking. '.VISIT_LINE_WORDS)
        ->and(Message::query()->where('status', MessageStatus::Draft->value)->sole()->id)->toBe($waiting->id)
        ->and($waiting->fresh()->body)->toBe($waiting->body);
});

it('leaves the visit line off at the length limit and keeps it at exactly 1,600 characters', function (int $length, bool $kept) {
    visitLineBookedDanaAsks(['reply' => fn (): string => visitLineWordsMakingText($length)]);

    $body = visitLineTextsToDana()[0];

    if ($kept) {
        expect(mb_strlen($body))->toBe(1600)
            ->and($body)->toBe(composedFirstReply(visitLineWordsMakingText($length).' '.VISIT_LINE_WORDS))
            ->and(theAgentMessage()->meta['parts'])->toBe(['model_words', 'visit_line']);
    } else {
        expect($body)->toBe(composedFirstReply(visitLineWordsMakingText($length)))
            ->and(theAgentMessage()->meta['parts'] ?? [])->not->toContain('visit_line');
    }
})->with([
    '1601 characters leaves it off' => [1601, false],
    '1600 characters keeps it' => [1600, true],
]);
