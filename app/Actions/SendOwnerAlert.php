<?php

namespace App\Actions;

use App\Channels\RecipientOptedOut;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Events\CustomerRecordChanged;
use App\Models\OwnerAlert;
use Illuminate\Contracts\Database\Query\Builder;
use Throwable;

class SendOwnerAlert
{
    /**
     * The most of an error message kept on an alert.
     */
    public const int FAILURE_LENGTH = 200;

    /**
     * What the owner reads when their own phone has texted STOP to the business number (Pitfall 17).
     */
    public const string OWNER_OPTED_OUT = 'Your phone opted out of texts (STOP). Text START to the business number to get alerts again.';

    /**
     * How long a send claim is respected, in minutes, measured from the claim. It is well above the
     * text channel's 10-second request timeout (TwilioTextChannel), so a slow send that is still
     * running is never taken again. A claim with no outcome after this long means its sender died,
     * and the alert is sent again. The worst case is an urgent alert lost to a crash going out about
     * 3 minutes late, when the every-minute sweep next runs (06 D-07).
     */
    public const int CLAIM_WAIT_MINUTES = 2;

    public function __construct(private readonly TextChannel $textChannel) {}

    /**
     * Send one stored alert by text and record how it ended.
     *
     * This never throws (D-19, Pitfall 17): the code that raises an alert is in the middle of
     * handling a customer, and a phone that refuses texts must not break that. A failure is
     * stored on the alert (failed_at and the reason) so it is visible, never silent.
     *
     * The alert is claimed right before the text goes out, by one conditional update whose
     * affected-row count decides who texts (06 D-07, WR-03). Two copies of one alert, or a second
     * worker that reaches the alert while the first is still sending, therefore text it once. The
     * in-memory check below is only a cheap first step: a stale copy still reads "open" after
     * another sender ended the alert, so it cannot stop a duplicate on its own. A claim with no
     * outcome is taken again after CLAIM_WAIT_MINUTES, so the only duplicate left is a crash after
     * the carrier accepted the text and before the outcome was written (06 D-06, D-07).
     *
     * Whatever the outcome, the customer's timeline gets an owner_alerted entry saying what
     * happened (D-19), written only by the copy that ended the alert.
     */
    public function handle(OwnerAlert $alert): void
    {
        if ($alert->sent_at !== null || $alert->skipped_at !== null || $alert->failed_at !== null) {
            return;
        }

        if (blank($alert->to_phone)) {
            $this->recordFailure($alert, 'No phone on file');

            return;
        }

        if (! $this->claimToSend($alert)) {
            return;
        }

        try {
            $providerMessageId = $this->textChannel->send($alert->to_phone, $alert->body);
        } catch (RecipientOptedOut) {
            $this->recordFailure($alert, self::OWNER_OPTED_OUT);

            return;
        } catch (Throwable $exception) {
            report($exception);

            $this->recordFailure($alert, $this->reasonFrom($exception));

            return;
        }

        if ($this->end($alert, ['sent_at' => now(), 'provider_message_id' => $providerMessageId])) {
            $this->addToTimeline($alert, 'Owner alerted by text: '.$alert->reason->plainWords());
        }
    }

    /**
     * End a delayed alert without sending it, with the reason stored on the alert and shown on
     * the customer's timeline. An alert that has already ended is left alone. This never throws.
     */
    public function skip(OwnerAlert $alert, string $why): void
    {
        if ($alert->sent_at !== null || $alert->skipped_at !== null || $alert->failed_at !== null) {
            return;
        }

        try {
            $alert->update(['skipped_at' => now(), 'failure' => $why]);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        $this->addToTimeline($alert, "Owner alert not sent: {$why}");
    }

    /**
     * The exception's own message, cut short. It is the channel's words and never includes a
     * credential, because the channels build their messages without one.
     */
    private function reasonFrom(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return mb_substr($message !== '' ? $message : class_basename($exception), 0, self::FAILURE_LENGTH);
    }

    private function recordFailure(OwnerAlert $alert, string $failure): void
    {
        if ($this->end($alert, ['failed_at' => now(), 'failure' => $failure])) {
            $this->addToTimeline($alert, "Owner alert not sent: {$failure}");
        }
    }

    /**
     * Take the alert to text it: one conditional update that sets send_claimed_at while no outcome
     * is written and the alert has never been claimed or its claim is CLAIM_WAIT_MINUTES old. The
     * number of rows it changed decides who sends: 1 means this sender may text, 0 means another
     * sender has the alert or it has ended. A database error is reported and counts as not
     * claimed, so this never throws.
     */
    private function claimToSend(OwnerAlert $alert): bool
    {
        $claimedAt = now();

        try {
            $claimed = OwnerAlert::query()
                ->whereKey($alert->id)
                ->whereNull('sent_at')
                ->whereNull('skipped_at')
                ->whereNull('failed_at')
                ->where(function (Builder $query): void {
                    $query->whereNull('send_claimed_at')
                        ->orWhere('send_claimed_at', '<=', now()->subMinutes(self::CLAIM_WAIT_MINUTES));
                })
                ->update(['send_claimed_at' => $claimedAt]);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        if ($claimed !== 1) {
            return false;
        }

        $alert->forceFill(['send_claimed_at' => $claimedAt])->syncOriginalAttribute('send_claimed_at');

        return true;
    }

    /**
     * End the alert: one conditional update of these columns that changes the row only while
     * sent_at, skipped_at and failed_at are all still null. When it changed the row the columns
     * are put on the in-memory alert too, so a caller that reads $alert->sent_at (the safety
     * check's claim) sees the outcome. Returns whether this call ended the alert; a database
     * error is reported and counts as not ended, so this never throws.
     *
     * @param  array<string, mixed>  $columns
     */
    private function end(OwnerAlert $alert, array $columns): bool
    {
        try {
            $ended = OwnerAlert::query()
                ->whereKey($alert->id)
                ->whereNull('sent_at')
                ->whereNull('skipped_at')
                ->whereNull('failed_at')
                ->update($columns);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        if ($ended !== 1) {
            return false;
        }

        $alert->forceFill($columns)->syncOriginal();

        return true;
    }

    /**
     * Add the owner_alerted entry to the contact of the alert's conversation and have their
     * card re-rendered. An alert about the whole business has no conversation and so no
     * customer timeline to write to (D-50). A timeline that cannot be written is logged, never
     * thrown.
     */
    private function addToTimeline(OwnerAlert $alert, string $summary): void
    {
        if ($alert->conversation_id === null) {
            return;
        }

        try {
            $contact = $alert->conversation->contact;

            $contact->activities()->create([
                'type' => ActivityType::OwnerAlerted,
                'summary' => $summary,
                'subject_type' => $alert->getMorphClass(),
                'subject_id' => $alert->id,
                'occurred_at' => now(),
            ]);

            CustomerRecordChanged::dispatch($contact->id);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
