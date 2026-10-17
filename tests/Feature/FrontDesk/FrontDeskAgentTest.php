<?php

use App\Ai\AgentFile;
use App\Ai\AgentReply;
use App\Ai\Agents\FrontDeskAgent;
use App\Ai\FrontDeskUnavailable;
use App\Ai\RunFrontDeskAgent;
use App\Enums\ReplyLabel;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasTools;

/**
 * The live example folder, and Dana's open conversation with one unhandled text.
 *
 * @return array{AgentFile, Conversation, Collection<int, Message>}
 */
function frontDeskAgentTestThread(string $text = 'My AC is blowing warm air'): array
{
    activateExampleFolder();

    $contact = Contact::factory()->create(['name' => 'Dana Whitfield', 'phone_e164' => '+12025550123']);
    $conversation = Conversation::factory()->for($contact)->create();
    $message = Message::factory()->for($conversation)->create(['body' => $text]);

    return [
        AgentFile::forChannel(app(ActiveConfig::class), 'sms'),
        $conversation,
        Message::query()->whereKey($message->id)->get(),
    ];
}

it('answers a customer text with a validated reply and names who answered', function () {
    [$agent, $conversation, $burst] = frontDeskAgentTestThread();

    scriptFrontDeskReplies([
        'reply' => 'Sorry to hear that. What is the system doing?',
        'label' => 'intake_question',
        'service_slug' => 'ac-repair',
    ]);

    $run = app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);

    expect($run->reply->reply)->toBe('Sorry to hear that. What is the system doing?')
        ->and($run->reply->label)->toBe(ReplyLabel::IntakeQuestion)
        ->and($run->reply->serviceSlug)->toBe('ac-repair')
        ->and($run->reply->wantsPerson)->toBeFalse()
        ->and($run->provider)->toBe('anthropic')
        ->and($run->model)->toBe('claude-sonnet-5-5')
        ->and($run->failedAttempts)->toBe([]);
});

it('prompts the agent once with the customer text', function () {
    [$agent, $conversation, $burst] = frontDeskAgentTestThread();

    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);

    FrontDeskAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'My AC is blowing warm air'));
    FrontDeskAgent::assertPromptedTimes(1);
});

it('turns a prompt nobody scripted into FrontDeskUnavailable instead of a real model call', function () {
    [$agent, $conversation, $burst] = frontDeskAgentTestThread();

    expect(fn () => app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst))
        ->toThrow(FrontDeskUnavailable::class);
});

it('rejects an empty structured answer', function () {
    expect(fn () => AgentReply::fromStructured([]))->toThrow(UnexpectedValueException::class);
});

it('rejects a label outside the fixed vocabulary', function () {
    expect(fn () => AgentReply::fromStructured(['reply' => 'Hi', 'label' => 'bogus', 'wants_person' => false]))
        ->toThrow(UnexpectedValueException::class);
});

it('rejects an empty or whitespace reply', function (string $reply) {
    expect(fn () => AgentReply::fromStructured(['reply' => $reply, 'label' => 'faq_answer', 'wants_person' => false]))
        ->toThrow(UnexpectedValueException::class);
})->with(['empty' => '', 'spaces' => '   ', 'new lines' => "\n\t "]);

it('rejects a wants_person that is not a boolean', function () {
    expect(fn () => AgentReply::fromStructured(['reply' => 'Hi', 'label' => 'faq_answer', 'wants_person' => 'yes']))
        ->toThrow(UnexpectedValueException::class);
});

it('rejects a possible_danger that is missing or not a boolean, so the chain tries the next model (WR-02)', function (array $overrides) {
    $answer = frontDeskAgentTestAnswer();
    unset($answer['possible_danger']);

    expect(fn () => AgentReply::fromStructured([...$answer, ...$overrides]))
        ->toThrow(UnexpectedValueException::class, 'possible_danger');
})->with([
    'missing' => [[]],
    'null' => [['possible_danger' => null]],
    'the string true' => [['possible_danger' => 'true']],
    'the integer 1' => [['possible_danger' => 1]],
]);

it('reads possible_danger back as the boolean the model gave (WR-02)', function (bool $danger) {
    expect(AgentReply::fromStructured(frontDeskAgentTestAnswer(['possible_danger' => $danger]))->possibleDanger)->toBe($danger);
})->with([true, false]);

it('treats missing intake answers as an empty list and trims the reply', function () {
    $reply = AgentReply::fromStructured(['reply' => "  Hello there \n", 'label' => 'faq_answer', 'wants_person' => false, 'possible_danger' => false]);

    expect($reply->intakeAnswers)->toBe([])
        ->and($reply->reply)->toBe('Hello there')
        ->and($reply->serviceSlug)->toBeNull()
        ->and($reply->customerName)->toBeNull();
});

it('keeps well-formed intake answers and drops malformed ones', function () {
    $reply = AgentReply::fromStructured([
        'reply' => 'Thanks',
        'label' => 'intake_question',
        'wants_person' => false,
        'possible_danger' => false,
        'intake_answers' => [
            ['question_key' => 'symptom', 'option' => 'warm air', 'raw_text' => 'blowing warm air'],
            ['question_key' => 'age', 'option' => null, 'raw_text' => 'about ten years'],
            ['question_key' => 7, 'option' => null, 'raw_text' => 'bad key'],
            ['question_key' => 'pets'],
        ],
    ]);

    expect($reply->intakeAnswers)->toBe([
        ['question_key' => 'symptom', 'option' => 'warm air', 'raw_text' => 'blowing warm air'],
        ['question_key' => 'age', 'option' => null, 'raw_text' => 'about ten years'],
    ]);
});

