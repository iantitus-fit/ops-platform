<?php

use App\Ai\FrontDeskPrompt;
use App\Enums\AlertReason;
use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\PausedReason;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\Models\User;
use App\SendRule\ClassifierFailed;
use App\SendRule\TriageQuestions;
use Illuminate\Support\Carbon;

const CUSTOMER_READING_OWNER_PHONE = '+12025550199';

const CUSTOMER_READING_CUSTOMER_PHONE = '+12025550123';

const CUSTOMER_READING_DIZZY = 'My head is pounding and the kids feel dizzy since the furnace came on.';

/**
 * Tuesday 2026-10-13 at 10:00 in the shop's zone, the owner with a phone, and Dana's text in the live example folder.
 */
function customerReadingDanaTexts(string $body, string $at = '2026-10-13 10:00'): void
{
    test()->travelTo(Carbon::parse($at, 'America/Chicago'));

    User::factory()->withPhone(CUSTOMER_READING_OWNER_PHONE)->create();

    frontDeskCustomerTexts($body);
}

/**
 * The texts that reached Dana's phone, leaving out the alerts to the owner.
 *
 * @return list<array{to: string, body: string, provider_message_id: string}>
 */
function customerReadingTextsToDana(): array
{
    return array_values(array_filter(
        textsSentToCustomers(),
        fn (array $text): bool => $text['to'] === CUSTOMER_READING_CUSTOMER_PHONE,
    ));
}

it('sends the safety line first when Jev reads a hazard the keywords miss, even if the text also asked a price (gap 3)', function () {
    customerReadingDanaTexts(CUSTOMER_READING_DIZZY.' How much is a furnace repair?');
    fakeClassifier()->answerReading('emergency', 0.95, ['life_safety' => 0.9]);
    scriptFrontDeskReplies(['reply' => '', 'price_services' => ['furnace-repair']]);

    runQueuedFrontDeskJobs();

    $line = Message::query()->where('kind', MessageKind::LifeSafety->value)->sole();
    $burst = Message::query()->where('author', MessageAuthor::Customer->value)->get();
    $reading = fakeClassifier()->readingCalls();

    expect(customerReadingTextsToDana())->toHaveCount(1)
        ->and($line->gate['life_safety']['trigger'])->toBe('classifier')
        ->and(Message::query()->where('kind', MessageKind::CodeAnswer->value)->count())->toBe(0)
        ->and(Conversation::query()->sole()->paused_reason)->toBe(PausedReason::LifeSafety)
        ->and(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::LifeSafety)
        ->and($reading)->toHaveCount(1)
        ->and($reading[0]['draft_reply'])->toBe('')
        ->and($reading[0]['customer_message'])->toBe(app(FrontDeskPrompt::class)->promptText($burst));
});

it('reads a plain text once for the customer and once for the words, and the intake question goes out', function () {
    customerReadingDanaTexts('My AC is blowing warm air');
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(fakeClassifier()->readingCalls())->toHaveCount(1)
        ->and(fakeClassifier()->calls())->toHaveCount(1)
        ->and(customerReadingTextsToDana())->toHaveCount(1)
        ->and(customerReadingTextsToDana()[0]['body'])->toContain('Thanks, what is the system doing?');
});

it('gives the reading and the words check the same customer characters (ANSR-03 encoding)', function () {
    customerReadingDanaTexts('My AC is blowing warm air, it’s 85° — “help”');
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(fakeClassifier()->readingCalls()[0]['customer_message'])->toBe(fakeClassifier()->calls()[0]['customer_message']);
});

it('makes no Jev call at all for a customer who opted out', function () {
    customerReadingDanaTexts('My AC is blowing warm air');
    Contact::query()->sole()->update(['sms_opted_out_at' => now()]);
    scriptFrontDeskReplies(['reply' => 'Thanks, what is the system doing?', 'label' => 'intake_question']);

    runQueuedFrontDeskJobs();

    expect(fakeClassifier()->allCalls())->toBe([])
        ->and(OwnerAlert::query()->sole()->reason)->toBe(AlertReason::OptedOutMessage);
});

it('scripts the words check with answer() and the reading with answerReading(), and fails each on its own', function () {
    $classifier = fakeClassifier();
    $questions = TriageQuestions::fromConfig(['intent_question' => 'q', 'intents' => [['key' => 'faq', 'means' => 'm', 'sendable' => true]], 'questions' => ['life_safety' => 'x']]);

    $classifier->answer('price_question', 0.9);
    $default = $classifier->classify('hello', '', $questions);
    $words = $classifier->classify('hello', 'a reply', $questions);

    expect($default->intent)->toBe('faq')
        ->and($default->yesNo['life_safety'])->toBe(0.01)
        ->and($words->intent)->toBe('price_question')
        ->and($classifier->readingCalls())->toHaveCount(1)
        ->and($classifier->calls())->toHaveCount(1)
        ->and($classifier->allCalls())->toHaveCount(2);

    $classifier->failReadingWith('reading down');

    expect(fn () => $classifier->classify('hello', '', $questions))->toThrow(ClassifierFailed::class, 'reading down')
        ->and($classifier->classify('hello', 'a reply', $questions)->intent)->toBe('price_question');

    $classifier->failWith('everything down');

    expect(fn () => $classifier->classify('hello', 'a reply', $questions))->toThrow(ClassifierFailed::class, 'everything down');
});
