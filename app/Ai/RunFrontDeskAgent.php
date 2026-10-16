<?php

namespace App\Ai;

use App\Ai\Agents\FrontDeskAgent;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

/**
 * Asks the agent file's providers for a reply, one after the other, until one gives a usable answer.
 *
 * The SDK can fail over by itself, but only on connection errors, 429, 402 and overloaded answers. A 500,
 * a rejected key or broken JSON would throw straight out of it and leave the customer unanswered while a
 * working fallback sat unused. So this class prompts one provider at a time and treats every exception, and
 * every answer that fails AgentReply's checks, as a reason to try the next entry in the file's order.
 *
 * The whole chain has a time budget (gate.agent.chain_budget_seconds): a fallback starts only if the time already
 * spent plus one provider's timeout fits, so the budget only cuts the tail of the chain and never reorders it. The first
 * call of a run always tries its first provider.
 *
 * The second call of a run (the whole-thread draft, D-35) shares the first call's budget: it passes the first call's
 * start as `$budgetFrom`, and then every provider, the first included, must fit. That keeps one run inside
 * ProcessInbound's 300-second timeout: 150 seconds for both agent calls together, about 8 for each Jev call, and the
 * rest as margin. A run now makes up to four Jev calls: the reading of the customer's text, the reply's words check, an
 * easy part's words check and a whole-thread draft's words check (D-42, D-45).
 */
class RunFrontDeskAgent
{
    public function __construct(private readonly FrontDeskPrompt $prompt) {}

    /**
     * Answer the customer's burst of texts with the first provider in the chain that gives a valid reply.
     *
     * @param  Collection<int, Message>  $burst
     * @param  Message|null  $waitingDraft  The conversation's live draft, when a question is already with the owner.
     * @param  bool  $coverBoth  Ask for one reply that covers the waiting question and the new one, because it goes to the owner first (D-35).
     * @param  CarbonInterface|null  $budgetFrom  When an earlier call of the same run began. The time budget then counts from it and every
     *                                            provider, the first included, must fit; without it only the fallbacks are budgeted.
     *
     * @throws InvalidArgumentException when the burst is empty, since there is nothing to answer
     * @throws FrontDeskUnavailable when every provider failed
     */
    public function handle(AgentFile $agent, Conversation $conversation, Collection $burst, ?Message $waitingDraft = null, bool $coverBoth = false, ?CarbonInterface $budgetFrom = null): AgentRun
    {
        if ($burst->isEmpty()) {
            throw new InvalidArgumentException('There is no customer text to answer.');
        }

        $instructions = $this->prompt->instructions($agent, $conversation->contact, $waitingDraft, $coverBoth);
        $history = $this->prompt->history($conversation, $burst);
        $promptText = $this->prompt->promptText($burst);

        $failedAttempts = [];
        $startedAt = ($budgetFrom ?? now())->toImmutable();

        foreach ($agent->providerChain() as ['provider' => $provider, 'model' => $model]) {
            if (($budgetFrom !== null || $failedAttempts !== []) && $this->outOfTime($startedAt)) {
                $failedAttempts[] = [
                    'provider' => $provider,
                    'model' => $model,
                    'error' => 'skipped: not enough time left in this run',
                ];

                continue;
            }

            try {
                $response = (new FrontDeskAgent($instructions, $history))->prompt(
                    $promptText,
                    provider: [$provider => $model],
                    timeout: config('gate.agent.timeout_seconds'),
                );

                return new AgentRun(AgentReply::fromStructured($response->toArray()), $provider, $model, $failedAttempts, $startedAt);
            } catch (Throwable $exception) {
                report($exception);

                $failedAttempts[] = [
                    'provider' => $provider,
                    'model' => $model,
                    'error' => mb_substr($exception::class.': '.$exception->getMessage(), 0, 200),
                ];
            }
        }

        throw new FrontDeskUnavailable($failedAttempts);
    }

    /**
     * Whether another provider, given its full timeout, would end after the chain's time budget.
     */
    private function outOfTime(CarbonInterface $startedAt): bool
    {
        return $startedAt->diffInSeconds(now()) + (int) config('gate.agent.timeout_seconds') > (int) config('gate.agent.chain_budget_seconds');
    }
}
