<?php

namespace App\Ai;

use RuntimeException;

/**
 * Every provider the agent file names failed or answered badly, so there is no reply to propose.
 *
 * The next plan turns this one exception into an owner alert and a held conversation.
 */
class FrontDeskUnavailable extends RuntimeException
{
    /**
     * @param  list<array{provider: string, model: string, error: string}>  $attempts  Each failed try, in the order they were made.
     */
    public function __construct(public array $attempts)
    {
        $tried = implode('; ', array_map(
            fn (array $attempt): string => "{$attempt['provider']}/{$attempt['model']}: {$attempt['error']}",
            $attempts,
        ));

        parent::__construct('No front desk provider could answer. '.($tried === '' ? 'None was tried.' : $tried));
    }
}
