<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\DraftResolution;
use App\Enums\MessageStatus;
use App\Events\CustomerRecordChanged;
use App\Models\Message;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * How the owner ends a held draft: send it as written, edit it and send it, or discard it (GATE-04, D-27).
 *
 * The draft is never deleted and never copied. The same message row moves from draft to sent (or withdrawn), and
 * keeps who decided, when, and the agent's own words if the owner changed them, so the owner's choices stay on the
 * record (D-54).
 */
class ResolveDraft
{
    public const string REPLACED = "The customer wrote again, here's the new draft";

    public const string ALREADY_HANDLED = 'This draft was already handled.';

    public function __construct(private readonly SendOwnerReply $sendOwnerReply) {}

    /**
     * The sentence for a draft that can no longer be acted on: a newer draft replaced it (D-28), or it was already
     * sent or discarded.
     */
    public static function whyGone(Message $draft): string
    {
        return $draft->resolution === DraftResolution::Replaced ? self::REPLACED : self::ALREADY_HANDLED;
    }

    /**
     * Send the draft to the customer. With no body it goes out as the agent wrote it; with a body the owner's words
     * go out instead, and the agent's are kept in original_body. Sending goes through SendOwnerReply (D-27), which
     * does not pause the assistant (D-25).
     *
     * A draft that was replaced or already handled is refused before anything is checked about the text, so the
     * owner is told the customer wrote again even when their edit also had a problem.
     *
     * @throws DomainException when the draft is gone, another reply is being sent, or the channel failed
     * @throws ValidationException when the text cannot or should not go out
     */
    public function approve(Message $draft, User $by, ?string $body = null): Message
    {
        $draft->refresh();

        if ($draft->status !== MessageStatus::Draft) {
            throw new DomainException(self::whyGone($draft));
        }

        $edited = $body !== null && trim($body) !== trim($draft->body);

        return $this->sendOwnerReply->sendDraft($draft, $body ?? $draft->body, $by, $edited);
    }

    /**
     * Withdraw the draft without sending anything, and note it on the customer's timeline.
     *
     * The draft is read again under a row lock, because a newer text may have replaced it or another tab may have
     * dealt with it since this page loaded.
     *
     * @throws DomainException when the draft is gone
     */
    public function discard(Message $draft, User $by): void
    {
        $contactId = DB::transaction(function () use ($draft, $by): int {
            $locked = Message::query()->lockForUpdate()->findOrFail($draft->id);

            if ($locked->status !== MessageStatus::Draft) {
                throw new DomainException(self::whyGone($locked));
            }

            $discardedAt = now();

            $locked->update([
                'status' => MessageStatus::Withdrawn,
                'resolution' => DraftResolution::Discarded,
                'resolved_by' => $by->id,
                'resolved_at' => $discardedAt,
            ]);

            $contact = $locked->conversation->contact;

            $contact->activities()->create([
                'type' => ActivityType::DraftDiscarded,
                'summary' => 'Draft discarded by owner',
                'subject_type' => $locked->getMorphClass(),
                'subject_id' => $locked->id,
                'occurred_at' => $discardedAt,
            ]);

            return $contact->id;
        });

        CustomerRecordChanged::dispatch($contactId);
    }
}
