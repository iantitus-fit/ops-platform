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

it('treats missing intake answers as an empty list and trims the reply', function () {
    $reply = AgentReply::fromStructured(['reply' => "  Hello there \n", 'label' => 'faq_answer', 'wants_person' => false]);

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
