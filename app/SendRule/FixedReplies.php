<?php

namespace App\SendRule;

use App\Icm\ActiveConfig;
use App\Icm\BusinessClock;
use Carbon\CarbonInterface;

/**
 * The owner's two fixed replies with their fill-ins completed by code: {owner_name}, {business_name}
 * and {next_open}. Code writes these texts, so the time they promise is always true (D-13), and
 * they skip the classifier when they send (D-12). The send path adds the disclosure and sign-off.
 */
class FixedReplies
{
    public function __construct(private ActiveConfig $config, private BusinessClock $clock) {}

    /**
     * The reply sent when a question is one only the owner can answer.
     */
    public function holdingReply(CarbonInterface $moment): string
    {
        return $this->render($this->config->replies()['holding_reply'], $moment);
    }

    /**
     * The reply sent when a customer asks to talk with a person.
     */
    public function handoffReply(CarbonInterface $moment): string
    {
        return $this->render($this->config->replies()['handoff_reply'], $moment);
    }

    private function render(string $template, CarbonInterface $moment): string
    {
        return strtr($template, [
            '{owner_name}' => $this->config->replies()['owner_name'],
            '{business_name}' => $this->config->business()['name'],
            '{next_open}' => $this->clock->nextOpenPhrase($moment),
            '{today_or_next_open}' => $this->clock->todayOrNextOpenPhrase($moment),
        ]);
    }
}
