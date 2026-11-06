<?php

namespace App\Actions;

use App\Enums\MessageAuthor;
use App\Enums\MessageKind;
use App\Enums\SendOrigin;
use App\Icm\ActiveConfig;
use App\Models\BookingSend;
use App\Models\Conversation;
use App\Rooms\RecordRoomEntry;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use DomainException;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sends one stored confirmation or reminder text and records how it ended (BOOK-04, D-26, D-27, D-30, D-31).
 *
 * The text is the owner's own line from the live booking file, put together by code (service, day and time in the
 * business's zone) and wrapped by ComposeAutomatedText: the sign-off always, the AI disclosure when it is the
 * conversation's first automated text (A1). It goes out through OutboundSender as a booking text, so the chokepoint
 * makes the checks a text of this kind needs and no model words are anywhere in it (06 D-09).
 *
 * The row is claimed by one conditional update right before anything is sent, exactly the way owner alerts are claimed
 * (06 D-06, D-07): the same constant is the lease, and the same shape of update decides who sends. It is reused as it is,
 * not refactored out of SendOwnerAlert (research Pattern 7). Every outcome write is conditional on no outcome being
 * written, so a text is sent at most once. A claim that is given back, because the text must wait, is given back by a
 * conditional update too.
 *
 * Everything is checked again each time a row is tried (D-31): the visit is still booked and still ahead, the time is
 * inside the send window, and no customer text is waiting unread. A text that cannot go out leaves one plain note on the
 * customer's timeline and never an owner alert (D-27); the owner reads the note on the card.
 */
class SendBookingText
{
    /**
     * The most of an error message kept on a row.
     */
    private const int FAILURE_LENGTH = 200;

    /**
     * What OutboundSender says when a front-desk reply to this customer is still being sent: a reason to wait, not to refuse.
     */
    private const string REPLY_IN_FLIGHT = 'Another reply to this customer is being sent.';

    /**
     * What OutboundSender says when the text is longer than a text may be.
     */
    private const string TOO_LONG = 'Texts are limited to 1,600 characters.';

    public function __construct(
        private readonly OutboundSender $outboundSender,
        private readonly ComposeAutomatedText $composeAutomatedText,
        private readonly ActiveConfig $config,
        private readonly RecordRoomEntry $recordRoomEntry,
        private readonly OpenConversationForText $openConversationForText,
    ) {}

    /**
     * Send the text of one row, unless it has ended or another sender holds it. This never throws: the sweep and the
     * booking that calls it have other work to do. An unexpected error is reported and ends the row as failed, as
     * SendOwnerAlert does; it is not retried by itself.
     */
    public function handle(BookingSend $send): void
    {
        if ($this->hasEnded($send) || ! $this->config->hasRoom('schedule_and_booking')) {
            return;
        }

        $claimedAt = $this->claim($send);

        if ($claimedAt === null) {
            return;
        }

        $send->refresh();

        try {
            $this->send($send, $claimedAt);
        } catch (Throwable $exception) {
            report($exception);

            $this->end($send, ['failed_at' => $this->now(), 'failure' => $this->reasonFrom($exception->getMessage())]);
        }
    }

