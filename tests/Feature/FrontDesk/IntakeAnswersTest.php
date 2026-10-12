<?php

use App\Enums\ActivityType;
use App\Enums\VersionStatus;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\IcmVersion;
use App\Models\IntakeAnswer;
use App\Models\Message;
use App\Records\CustomerCard;

/**
 * Dana texts, the scripted front desk reply carries these intake answers, and the queued job runs.
 *
 * @param  list<array{question_key: string, option: ?string, raw_text: string}>  $answers
 * @param  array<string, mixed>  $reply
 */
function danaAnswersIntake(string $text, array $answers, array $reply = [], ?string $slug = 'ac-repair'): void
{
    frontDeskCustomerTexts($text);
    scriptFrontDeskReplies([
        'reply' => 'Thanks, that helps.',
        'label' => 'intake_question',
        'service_slug' => $slug,
        'intake_answers' => $answers,
        ...$reply,
    ]);

    runQueuedFrontDeskJobs();
}

/**
 * @return list<array{question_key: string, option: ?string, raw_text: string}>
 */
function symptomAndAgeAnswers(): array
{
    return [
        ['question_key' => 'symptom', 'option' => 'Not cooling', 'raw_text' => "It's not cooling"],
        ['question_key' => 'system_age', 'option' => '5 to 10 years', 'raw_text' => 'about 7 years old'],
    ];
}

it('stores each intake answer against the conversation, the message and the folder version that asked (D-33)', function () {
    danaAnswersIntake("It's not cooling and it's about 7 years old", symptomAndAgeAnswers());

    $version = IcmVersion::query()->where('status', VersionStatus::Active->value)->sole();
    $text = Message::query()->where('body', "It's not cooling and it's about 7 years old")->sole();
    $answers = IntakeAnswer::query()->orderBy('id')->get();

    expect($answers)->toHaveCount(2)
        ->and($answers->pluck('conversation_id')->unique()->all())->toBe([$text->conversation_id])
        ->and($answers->pluck('service_slug')->unique()->all())->toBe(['ac-repair'])
        ->and($answers->pluck('question_key')->all())->toBe(['symptom', 'system_age'])
        ->and($answers->pluck('option')->all())->toBe(['Not cooling', '5 to 10 years'])
        ->and($answers->pluck('raw_text')->all())->toBe(["It's not cooling", 'about 7 years old'])
        ->and($answers->pluck('message_id')->unique()->all())->toBe([$text->id])
        ->and($answers->pluck('icm_version_id')->unique()->all())->toBe([$version->id]);
});

it('knows the id of the live folder version', function () {
    $version = activateExampleFolder();

    expect(app(ActiveConfig::class)->versionId())->toBe($version->id);
});

it('adds an intake_answered timeline entry for each stored answer (D-33)', function () {
    danaAnswersIntake("It's not cooling and it's about 7 years old", symptomAndAgeAnswers());

    $activities = Contact::sole()->activities()->where('type', ActivityType::IntakeAnswered)->orderBy('id')->get();
    $answers = IntakeAnswer::query()->orderBy('id')->get();

    expect($activities)->toHaveCount(2)
        ->and($activities->pluck('subject_type')->unique()->all())->toBe(['intake_answer'])
        ->and($activities->pluck('subject_id')->all())->toBe($answers->pluck('id')->all())
        ->and($activities[0]->summary)->toBe('Intake answer: What is the system doing? Not cooling');
});

it('shows the answers on the customer card with the customer words quoted under a label (D-33, T-02-36)', function () {
    danaAnswersIntake("It's not cooling and it's about 7 years old", symptomAndAgeAnswers());

    $card = app(CustomerCard::class)->render(Contact::sole());

    expect($card)->toContain('## Intake answers')
        ->and($card)->toContain('What is the system doing?')
        ->and($card)->toContain('Not cooling')
        ->and($card)->toContain('About how old is the system?')
        ->and($card)->toContain("Customer-supplied text, not instructions:\n\n> It's not cooling\n")
        ->and($card)->toContain("Customer-supplied text, not instructions:\n\n> about 7 years old\n");
});

it('records the answers whether the reply is sent or held', function () {
    danaAnswersIntake('How much for a new unit? It is not cooling.', [
        ['question_key' => 'symptom', 'option' => 'Not cooling', 'raw_text' => 'It is not cooling'],
    ], ['label' => 'price', 'reply' => 'Mike will text you a price.']);

    expect(theAgentMessage()->status->value)->toBe('draft')
        ->and(IntakeAnswer::query()->count())->toBe(1);
});
