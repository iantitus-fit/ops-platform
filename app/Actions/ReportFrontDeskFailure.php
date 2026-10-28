<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\MessageChannel;
use App\Events\CustomerRecordChanged;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use Illuminate\Support\Collection;

/**
 * The one door for "the front desk could not answer": a note on the customer's timeline and a front_desk_failed
 * alert to the owner, for the customer text the front desk had claimed (AGNT-04).
 *
 * It is safe to call twice for the same text. A run that crashed reports itself, and the queue's failed() hook can
 * report the same text again if the job is then failed as well, so an alert that already exists for the text means
 * the owner has been told and nothing more is written or sent.
 *
 * Callers pass fixed words only. An exception's message can hold SQL, a customer's words or a key, so it goes to the
 * log and never into a note or an alert.
 */
class ReportFrontDeskFailure
{
    public function __construct(private readonly RaiseOwnerAlert $raiseOwnerAlert) {}

    /**
     * @param  string  $noteDetail  Fixed words for the timeline, after "Front desk could not answer: ".
     * @param  string  $why  Fixed words for the owner's alert.
     */
    public function handle(Conversation $conversation, Message $text, string $noteDetail, string $why): void
    {
        $alreadyReported = OwnerAlert::query()
            ->where('message_id', $text->id)
            ->where('reason', AlertReason::FrontDeskFailed->value)
            ->exists();

        if ($alreadyReported) {
            return;
        }

        $contact = $conversation->contact;

        $contact->activities()->create([
            'type' => ActivityType::Note,
            'summary' => "Front desk could not answer: {$noteDetail}",
            'subject_type' => $text->getMorphClass(),
            'subject_id' => $text->id,
            'occurred_at' => now(),
        ]);

        CustomerRecordChanged::dispatch($contact->id);

        $this->raiseOwnerAlert->handle($conversation, AlertReason::FrontDeskFailed, $text, $why);
    }

    /**
     * The text a failed run is reported against: the newest text message the run claimed, or its newest row when it
     * claimed no text message, so a failed web-form-only burst is still reported (06 D-20).
     *
     * @param  Collection<int, Message>  $burst  the rows one run claimed, oldest first
     */
    public static function subjectOf(Collection $burst): Message
    {
        return $burst->last(fn (Message $message): bool => $message->channel === MessageChannel::Sms) ?? $burst->last();
    }
}
