<?php

namespace App\Ai;

use App\Icm\Walks\WalkTape;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The front desk model as a recording, inside a walk only.
 *
 * A walk plays the real front desk path against a candidate folder, and the one thing it must never do is ask a real
 * model. This class stands where RunFrontDeskAgent stands and hands back the reply the tape holds for the turn under
 * way, in order. When the tape has nothing left it refuses with the same exception a front desk with no working
 * provider throws, so nothing is guessed and no reply recorded for another turn is ever used (10 D-27); the walk then
 * needs a new recording. It is bound by the walk's runner while a scratch world is open and the binding is gone when
 * the world closes, so every real customer text still reaches the real model.
 */
class ReplayFrontDeskAgent extends RunFrontDeskAgent
{
    public function __construct(FrontDeskPrompt $prompt, private readonly WalkTape $tape)
    {
        parent::__construct($prompt);
    }

    /**
     * The recorded reply for this turn, shaped as a successful run so the rest of the path runs unchanged.
     *
     * @param  Collection<int, Message>  $burst
     *
     * @throws FrontDeskUnavailable when the recording holds no model reply for this turn
     */
    public function handle(AgentFile $agent, Conversation $conversation, Collection $burst, ?Message $waitingDraft = null, bool $coverBoth = false, ?CarbonInterface $budgetFrom = null): AgentRun
    {
        $stored = $this->tape->nextAgentReply();

        if ($stored === null) {
            throw new FrontDeskUnavailable([[
                'provider' => 'recorded',
                'model' => 'recorded',
                'error' => 'the recording holds no model reply for this turn',
            ]]);
        }

        return new AgentRun(AgentReply::fromStructured($stored), 'recorded', 'recorded', [], now()->toImmutable());
    }
}
