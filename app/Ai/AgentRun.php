<?php

namespace App\Ai;

use Carbon\CarbonImmutable;

/**
 * One successful agent run: the validated reply, who wrote it, and the providers that failed first.
 */
readonly class AgentRun
{
    /**
     * @param  list<array{provider: string, model: string, error: string}>  $failedAttempts  Earlier entries of the chain that failed, in order.
     * @param  ?CarbonImmutable  $startedAt  When the chain began, which a second call of the same run counts its time budget from (D-35).
     */
    public function __construct(
        public AgentReply $reply,
        public string $provider,
        public string $model,
        public array $failedAttempts,
        public ?CarbonImmutable $startedAt = null,
    ) {}
}
