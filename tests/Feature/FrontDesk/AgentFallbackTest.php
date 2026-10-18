<?php

use App\Ai\AgentFile;
use App\Ai\Agents\FrontDeskAgent;
use App\Ai\FrontDeskUnavailable;
use App\Ai\RunFrontDeskAgent;
use App\Enums\ReplyLabel;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Laravel\Ai\Exceptions\ProviderOverloadedException;

/**
 * The live example folder and Dana's one unhandled text, ready for a run.
 *
 * @return array{AgentFile, Conversation, Collection<int, Message>}
 */
function fallbackTestRun(): array
{
    activateExampleFolder();

    $conversation = Conversation::factory()->for(Contact::factory()->create(['phone_e164' => '+12025550123']))->create();
    $message = Message::factory()->for($conversation)->create(['body' => 'My AC is blowing warm air']);

    return [
        AgentFile::forChannel(app(ActiveConfig::class), 'sms'),
        $conversation,
        Message::query()->whereKey($message->id)->get(),
    ];
}

/**
 * A fake model where the anthropic primary does whatever $primary says and openai answers well.
 *
 * @param  Closure(): mixed  $primary
 */
function fakeFailingPrimary(Closure $primary): void
{
    FrontDeskAgent::fake(function ($prompt, $attachments, $provider) use ($primary) {
        if ($provider->name() === 'anthropic') {
            return $primary();
        }

        return [
            'reply' => 'Sorry to hear that. What is the system doing?',
            'label' => 'intake_question',
            'wants_person' => false,
            'service_slug' => 'ac-repair',
            'intake_answers' => [],
            'customer_name' => null,
            'possible_danger' => false,
        ];
    })->preventStrayPrompts();
}

it('answers from the fallback when the primary is overloaded', function () {
    [$agent, $conversation, $burst] = fallbackTestRun();

    fakeFailingPrimary(fn () => throw ProviderOverloadedException::forProvider('anthropic'));

    $run = app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);

    expect($run->provider)->toBe('openai')
        ->and($run->model)->toBe('gpt-6.1-sol')
        ->and($run->reply->label)->toBe(ReplyLabel::IntakeQuestion)
        ->and($run->failedAttempts)->toHaveCount(1)
        ->and($run->failedAttempts[0]['provider'])->toBe('anthropic')
        ->and($run->failedAttempts[0]['model'])->toBe('claude-sonnet-5-5');

    FrontDeskAgent::assertPromptedTimes(2);
});

it('answers from the fallback when the primary fails with an error the SDK would not fail over on', function () {
    [$agent, $conversation, $burst] = fallbackTestRun();

    fakeFailingPrimary(fn () => throw new RuntimeException('HTTP 500'));

    $run = app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);

    expect($run->provider)->toBe('openai')
        ->and($run->failedAttempts)->toHaveCount(1)
        ->and($run->failedAttempts[0]['error'])->toContain('HTTP 500');

    FrontDeskAgent::assertPromptedTimes(2);
});

it('answers from the fallback when the primary returns malformed json, a bogus label or an empty reply', function (array $badAnswer) {
    [$agent, $conversation, $burst] = fallbackTestRun();

    fakeFailingPrimary(fn () => $badAnswer);

    $run = app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);

    expect($run->provider)->toBe('openai')
        ->and($run->failedAttempts)->toHaveCount(1)
        ->and($run->failedAttempts[0]['provider'])->toBe('anthropic');

    FrontDeskAgent::assertPromptedTimes(2);
})->with([
    'malformed json' => [[]],
    'bogus label' => [['reply' => 'Hi', 'label' => 'bogus', 'wants_person' => false]],
    'empty reply' => [['reply' => '  ', 'label' => 'faq_answer', 'wants_person' => false]],
    'possible_danger missing' => [['reply' => 'Hi', 'label' => 'faq_answer', 'wants_person' => false]],
    'possible_danger not a boolean' => [['reply' => 'Hi', 'label' => 'faq_answer', 'wants_person' => false, 'possible_danger' => 'yes']],
]);

it('accepts a very long reply, because length is the send rule\'s job', function () {
    [$agent, $conversation, $burst] = fallbackTestRun();

    scriptFrontDeskReplies(['reply' => str_repeat('a', 3000)]);

    $run = app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);

    expect(mb_strlen($run->reply->reply))->toBe(3000)
        ->and($run->failedAttempts)->toBe([]);
});

it('reports every attempt, primary first, when all providers fail', function () {
    [$agent, $conversation, $burst] = fallbackTestRun();

    FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();

    try {
        app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);
        $this->fail('Expected FrontDeskUnavailable.');
    } catch (FrontDeskUnavailable $unavailable) {
        expect($unavailable->attempts)->toHaveCount(2)
            ->and($unavailable->attempts[0]['provider'])->toBe('anthropic')
            ->and($unavailable->attempts[1]['provider'])->toBe('openai')
            ->and($unavailable->getMessage())->toContain('anthropic')->toContain('openai');
    }

    FrontDeskAgent::assertPromptedTimes(2);
});

