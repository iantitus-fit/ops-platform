<?php

namespace App\Actions;

use App\Channels\RecipientOptedOut;
use App\Channels\TextChannel;
use App\Enums\ActivityType;
use App\Events\CustomerRecordChanged;
use App\Models\OwnerAlert;
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

    public function __construct(private readonly TextChannel $textChannel) {}

    /**
     * Send one stored alert by text and record how it ended.
     *
     * This never throws (D-19, Pitfall 17): the code that raises an alert is in the middle of
     * handling a customer, and a phone that refuses texts must not break that. A failure is
     * stored on the alert (failed_at and the reason) so it is visible, never silent. An alert
     * that has already ended is left alone, so calling this twice cannot text twice.
     *
     * Whatever the outcome, the customer's timeline gets an owner_alerted entry saying what
     * happened (D-19).
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

        try {
            $alert->update(['sent_at' => now(), 'provider_message_id' => $providerMessageId]);
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->addToTimeline($alert, 'Owner alerted by text: '.$alert->reason->plainWords());
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
        try {
            $alert->update(['failed_at' => now(), 'failure' => $failure]);
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->addToTimeline($alert, "Owner alert not sent: {$failure}");
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
