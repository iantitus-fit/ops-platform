<?php

namespace App\SendRule;

use App\Icm\ActiveConfig;
use App\Icm\BusinessClock;
use Carbon\CarbonInterface;

/**
 * The owner's fixed replies, all filled in by code and sent without the classifier, with these fill-ins: {owner_name}, {business_name},
 * {next_open}, {today_or_next_open}, {next_opening} and {check_in_time}. Code writes these texts, so
 * every time they promise is computed from business/hours.md at the moment of sending and is always
 * true (D-13, D-25), and they skip the classifier when they send (D-12). The send path adds the
 * disclosure and sign-off.
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

    /**
     * The reply sent when a customer complains. A version that went live before this reply existed has no
     * complaint_reply, so it falls back to the holding reply and keeps sending.
     */
    public function complaintReply(CarbonInterface $moment): string
    {
        return $this->replyOrHolding('complaint_reply', $moment);
    }

    /**
     * The reply sent when a customer's message was answered in part and the rest waits for the owner.
     */
    public function partialHoldingReply(CarbonInterface $moment): string
    {
        return $this->replyOrHolding('partial_holding_reply', $moment);
    }

    /**
     * The reply sent when a customer writes again to ask whether anyone saw their question.
     */
    public function checkInReply(CarbonInterface $moment): string
    {
        return $this->replyOrHolding('check_in_reply', $moment);
    }

    /**
     * The reply sent when a customer adds a detail to a question the owner already holds.
     */
    public function addedInfoReply(CarbonInterface $moment): string
    {
        return $this->replyOrHolding('added_info_reply', $moment);
    }

    /**
     * The reply sent when a customer asks for a discount. It is optional in the folder, so a folder without
     * one sends the holding reply.
     */
    public function discountReply(CarbonInterface $moment): string
    {
        return $this->replyOrHolding('discount_reply', $moment);
    }

    /**
     * The line that goes in front of a price sentence sent a second time, for a price that stayed the
     * `same`, went `higher`, went `lower` or `changed`. Null when the folder has no such lead (a version made
     * before leads existed), and then the sentence goes alone.
     */
    public function priceLead(string $direction, CarbonInterface $moment): ?string
    {
        $leads = $this->config->replies()['price_leads'] ?? null;
        $lead = is_array($leads) ? ($leads[$direction] ?? null) : null;

        return is_string($lead) ? $this->render($lead, $moment) : null;
    }

    /**
     * A version that went live before a reply existed has no such field; the holding reply keeps it sending.
     */
    private function replyOrHolding(string $field, CarbonInterface $moment): string
    {
        $replies = $this->config->replies();
        $template = $replies[$field] ?? null;

        return $this->render(is_string($template) ? $template : $replies['holding_reply'], $moment);
    }

    private function render(string $template, CarbonInterface $moment): string
    {
        return strtr($template, [
            '{owner_name}' => $this->config->replies()['owner_name'],
            '{business_name}' => $this->config->business()['name'],
            '{next_open}' => $this->clock->nextOpenPhrase($moment),
            '{today_or_next_open}' => $this->clock->todayOrNextOpenPhrase($moment),
            '{next_opening}' => $this->clock->nextOpeningPhrase($moment),
            '{check_in_time}' => $this->checkInTime($moment),
        ]);
    }

    /**
     * The check-in time when the owner set check_in_hours, otherwise the next opening alone, so a
     * version made before the setting existed can never show the braces to a customer.
     */
    private function checkInTime(CarbonInterface $moment): string
    {
        $hours = $this->config->replies()['check_in_hours'] ?? null;

        return is_int($hours)
            ? $this->clock->checkInPhrase($moment, $hours)
            : $this->clock->nextOpeningPhrase($moment);
    }
}