it('tries only the primary when the agent file has no fallback', function () {
    breakFile('agents/front-desk.md', fn (string $text) => preg_replace('/fallback:\n  - provider: openai\n    model: gpt-6.1-sol\n/', "fallback: []\n", $text));
    [$agent, $conversation, $burst] = fallbackTestRun();

    FrontDeskAgent::fake(fn () => throw new RuntimeException('down'))->preventStrayPrompts();

    try {
        app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);
        $this->fail('Expected FrontDeskUnavailable.');
    } catch (FrontDeskUnavailable $unavailable) {
        expect($unavailable->attempts)->toHaveCount(1)
            ->and($unavailable->attempts[0]['provider'])->toBe('anthropic');
    }

    FrontDeskAgent::assertPromptedTimes(1);
});

it('stops at the first provider that answers well', function () {
    [$agent, $conversation, $burst] = fallbackTestRun();

    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    $run = app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);

    expect($run->provider)->toBe('anthropic');

    FrontDeskAgent::assertPromptedTimes(1);
});

it('prompts no provider when there is nothing to answer', function () {
    [$agent, $conversation] = fallbackTestRun();

    scriptFrontDeskReplies(['reply' => 'Hello']);

    expect(fn () => app(RunFrontDeskAgent::class)->handle($agent, $conversation, new Collection))
        ->toThrow(InvalidArgumentException::class);

    FrontDeskAgent::assertNeverPrompted();
});

it('stops trying fallbacks once the next one would not finish inside the time budget', function () {
    [$agent, $conversation, $burst] = fallbackTestRun();

    config(['gate.agent.chain_budget_seconds' => 60]);
    fakeFailingPrimary(function () {
        test()->travel(41)->seconds();

        throw new RuntimeException('slow and then down');
    });

    try {
        app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);
        $this->fail('Expected FrontDeskUnavailable.');
    } catch (FrontDeskUnavailable $unavailable) {
        expect($unavailable->attempts)->toHaveCount(2)
            ->and($unavailable->attempts[0]['provider'])->toBe('anthropic')
            ->and($unavailable->attempts[0]['error'])->toContain('slow and then down')
            ->and($unavailable->attempts[1]['provider'])->toBe('openai')
            ->and($unavailable->attempts[1]['error'])->toBe('skipped: not enough time left in this run');
    }

    FrontDeskAgent::assertPromptedTimes(1);
});

it('keeps the file order and tries every entry that fits inside the time budget', function () {
    [$agent, $conversation, $burst] = fallbackTestRun();

    config(['gate.agent.chain_budget_seconds' => 100]);
    fakeFailingPrimary(function () {
        test()->travel(41)->seconds();

        throw new RuntimeException('slow and then down');
    });

    $run = app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst);

    expect($run->provider)->toBe('openai')
        ->and($run->failedAttempts)->toHaveCount(1)
        ->and($run->failedAttempts[0]['provider'])->toBe('anthropic');

    FrontDeskAgent::assertPromptedTimes(2);
});

it('prompts no provider when the first call of the run already used the time budget (D-35)', function () {
    [$agent, $conversation, $burst] = fallbackTestRun();

    config(['gate.agent.timeout_seconds' => 40, 'gate.agent.chain_budget_seconds' => 150]);
    scriptFrontDeskReplies(['reply' => 'Never asked.']);

    try {
        app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst, budgetFrom: now()->subSeconds(120));
        $this->fail('Expected FrontDeskUnavailable.');
    } catch (FrontDeskUnavailable $unavailable) {
        expect($unavailable->attempts)->toHaveCount(2)
            ->and(array_column($unavailable->attempts, 'error'))->toBe(['skipped: not enough time left in this run', 'skipped: not enough time left in this run'])
            ->and(array_column($unavailable->attempts, 'provider'))->toBe(['anthropic', 'openai']);
    }

    FrontDeskAgent::assertNeverPrompted();
});

it('runs the first provider when the first call of the run left room for one timeout (D-35)', function () {
    $this->freezeTime();

    [$agent, $conversation, $burst] = fallbackTestRun();

    config(['gate.agent.timeout_seconds' => 40, 'gate.agent.chain_budget_seconds' => 150]);
    scriptFrontDeskReplies(['reply' => 'What is the system doing?', 'label' => 'intake_question']);

    $run = app(RunFrontDeskAgent::class)->handle($agent, $conversation, $burst, budgetFrom: now()->subSeconds(100));

    expect($run->provider)->toBe('anthropic')
        ->and($run->startedAt->equalTo(now()->subSeconds(100)))->toBeTrue();

    FrontDeskAgent::assertPromptedTimes(1);
});
