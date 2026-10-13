<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Events\CustomerRecordChanged;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TwilioStatusCallbackController extends Controller
{
    /**
     * Twilio's error code for a number that opted out of the Messaging Service.
     */
    private const string OPTED_OUT_CODE = '21610';

    /**
     * Record how Twilio says a text we sent has gone, and answer with the empty TwiML reply Twilio expects.
     *
     * Callbacks can arrive out of order, so a message only moves forward: queued, then sent, then delivered or
     * failed, and a late "sent" after "delivered" changes nothing. Statuses with no news for us (accepted, sending,
     * read and anything Twilio adds later) are ignored, and so is a sid we do not know: every one is answered 200
     * so Twilio does not retry. The message is found by an exact match on Twilio's sid, among the texts we sent.
     */
    public function __invoke(Request $request): Response
    {
        $sid = $this->input($request, 'MessageSid') ?? $this->input($request, 'SmsSid');
        $newStatus = $this->statusFor((string) $this->input($request, 'MessageStatus'));

        if ($sid !== null && $newStatus !== null) {
            $this->record($sid, $newStatus, $this->input($request, 'ErrorCode'));
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, [
            'Content-Type' => 'text/xml',
        ]);
    }

    /**
     * Move the message forward, and treat error 21610 as the carrier telling us the customer opted out.
     */
    private function record(string $sid, MessageStatus $newStatus, ?string $errorCode): void
    {
        $changedContactId = DB::transaction(function () use ($sid, $newStatus, $errorCode): ?int {
            $message = Message::query()
                ->where('provider', 'twilio')
                ->where('provider_message_id', $sid)
                ->where('direction', MessageDirection::Out)
                ->lockForUpdate()
                ->first();

            if ($message === null) {
                return null;
            }

            $changed = false;
            $currentRank = $this->rank($message->status);

            if ($currentRank !== null && $this->rank($newStatus) > $currentRank) {
                $meta = $message->meta ?? [];

                if ($newStatus === MessageStatus::Failed && $errorCode !== null) {
                    $meta['error_code'] = $errorCode;
                }

                $message->update(['status' => $newStatus, 'meta' => $meta ?: null]);
                $changed = true;
            }

            if ($newStatus === MessageStatus::Failed && $errorCode === self::OPTED_OUT_CODE) {
                $changed = $this->markOptedOut($message) || $changed;
            }

            return $changed ? $message->conversation->contact_id : null;
        });

        if ($changedContactId !== null) {
            CustomerRecordChanged::dispatch($changedContactId);
        }
    }

    /**
     * Mark the customer opted out when our record does not say so yet, and leave a note. Returns whether it did.
     */
    private function markOptedOut(Message $message): bool
    {
        $contact = $message->conversation->contact;

        if ($contact->isOptedOut()) {
            return false;
        }

        $contact->update(['sms_opted_out_at' => now()]);

        $contact->activities()->create([
            'type' => ActivityType::Note,
            'summary' => 'Not delivered: the customer has opted out of texts (the carrier reported it)',
            'subject_type' => $message->getMorphClass(),
            'subject_id' => $message->id,
            'occurred_at' => now(),
        ]);

        return true;
    }

    /**
     * What Twilio's status word means for us, or null for a status that carries no news.
     */
    private function statusFor(string $twilioStatus): ?MessageStatus
    {
        return match ($twilioStatus) {
            'sent' => MessageStatus::Sent,
            'delivered' => MessageStatus::Delivered,
            'failed', 'undelivered' => MessageStatus::Failed,
            default => null,
        };
    }

    /**
     * How far along a message is: queued, then sent, then delivered or failed. Null for a status that never
     * travels through Twilio (a draft, a withdrawn draft, a received text), which a callback must not touch.
     */
    private function rank(MessageStatus $status): ?int
    {
        return match ($status) {
            MessageStatus::Queued => 0,
            MessageStatus::Sent => 1,
            MessageStatus::Delivered, MessageStatus::Failed => 2,
            default => null,
        };
    }

    /**
     * One form field as text, or null when it is missing, empty or not plain text.
     */
    private function input(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
