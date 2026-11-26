<?php

namespace App\Ai;

use App\Icm\Walks\WalkTape;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The real front desk model, with each of its replies kept on a tape, inside a recording walk only.
 *
 * walks:record plays a walk against the folder as it is now and needs the model's real answer to every prompt. This class
 * stands where RunFrontDeskAgent stands, asks the real one it wraps with the same arguments, keeps the reply (the
 * AgentReply array the replay reads back) with the provider and model that wrote it, and hands the run on unchanged, so
 * the rest of the path runs as it does for a customer. When no provider could answer, that is noted on the tape and the
 * same exception goes on, so the walk sees what a customer's conversation would see and the command knows the recording
 * is incomplete. It is bound by the walk's runner while a scratch world is open and the binding is gone when the world
 * closes (10 D-03).
 */
class RecordingFrontDeskAgent extends RunFrontDeskAgent
{
    public function __construct(FrontDeskPrompt $prompt, private readonly RunFrontDeskAgent $inner, private readonly WalkTape $tape)
    {
        parent::__construct($prompt);
    }

    /**
     * Ask the wrapped agent, keep what it answered, and return the run as it came.
     *
     * @param  Collection<int, Message>  $burst
     *
     * @throws FrontDeskUnavailable when every provider failed
     */
    public function handle(AgentFile $agent, Conversation $conversation, Collection $burst, ?Message $waitingDraft = null, bool $coverBoth = false, ?CarbonInterface $budgetFrom = null): AgentRun
    {
        try {
            $run = $this->inner->handle($agent, $conversation, $burst, $waitingDraft, $coverBoth, $budgetFrom);
        } catch (FrontDeskUnavailable $unavailable) {
            $this->tape->noteRecordingFailure('the model', $unavailable->getMessage());

            throw $unavailable;
        }

        $this->tape->recordAgentReply($run->reply->toArray(), $run->provider, $run->model);

        return $run;
    }
}
