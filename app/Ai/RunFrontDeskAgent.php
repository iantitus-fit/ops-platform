<?php

namespace App\Ai;

use App\Ai\Agents\FrontDeskAgent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Asks the agent file's providers for a reply, one after the other, until one gives a usable answer.
 */
class RunFrontDeskAgent
{
    public function __construct(private readonly FrontDeskPrompt $prompt) {}

    /**
     * Answer the customer's burst of texts with the first provider in the chain that gives a valid reply.
     *
     * @param  Collection<int, Message>  $burst
     *
     * @throws FrontDeskUnavailable when every provider failed
     */
    public function handle(AgentFile $agent, Conversation $conversation, Collection $burst): AgentRun
    {
        $instructions = $this->prompt->instructions($agent, $conversation->contact);
        $history = $this->prompt->history($conversation, $burst);
        $promptText = $this->prompt->promptText($burst);

        $failedAttempts = [];

        foreach ($agent->providerChain() as ['provider' => $provider, 'model' => $model]) {
            try {
                $response = (new FrontDeskAgent($instructions, $history))->prompt(
                    $promptText,
                    provider: [$provider => $model],
                    timeout: config('gate.agent.timeout_seconds'),
                );

                return new AgentRun(AgentReply::fromStructured($response->toArray()), $provider, $model, $failedAttempts);
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
}
