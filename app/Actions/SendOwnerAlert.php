<?php

namespace App\Actions;

use App\Channels\TextChannel;
use App\Models\OwnerAlert;
use Throwable;

class SendOwnerAlert
{
    /**
     * The most of an error message kept on an alert.
     */
    public const int FAILURE_LENGTH = 200;

    public function __construct(private readonly TextChannel $textChannel) {}

    /**
     * Send one stored alert by text and record how it ended.
     *
     * This never throws (D-19, Pitfall 17): the code that raises an alert is in the middle of
     * handling a customer, and a phone that refuses texts must not break that. A failure is
     * stored on the alert (failed_at and the reason) so it is visible, never silent. An alert
     * that has already ended is left alone, so calling this twice cannot text twice.
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
    }
}
