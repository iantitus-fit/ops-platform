<?php

use App\Actions\SendOwnerReply;
use App\Ai\Agents\FrontDeskAgent;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Records\CustomerCard;
use App\SendRule\Verdict;

it('stores every part of the verdict on a sent reply (D-07)', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'Sorry to hear that. What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $gate = theAgentMessage()->gate;

    expect(array_keys($gate))->toContain('version', 'label', 'wants_person', 'inputs', 'checks', 'thresholds', 'reply_model', 'failed_attempts', 'reason')
        ->and($gate['version'])->toBe(1)
        ->and($gate['inputs']['agent_reply'])->toBe('Sorry to hear that. What is the system doing?')
        ->and($gate['inputs']['customer_text'])->toBe('My AC is blowing warm air')
        ->and($gate['thresholds'])->toBe(config('gate.classifier'))
        ->and($gate['checks']['classifier']['model'])->toBe('fake-jev')
        ->and($gate['reply_model'])->toBe(['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5'])
        ->and($gate['failed_attempts'])->toBe([]);
});

it('records the failed provider and the fallback model that answered', function () {
    frontDeskCustomerTexts();

    FrontDeskAgent::fake(function ($prompt, $attachments, $provider) {
        if ($provider->name() === 'anthropic') {
            throw new RuntimeException('HTTP 500');
        }

        return [
            'reply' => 'What is the system doing?',
            'label' => 'intake_question',
            'wants_person' => false,
            'service_slug' => null,
            'intake_answers' => [],
            'customer_name' => null,
        ];
    })->preventStrayPrompts();

    runQueuedFrontDeskJobs();

    $gate = theAgentMessage()->gate;

    expect(textsSentToCustomers())->toHaveCount(1)
        ->and($gate['reply_model'])->toBe(['provider' => 'openai', 'model' => 'gpt-6.1-sol'])
        ->and($gate['failed_attempts'])->toHaveCount(1)
        ->and($gate['failed_attempts'][0]['provider'])->toBe('anthropic')
        ->and($gate['failed_attempts'][0]['error'])->toContain('HTTP 500');
});

it('asks Jev exactly once per sent reply, about the burst text and the agent words only (D-04, D-05)', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    $voice = app(ActiveConfig::class)->voice();
    $calls = fakeClassifier()->calls();

    expect($calls)->toHaveCount(1)
        ->and($calls[0]['customer_message'])->toBe('My AC is blowing warm air')
        ->and($calls[0]['draft_reply'])->toBe('What is the system doing?')
        ->and($calls[0]['draft_reply'])->not->toContain($voice['ai_disclosure'])
        ->and($calls[0]['draft_reply'])->not->toContain($voice['sign_off'])
        ->and($calls[0]['draft_reply'])->not->toContain('Dana');
});

it('says in plain words why a reply was held (D-07)', function (string $code, string $plain) {
    expect(Verdict::plainReasonFor($code))->toBe($plain);
})->with([
    'price from the code check' => ['code:price', 'Held: asks about price'],
    'price from the label' => ['label:price', 'Held: asks about price'],
    'price from Jev' => ['classifier:price', 'Held: asks about price'],
    'price as Jev reads the customer' => ['classifier_intent:price_question', 'Held: asks about price'],
    'promise from the label' => ['label:promise', 'Held: makes a promise'],
    'promise from the code check' => ['code:promise', 'Held: makes a promise'],
    'promise from Jev' => ['classifier:promise', 'Held: makes a promise'],
    'discount from the label' => ['label:discount', 'Held: mentions a discount'],
    'discount from the code check' => ['code:discount', 'Held: mentions a discount'],
    'discount from Jev' => ['classifier:discount', 'Held: mentions a discount'],
    'complaint from the label' => ['label:complaint', 'Held: complaint'],
    'complaint from Jev' => ['classifier:complaint', 'Held: complaint'],
    'complaint as Jev reads the customer' => ['classifier_intent:complaint', 'Held: complaint'],
    'other from the label' => ['label:other', 'Held: needs your judgment'],
    'other as Jev reads the customer' => ['classifier_intent:other', 'Held: needs your judgment'],
    'never_say' => ['code:never_say', 'Held: uses a phrase you never say'],
    'too long' => ['too_long', 'Held: too long for one text'],
    'Jev did not answer' => ['classifier_failed', 'Held: the safety check did not answer'],
    'Jev unsure of the intent' => ['classifier_unsure:intent', 'Held: the safety check was not sure'],
    'emergency intent' => ['classifier_intent:emergency', 'Held: may be urgent'],
    'life safety from Jev' => ['classifier:life_safety', 'Held: may be urgent'],
    'unsure about a person' => ['classifier_unsure:asks_for_person', 'Held: may want to talk to a person'],
    'asks for a person' => ['classifier:asks_for_person', 'Held: may want to talk to a person'],
    'sender refused' => ['send_refused', 'Held: could not send right now'],
    'a code nobody listed' => ['something_new', 'Held: needs your judgment'],
]);

it('stores the plain reason on a held draft', function () {
    frontDeskCustomerTexts('How much is a diagnostic?');
    scriptFrontDeskReplies(['reply' => 'A diagnostic visit is $89.', 'label' => 'faq_answer']);

    runQueuedFrontDeskJobs();

    expect(theAgentMessage()->gate['reason'])->toBe(['code' => 'code:price', 'plain' => 'Held: asks about price']);
});

it('labels a front desk reply and an owner reply differently on the customer card (Pitfall 8)', function () {
    frontDeskCustomerTexts();
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);
    runQueuedFrontDeskJobs();

    app(SendOwnerReply::class)->handle(Conversation::sole(), 'Sam here, I can come Tuesday at 9');

    $agent = Message::query()->where('author', MessageAuthor::Agent)->sole();
    $owner = Message::query()->where('author', MessageAuthor::Owner)->sole();
    $card = app(CustomerCard::class)->render(Contact::sole());

    expect($agent->direction)->toBe(MessageDirection::Out)
        ->and($agent->status)->toBe(MessageStatus::Sent)
        ->and($card)
        ->toContain('Reply from front desk (sms, sent)')
        ->toContain('Front desk reply:')
        ->toContain('Reply from owner (sms, sent)')
        ->toContain('Owner reply:');
});
