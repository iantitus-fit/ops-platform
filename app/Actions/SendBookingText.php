<?php

namespace App\Actions;

use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\SendOrigin;
use App\Icm\ActiveConfig;
use App\Models\BookingSend;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use DomainException;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sends one stored confirmation or reminder text and records how it ended (BOOK-04, D-26, D-31).
 *
 * The text is the owner's own line from the live booking file, put together by code (service, day and time in the
 * business's zone) and wrapped by ComposeAutomatedText: the sign-off always, the AI disclosure when it is the
 * conversation's first automated text (A1). It goes out through OutboundSender as a booking text, so the chokepoint
 * makes the checks a text of this kind needs and no model words are anywhere in it (06 D-09).
 *
 * The row is claimed by one conditional update right before anything is sent, exactly the way owner alerts are claimed
 * (06 D-06, D-07): the same constant is the lease, and the same shape of update decides who sends. It is reused as it is,
 * not refactored out of SendOwnerAlert (research Pattern 7). Every outcome write is conditional on no outcome being
 * written, so a text is sent at most once.
 */
class SendBookingText
{
    /**
     * The most of an error message kept on a row.
     */
    private const int FAILURE_LENGTH = 200;

    public function __construct(
        private readonly OutboundSender $outboundSender,
        private readonly ComposeAutomatedText $composeAutomatedText,
        private readonly ActiveConfig $config,
    ) {}

    /**
     * Send the text of one row, unless it has ended or another sender holds it. This never throws: the sweep and the
     * booking that calls it have other work to do.
     */
    public function handle(BookingSend $send): void
    {
        if ($this->hasEnded($send) || ! $this->config->hasRoom('schedule_and_booking')) {
            return;
        }

        if ($this->claim($send) === null) {
            return;
        }

        $send->refresh();

        try {
            $this->send($send);
        } catch (Throwable $exception) {
            report($exception);

            $this->end($send, ['failed_at' => $this->now(), 'failure' => $this->reasonFrom($exception)]);
        }
    }

    /**
     * Compose the owner's line and send it in the customer's open conversation.
     *
     * @throws DomainException when the chokepoint refuses or the channel fails
     */
    private function send(BookingSend $send): void
    {
        $visit = $send->appointment;
        $settings = BookingSettings::fromConfig($this->config);

        if ($visit === null || $settings === null) {
            return;
        }

        $conversation = $visit->contact->openConversation()->first();

        if ($conversation === null) {
            return;
        }

        $zone = $this->config->timezone();
        $isReminder = $send->lead_minutes > 0;

        $line = $isReminder
            ? $settings->renderReminder($visit->service_name, $visit->starts_at, $zone)
            : $settings->renderConfirmation($visit->service_name, $visit->starts_at, $zone);

        $message = $this->outboundSender->send(
            $conversation,
            $this->composeAutomatedText->forReply($conversation, $line),
            MessageAuthor::Agent,
            SendOrigin::Automated,
            [
                'kind' => MessageKind::BookingText,
                'meta' => [
                    'appointment_id' => $visit->id,
                    'parts' => ['booking_line'],
                    'booking_send_id' => $send->id,
                ],
                'activity_summary' => $this->summaryFor($send, $visit->service_name, $visit->starts_at, $zone),
            ],
        );

        $this->end($send, ['sent_at' => $this->now(), 'message_id' => $message->id]);
    }

    /**
     * The words on the customer's timeline for a text that went out.
     */
    private function summaryFor(BookingSend $send, string $service, Carbon $startsAt, string $zone): string
    {
        $visit = $service.', '.BookingSettings::dayText($startsAt, $zone).' at '.BookingSettings::timeText($startsAt, $zone).'.';

        if ($send->lead_minutes === 0) {
            return 'Confirmation text sent for '.$visit;
        }

        $hours = intdiv($send->lead_minutes, 60);

        return 'Reminder text sent, '.$hours.($hours === 1 ? ' hour' : ' hours').' before: '.$visit;
    }

    /**
     * Whether the row has an outcome in memory. It is only a cheap first step: a stale copy still reads "open" after
     * another sender ended the row, so the conditional updates below are what decide.
     */
    private function hasEnded(BookingSend $send): bool
    {
        return $send->sent_at !== null || $send->skipped_at !== null || $send->failed_at !== null;
    }

    /**
     * Take the row to send it: one conditional update that sets send_claimed_at while no outcome is written and the row
     * has never been claimed or its claim is SendOwnerAlert::CLAIM_WAIT_MINUTES old (06 D-07). A claim exactly that old
     * can be taken again. The number of rows it changed decides who sends. A database error is reported and counts as
     * not claimed. Returns the moment of the claim, or null when this sender did not get the row.
     */
    private function claim(BookingSend $send): ?Carbon
    {
        $claimedAt = $this->now();

        try {
            $claimed = BookingSend::query()
                ->whereKey($send->id)
                ->whereNull('sent_at')
                ->whereNull('skipped_at')
                ->whereNull('failed_at')
                ->where(function (Builder $query): void {
                    $query->whereNull('send_claimed_at')
                        ->orWhere('send_claimed_at', '<=', $this->now()->subMinutes(SendOwnerAlert::CLAIM_WAIT_MINUTES));
                })
                ->update(['send_claimed_at' => $claimedAt]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $claimed === 1 ? $claimedAt : null;
    }

    /**
     * End the row: one conditional update of these columns that changes it only while sent_at, skipped_at and failed_at
     * are all still null. Returns whether this call ended it; a database error is reported and counts as not ended.
     *
     * @param  array<string, mixed>  $columns
     */
    private function end(BookingSend $send, array $columns): bool
    {
        try {
            $ended = BookingSend::query()
                ->whereKey($send->id)
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

        $send->forceFill($columns)->syncOriginal();

        return true;
    }

    /**
     * The exception's own message, cut short.
     */
    private function reasonFrom(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return mb_substr($message !== '' ? $message : class_basename($exception), 0, self::FAILURE_LENGTH);
    }

    /**
     * The present moment in UTC. Every moment this class stores or compares is UTC, because Eloquent's datetime cast and
     * the query builder keep an instance's own wall time and do not convert zones.
     */
    private function now(): Carbon
    {
        return now()->utc();
    }
}
