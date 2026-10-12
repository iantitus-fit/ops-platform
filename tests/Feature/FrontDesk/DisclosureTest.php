<?php

use App\Actions\CloseConversation;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;

/**
 * Dana texts again through the fake webhook. Each text needs its own MessageSid.
 */
function danaTextsAgain(string $body, string $sid): void
{
    test()->post(route('webhooks.text.fake'), fakeTextPayload(['MessageSid' => 'SM'.str_repeat($sid, 32), 'Body' => $body]))->assertOk();
}

it('opens the first automated text of a conversation with the AI disclosure and no later one (D-50)', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    danaTextsAgain('It just blows warm', 'd');
    scriptFrontDeskReplies(['reply' => 'How old is the system?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    $voice = app(ActiveConfig::class)->voice();
    [$first, $second] = textsSentToCustomers();

    expect(textsSentToCustomers())->toHaveCount(2)
        ->and($first['body'])->toStartWith($voice['ai_disclosure']."\n\n")
        ->and($second['body'])->not->toContain($voice['ai_disclosure'])
        ->and($second['body'])->toStartWith('How old is the system?');
});

it('still opens the first automated text with the disclosure when the owner texted first', function () {
    frontDeskCustomerTexts();
    $conversation = Conversation::sole();
    Message::factory()->fromOwner()->for($conversation)->create(['body' => 'Hi Dana, Sam here. Saw your text.', 'direction' => MessageDirection::Out]);
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['body'])->toStartWith(app(ActiveConfig::class)->voice()['ai_disclosure']."\n\n");
});

it('does not count a held draft as the disclosure having reached the customer', function () {
    frontDeskCustomerTexts('How much is a diagnostic?');
    scriptFrontDeskReplies(['reply' => 'A diagnostic visit is $89.', 'label' => 'faq_answer']);
    runQueuedFrontDeskJobs();

    danaTextsAgain('It just blows warm', 'd');
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and(textsSentToCustomers()[0]['body'])->toStartWith(app(ActiveConfig::class)->voice()['ai_disclosure']."\n\n");
});

it('discloses again in the new conversation that starts after the owner closed the old one', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    app(CloseConversation::class)->handle(Conversation::sole());

    danaTextsAgain('The unit is leaking now', 'e');
    scriptFrontDeskReplies(['reply' => 'Where is the water coming from?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    $disclosure = app(ActiveConfig::class)->voice()['ai_disclosure'];

    expect(Conversation::query()->count())->toBe(2)
        ->and(textsSentToCustomers())->toHaveCount(2)
        ->and(textsSentToCustomers()[1]['body'])->toStartWith($disclosure."\n\n".'Where is the water coming from?');
});

it('ends every agent text with a new line and the sign-off', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    danaTextsAgain('It just blows warm', 'd');
    scriptFrontDeskReplies(['reply' => 'How old is the system?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    foreach (textsSentToCustomers() as $text) {
        expect($text['body'])->toEndWith("\nExample Heating & Air");
    }

    expect(textsSentToCustomers())->toHaveCount(2);
});

it('holds a reply that says a never_say phrase and sends nothing (GATE-02)', function () {
    frontDeskCustomerTexts('Can you come Tuesday?');
    scriptFrontDeskReplies(['reply' => 'No problem, we can come Tuesday', 'label' => 'scheduling']);

    runQueuedFrontDeskJobs();

    $draft = theAgentMessage();

    expect(textsSentToCustomers())->toBe([])
        ->and($draft->status)->toBe(MessageStatus::Draft)
        ->and($draft->author)->toBe(MessageAuthor::Agent)
        ->and($draft->gate_outcome)->toBe(GateOutcome::Hold)
        ->and($draft->gate_reason)->toBe('code:never_say')
        ->and($draft->gate['reason']['plain'])->toBe('Held: uses a phrase you never say')
        ->and(Contact::sole()->activities()->count())->toBeGreaterThan(0);
});