it('gives the model no tools, so it can never send a text', function () {
    expect(class_implements(FrontDeskAgent::class))->not->toContain(HasTools::class);
});

/**
 * A well-formed structured answer with every field, ready to have a value swapped for a test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function frontDeskAgentTestAnswer(array $overrides = []): array
{
    return [
        'reply' => 'Thanks',
        'label' => 'faq_answer',
        'wants_person' => false,
        'possible_danger' => false,
        ...$overrides,
    ];
}

it('reads the six new fields back as given and returns them under snake_case keys', function () {
    $reply = AgentReply::fromStructured(frontDeskAgentTestAnswer([
        'price_services' => ['ac-repair', 'system-replacement'],
        'asks_discount' => true,
        'touches_waiting' => false,
        'waiting_note' => 'check_in',
        'easy_reply' => 'Yes, we service heat pumps.',
        'easy_label' => 'faq_answer',
    ]));

    expect($reply->priceServices)->toBe(['ac-repair', 'system-replacement'])
        ->and($reply->asksDiscount)->toBeTrue()
        ->and($reply->touchesWaiting)->toBeFalse()
        ->and($reply->waitingNote)->toBe('check_in')
        ->and($reply->easyReply)->toBe('Yes, we service heat pumps.')
        ->and($reply->easyLabel)->toBe(ReplyLabel::FaqAnswer)
        ->and($reply->toArray())->toMatchArray([
            'price_services' => ['ac-repair', 'system-replacement'],
            'asks_discount' => true,
            'touches_waiting' => false,
            'waiting_note' => 'check_in',
            'easy_reply' => 'Yes, we service heat pumps.',
            'easy_label' => 'faq_answer',
        ]);
});

it('falls back to the safer path when the six new fields are missing, and never throws', function () {
    $reply = AgentReply::fromStructured(frontDeskAgentTestAnswer());

    expect($reply->priceServices)->toBe([])
        ->and($reply->asksDiscount)->toBeFalse()
        ->and($reply->touchesWaiting)->toBeNull()
        ->and($reply->waitingNote)->toBeNull()
        ->and($reply->easyReply)->toBeNull()
        ->and($reply->easyLabel)->toBeNull()
        ->and($reply->toArray())->toMatchArray(['price_services' => [], 'asks_discount' => false, 'touches_waiting' => null, 'waiting_note' => null, 'easy_reply' => null, 'easy_label' => null]);
});

it('never throws on a malformed new field, it falls back instead (T-02.1-11)', function (string $field, mixed $value, string $property, mixed $expected) {
    $reply = AgentReply::fromStructured(frontDeskAgentTestAnswer([$field => $value]));

    expect($reply->{$property})->toBe($expected);
})->with([
    'price_services as a string' => ['price_services', 'ac-repair', 'priceServices', []],
    'price_services with junk, spaces and a repeat' => ['price_services', [' ac-repair ', 12, '', 'ac-repair'], 'priceServices', ['ac-repair']],
    'asks_discount as text' => ['asks_discount', 'yes', 'asksDiscount', false],
    'touches_waiting as text' => ['touches_waiting', 'no', 'touchesWaiting', null],
    'waiting_note misspelled' => ['waiting_note', 'checkin', 'waitingNote', null],
    'easy_reply as a number' => ['easy_reply', 42, 'easyReply', null],
    'easy_reply of spaces' => ['easy_reply', '   ', 'easyReply', null],
    'easy_label outside the vocabulary' => ['easy_label', 'booking', 'easyLabel', null],
    'easy_label as a number' => ['easy_label', 7, 'easyLabel', null],
]);

it('still throws on a blank reply while the new fields are well formed and no price ask is flagged', function () {
    expect(fn () => AgentReply::fromStructured(frontDeskAgentTestAnswer(['reply' => '  ', 'price_services' => []])))
        ->toThrow(UnexpectedValueException::class);
});

it('lists every new field as a required property of the schema', function () {
    $schema = (new FrontDeskAgent('', []))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toContain('price_services', 'asks_discount', 'touches_waiting', 'waiting_note', 'easy_reply', 'easy_label');

    foreach (['price_services', 'asks_discount', 'touches_waiting', 'waiting_note', 'easy_reply', 'easy_label'] as $name) {
        expect($schema[$name]->toArray())->toHaveKey('type');
    }

    $required = (new ObjectType($schema))->toArray()['required'] ?? [];

    expect($required)->toContain('price_services', 'asks_discount', 'touches_waiting', 'waiting_note', 'easy_reply', 'easy_label');
});

it('carries the six new fields from a scripted model answer into the run', function () {
    [$agent, $conversation, $burst] = frontDeskAgentTestThread();

    scriptFrontDeskReplies(['price_services' => ['ac-repair'], 'asks_discount' => true, 'touches_waiting' => true, 'waiting_note' => 'added_info', 'easy_reply' => 'Yes.', 'easy_label' => 'faq_answer']);

    $run = app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);

    expect($run->reply->priceServices)->toBe(['ac-repair'])
        ->and($run->reply->asksDiscount)->toBeTrue()
        ->and($run->reply->touchesWaiting)->toBeTrue()
        ->and($run->reply->waitingNote)->toBe('added_info')
        ->and($run->reply->easyReply)->toBe('Yes.')
        ->and($run->reply->easyLabel)->toBe(ReplyLabel::FaqAnswer);
});
