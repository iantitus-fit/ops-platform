<?php

namespace App\Ai;

/**
 * One successful agent run: the validated reply, who wrote it, and the providers that failed first.
 */
readonly class AgentRun
{
    /**
     * @param  list<array{provider: string, model: string, error: string}>  $failedAttempts  Earlier entries of the chain that failed, in order.
     */
    public function __construct(
        public AgentReply $reply,
        public string $provider,
        public string $model,
        public array $failedAttempts,
    ) {}
}
