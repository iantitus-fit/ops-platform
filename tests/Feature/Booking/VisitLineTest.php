<?php

use App\Enums\AlertReason;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\ReplyLabel;
use App\Models\Appointment;
use App\Models\BookingLink;
use App\Models\Contact;
use App\Models\OwnerAlert;
use App\Models\User;
use Carbon\CarbonImmutable;

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
 * @param  array<string, mixed>  $reply  Fields of the scripted reply that replace the scheduling defaults.
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

    scriptFrontDeskReplies([...[
        'reply' => 'Thanks for checking.',
        'label' => 'scheduling',
        'service_slug' => 'ac-repair',
    ], ...$reply]);

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
