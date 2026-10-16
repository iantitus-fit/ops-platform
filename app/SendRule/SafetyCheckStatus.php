<?php

namespace App\SendRule;

use App\Actions\RaiseOwnerAlert;
use App\Enums\AlertReason;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Models\Message;
use App\Models\SafetyCheckOutage;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Whether Jev, the safety check, is answering, and the owner's texts when that changes (D-50).
 *
 * A failed live call (the reading of the customer's text or a check of the model's words) or a failed health ping
 * marks Jev down by opening an outage; any answer marks it up by closing the open one. Being marked down changes no
 * call: every run still reads and checks, and each call's result moves the status. A reading that never ran, such as
 * no business folder being live, is not a Jev failure and never reaches this class.
 *
 * The owner hears from this class in three ways, and no more:
 *  1. One down alert per outage, sent right away at any hour.
 *  2. Never more than one down alert in any ALERT_GAP_MINUTES when Jev flaps. An outage whose down alert was held back
 *     sends it at its first failure that is that many minutes or more after the last one went out, and an outage that
 *     ends before that sends no back-up alert at all.
 *  3. One back-up alert when an outage whose down alert went out ends, naming how many customer texts arrived meanwhile.
 *
 * Changes are made under one cache lock, so two runs failing at the same moment open one outage and raise one alert.
 * A lock that cannot be had, or any error while the status is kept, is reported and skipped, never thrown: keeping
 * the status must not stop a customer's text from being answered.
 */
class SafetyCheckStatus
{
    /**
     * The fewest minutes between two down alerts, however often Jev flaps.
     */
    public const int ALERT_GAP_MINUTES = 60;

    public function __construct(private readonly RaiseOwnerAlert $raiseOwnerAlert) {}

    /**
     * Whether an outage is open: Jev's last call failed and nothing has answered since.
     */
    public function isDown(): bool
    {
        return SafetyCheckOutage::query()->whereNull('ended_at')->exists();
    }

    /**
     * A live call or the health ping failed: open an outage if none is open, and tell the owner once, right away,
     * unless a down alert already went out in the last ALERT_GAP_MINUTES.
     */
    public function recordFailure(string $error): void
    {
        $this->underLock(function () use ($error): void {
            $outage = SafetyCheckOutage::query()->whereNull('ended_at')->latest('id')->first()
                ?? SafetyCheckOutage::query()->create(['started_at' => now()]);

            $outage->last_error = mb_substr($error, 0, 255);
            $outage->save();

            if ($outage->down_alerted_at !== null || $this->aDownAlertWentOutRecently()) {
                return;
            }

            $this->raiseOwnerAlert->forBusiness(AlertReason::SafetyCheckDown, 'The safety check is not answering, so replies are held for you and customers get your holding reply.');

            $outage->down_alerted_at = now();
            $outage->save();
        });
    }

    /**
     * A live call or the health ping got a usable answer: close the open outage, if there is one, and when its down
     * alert went out, tell the owner once that the safety check is back and how many customer texts came in meanwhile.
     */
    public function recordSuccess(): void
    {
        $this->underLock(function (): void {
            $outage = SafetyCheckOutage::query()->whereNull('ended_at')->latest('id')->first();

            if ($outage === null) {
                return;
            }

            $outage->ended_at = now();
            $outage->save();

            if ($outage->down_alerted_at === null || $outage->up_alerted_at !== null) {
                return;
            }

            $this->raiseOwnerAlert->forBusiness(AlertReason::SafetyCheckDown, $this->backUpWords($this->customerTextsSince($outage)));

            $outage->up_alerted_at = now();
            $outage->save();
        });
    }

    /**
     * How many texts customers sent while the outage was open, across every conversation. Web forms and texts the
     * business sent are not counted.
     */
    private function customerTextsSince(SafetyCheckOutage $outage): int
    {
        return Message::query()
            ->where('direction', MessageDirection::In)
            ->where('author', MessageAuthor::Customer)
            ->where('channel', MessageChannel::Sms)
            ->where('created_at', '>=', $outage->started_at)
            ->where('created_at', '<=', now())
            ->count();
    }

    /**
     * What the owner is told when the safety check is back.
     */
    private function backUpWords(int $customerTexts): string
    {
        return match ($customerTexts) {
            0 => 'The safety check is back. No customer texts came in while it was down.',
            1 => 'The safety check is back. 1 customer text came in while it was down; its reply is in Needs you.',
            default => "The safety check is back. {$customerTexts} customer texts came in while it was down; their replies are in Needs you.",
        };
    }

    /**
     * Whether any outage sent its down alert less than ALERT_GAP_MINUTES ago.
     */
    private function aDownAlertWentOutRecently(): bool
    {
        return SafetyCheckOutage::query()
            ->where('down_alerted_at', '>', now()->subMinutes(self::ALERT_GAP_MINUTES))
            ->exists();
    }

    /**
     * Run a status change under the cache lock, reporting and skipping it when the lock cannot be had or it fails.
     *
     * @param  callable(): void  $change
     */
    private function underLock(callable $change): void
    {
        try {
            Cache::lock('safety-check-status', 10)->block(5, $change);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