    /**
     * Check everything again, then compose the owner's line and send it.
     *
     * @throws Throwable only for what no case below expects; handle() reports it and fails the row
     */
    private function send(BookingSend $send, Carbon $claimedAt): void
    {
        $visit = $send->appointment;

        if ($visit === null) {
            $this->skip($send, 'The visit is gone.', null);

            return;
        }

        if (! $visit->isBooked() || $visit->starts_at->lte(now())) {
            $this->drop($send);

            return;
        }

        $settings = BookingSettings::fromConfig($this->config);

        if ($settings === null) {
            $this->skip($send, 'The booking file is not in the business folder.', 'the booking file is not in the business folder');

            return;
        }

        $zone = $this->config->timezone();

        if (! $send->ignores_window) {
            $opening = $this->nextOpening($settings, $zone);

            if ($opening !== null) {
                $this->moveTo($send, $claimedAt, $opening);

                return;
            }
        }

        $contact = $visit->contact;
        $conversation = $contact->openConversation()->first();

        if ($conversation === null) {
            $why = match (true) {
                $contact->isOptedOut() => 'the customer has opted out',
                ! $contact->mayGetAutomaticTexts() => 'the customer has not agreed to automatic texts',
                blank($contact->phone_e164) => 'the customer has no phone number on file',
                default => null,
            };

            if ($why !== null) {
                $this->skip($send, ucfirst($why).'.', $why);

                return;
            }

            $conversation = $this->openConversationForText->handle($contact);
        }

        $when = BookingSettings::dayText($visit->starts_at, $zone).' at '.BookingSettings::timeText($visit->starts_at, $zone);

        $line = $send->lead_minutes > 0
            ? $settings->renderReminder($visit->service_name, $visit->starts_at, $zone)
            : $settings->renderConfirmation($visit->service_name, $visit->starts_at, $zone);

        try {
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
                    'activity_summary' => $this->sentSummary($send, $visit->service_name, $when),
                ],
            );
        } catch (DomainException $refusal) {
            $this->answerRefusal($send, $claimedAt, $conversation, $refusal);

            return;
        }

        $this->end($send, ['sent_at' => $this->now(), 'message_id' => $message->id]);
    }

    /**
     * The next moment the send window opens when now is outside it, or null when now is inside it (D-29). Both ends of the
     * window count as inside, judged to the minute, so a row due exactly when the window closes goes out even when the
     * sweep reaches it a few seconds late. Days are calendar days on the business wall clock (D-11), so a daylight-saving
     * change cannot move the wall time by an hour.
     */
    private function nextOpening(BookingSettings $settings, string $zone): ?Carbon
    {
        $local = now()->setTimezone($zone)->startOfMinute();
        $opens = $local->copy()->setTimeFromTimeString($settings->sendWindowStart);
        $closes = $local->copy()->setTimeFromTimeString($settings->sendWindowEnd);

        if ($local->greaterThanOrEqualTo($opens) && $local->lessThanOrEqualTo($closes)) {
            return null;
        }

        return $local->lessThan($opens) ? $opens : $local->copy()->addDay()->setTimeFromTimeString($settings->sendWindowStart);
    }

    /**
     * Hold a row that came due outside the send window until the window next opens: one conditional update that moves
     * due_at (stored in UTC) and gives the claim back, only while no outcome is written and the claim is still this
     * sender's (D-29, D-31). The sweep then leaves the row alone until its new due time.
     */
    private function moveTo(BookingSend $send, Carbon $claimedAt, Carbon $opening): void
    {
        try {
            BookingSend::query()
                ->whereKey($send->id)
                ->whereNull('sent_at')
                ->whereNull('skipped_at')
                ->whereNull('failed_at')
                ->where('send_claimed_at', $claimedAt)
                ->update(['due_at' => $opening->copy()->utc(), 'send_claimed_at' => null]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * What to do with a refused send (D-26, D-27). A text held for an unread customer text, or while a front-desk reply is
     * in flight, waits: the claim is given back and the next sweep tries again. A visit that is no longer booked or
     * ahead ends the row. A refusal to text this customer ends it with one note. A channel failure ends it as failed with
     * no further note, because OutboundSender already wrote its own. Anything else is a fault in our own code: reported,
     * and the row fails.
     */
    private function answerRefusal(BookingSend $send, Carbon $claimedAt, Conversation $conversation, DomainException $refusal): void
    {
        $sentence = $refusal->getMessage();

        $why = match (true) {
            str_starts_with($sentence, 'Opted out by texting STOP') => 'the customer has opted out',
            $sentence === OutboundSender::NOT_AGREED => 'the customer has not agreed to automatic texts',
            str_starts_with($sentence, 'This conversation is closed') => 'the conversation is closed',
            $sentence === 'The assistant is paused in this conversation.' => 'the conversation is paused',
            str_starts_with($sentence, 'There is no phone number on file') => 'the customer has no phone number on file',
            $sentence === self::TOO_LONG => 'the text is longer than '.number_format(SendOwnerReply::MAX_LENGTH).' characters',
            default => null,
        };

        if ($sentence === OutboundSender::UNREAD_CUSTOMER_TEXT) {
            $this->giveBack($send, $claimedAt, markWaited: true);
        } elseif ($sentence === self::REPLY_IN_FLIGHT) {
            $this->giveBack($send, $claimedAt, markWaited: false);
        } elseif ($sentence === OutboundSender::APPOINTMENT_NOT_AHEAD) {
            $this->drop($send);
        } elseif ($why !== null) {
            $this->skip($send, ucfirst($why).'.', $why);
        } elseif ($sentence === OutboundSender::CARRIER_OPTED_OUT || str_starts_with($sentence, OutboundSender::NOT_SENT)) {
            $this->end($send, ['failed_at' => $this->now(), 'failure' => $this->reasonFrom($sentence)]);
        } else {
            report($refusal);

            $this->end($send, ['failed_at' => $this->now(), 'failure' => $this->reasonFrom($sentence)]);
        }
    }

    /**
     * End a row whose visit is no longer booked or no longer ahead: the text would never be right to send. It ends with
     * no note, unless the text had been waiting on a customer's unread text, when the customer's thread would otherwise
     * show a reminder that never came and no reason (D-26, D-27).
     */
    private function drop(BookingSend $send): void
    {
        $why = 'an earlier text from the customer was still waiting to be read';

        $this->skip(
            $send,
            'The visit is no longer booked or no longer ahead.',
            $send->waited_on_unread_at !== null ? $why : null,
        );
    }

    /**
     * End the row as skipped with the reason stored on it. When $noteReason is given, the copy that ended the row writes
     * one timeline note, "<Confirmation|Reminder> text not sent: <reason>." and no owner alert is raised (D-27).
     */
    private function skip(BookingSend $send, string $failure, ?string $noteReason): void
    {
        if (! $this->end($send, ['skipped_at' => $this->now(), 'failure' => $failure]) || $noteReason === null) {
            return;
        }

        try {
            $contact = $send->appointment?->contact;

            if ($contact === null) {
                return;
            }

            $this->recordRoomEntry->handle(
                $contact,
                'schedule_and_booking',
                'booking_text_not_sent',
                ($send->lead_minutes === 0 ? 'Confirmation' : 'Reminder').' text not sent: '.$noteReason.'.',
                $send,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The words on the customer's timeline for a text that went out.
     */
    private function sentSummary(BookingSend $send, string $service, string $when): string
    {
        if ($send->lead_minutes === 0) {
            return 'Confirmation text sent for '.$service.', '.$when.'.';
        }

        $hours = intdiv($send->lead_minutes, 60);

        return 'Reminder text sent, '.$hours.($hours === 1 ? ' hour' : ' hours').' before: '.$service.', '.$when.'.';
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
     * Give the claim back so the next sweep can take the row again: one conditional update that clears send_claimed_at
     * only while no outcome is written and the claim is still this sender's. When the text waited on an unread customer
     * text, waited_on_unread_at is set the first time, so a later drop knows to leave a note (D-26). A database error is
     * reported; the claim then simply runs out its lease.
     */
    private function giveBack(BookingSend $send, Carbon $claimedAt, bool $markWaited): void
    {
        try {
            if ($markWaited) {
                BookingSend::query()
                    ->whereKey($send->id)
                    ->whereNull('sent_at')
                    ->whereNull('skipped_at')
                    ->whereNull('failed_at')
                    ->whereNull('waited_on_unread_at')
                    ->update(['waited_on_unread_at' => $this->now()]);
            }

            BookingSend::query()
                ->whereKey($send->id)
                ->whereNull('sent_at')
                ->whereNull('skipped_at')
                ->whereNull('failed_at')
                ->where('send_claimed_at', $claimedAt)
                ->update(['send_claimed_at' => null]);
        } catch (Throwable $exception) {
            report($exception);
        }
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
     * A message cut short to what a row keeps.
     */
    private function reasonFrom(string $message): string
    {
        $message = trim($message);

        return mb_substr($message !== '' ? $message : 'The text could not be sent.', 0, self::FAILURE_LENGTH);
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
