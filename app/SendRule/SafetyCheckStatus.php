<?php

namespace App\SendRule;

use App\Actions\RaiseOwnerAlert;
use App\Enums\AlertReason;
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
     * A live call or the health ping got a usable answer: close the open outage, if there is one.
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
        });
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
