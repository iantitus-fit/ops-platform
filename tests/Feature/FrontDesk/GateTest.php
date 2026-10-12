<?php

use App\Enums\GateOutcome;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Models\Message;

/**
 * The one reply the agent wrote, sent or held. Any holding reply the code added after it is left out.
 */
function gateTestAgentReply(): Message
{
    return Message::query()->where('kind', MessageKind::AgentReply->value)->sole();
}

/**
 * Script Jev for one gate row: 'default' leaves the fake's clear faq answer, 'fail' makes the call fail, and an
 * array is intent, confidence and any yes or no overrides.
 *
 * @param  'default'|'fail'|array{0: string, 1: float, 2?: array<string, float>}  $script
 */
function scriptGateTestClassifier(string|array $script): void
{
    if ($script === 'fail') {
        fakeClassifier()->failWith('down');
    } elseif (is_array($script)) {
        fakeClassifier()->answer($script[0], $script[1], $script[2] ?? []);
    }
}

it('turns the label, the code checks and Jev into the stated send or hold (GATE-01)', function (string $label, string $reply, string|array $jev, string $outcome, ?string $reason) {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => $reply, 'label' => $label]);
    scriptGateTestClassifier($jev);

    runQueuedFrontDeskJobs();

    $message = gateTestAgentReply();

    expect($message->gate_outcome)->toBe(GateOutcome::from($outcome))
        ->and($message->gate_reason)->toBe($reason)
        ->and($message->status)->toBe($outcome === 'send' ? MessageStatus::Sent : MessageStatus::Draft);
})->with([
    'intake question sends' => ['intake_question', 'What is the system doing?', 'default', 'send', null],
    'scheduling sends' => ['scheduling', 'We have 9 am Thursday open. Does that work?', 'default', 'send', null],
    'a price in the words' => ['faq_answer', 'A diagnostic visit is $89.', 'default', 'hold', 'code:price'],
    'a discount in the words' => ['faq_answer', 'We can take 10% off.', 'default', 'hold', 'code:discount'],
    'a promise in the words' => ['scheduling', 'We guarantee Thursday.', 'default', 'hold', 'code:promise'],
    'price label' => ['price', 'Mike will text you a price.', 'default', 'hold', 'label:price'],
    'promise label' => ['promise', 'Mike will follow up on that.', 'default', 'hold', 'label:promise'],
    'discount label' => ['discount', 'Mike will follow up on that.', 'default', 'hold', 'label:discount'],
    'complaint label' => ['complaint', 'I am sorry about the visit.', 'default', 'hold', 'label:complaint'],
    'other label' => ['other', 'Mike will follow up on that.', 'default', 'hold', 'label:other'],
    'Jev is down' => ['faq_answer', 'We are open until 5 pm today.', 'fail', 'hold', 'classifier_failed'],
    'Jev is unsure of the intent' => ['faq_answer', 'We are open until 5 pm today.', ['faq', 0.84], 'hold', 'classifier_unsure:intent'],
    'Jev sees a price' => ['faq_answer', 'We are open until 5 pm today.', ['faq', 0.95, ['price' => 0.2]], 'hold', 'classifier:price'],
    'Jev reads a complaint' => ['faq_answer', 'We are open until 5 pm today.', ['complaint', 0.95], 'hold', 'classifier_intent:complaint'],
    'too long for one text' => ['faq_answer', trim(str_repeat('Thanks for waiting on us. ', 66)), 'default', 'hold', 'too_long'],
]);

it('drafts a scheduling reply when the folder lets only intake questions send on their own (D-08)', function () {
    breakFile('rules/sending.md', fn (string $text) => str_replace(
        'send_on_own: [intake_question, scheduling, faq_answer]',
        'send_on_own: [intake_question]',
        $text,
    ));

    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'We have 9 am Thursday open. Does that work?', 'label' => 'scheduling']);

    runQueuedFrontDeskJobs();

    $message = gateTestAgentReply();

    expect($message->status)->toBe(MessageStatus::Draft)
        ->and($message->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($message->gate_reason)->toBe('label:scheduling')
        ->and(fakeClassifier()->calls())->toBe([]);
});
