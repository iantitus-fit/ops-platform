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

    public const string ALREADY_ANSWERED = 'Already answered';

    public function __construct(private readonly SendOwnerReply $sendOwnerReply) {}

    /**
     * The sentence for a draft that can no longer be acted on: a newer draft replaced it (D-28), a price sentence
     * already answered it (D-21), or it was already sent or discarded.
     */
    public static function whyGone(Message $draft): string
    {
        return match ($draft->resolution) {
            DraftResolution::Replaced => self::REPLACED,
            DraftResolution::Answered => self::ALREADY_ANSWERED,
            default => self::ALREADY_HANDLED,
        };
    }

    /**
     * Send the draft to the customer. With no body it goes out as the agent wrote it; with a body the owner's words
     * go out instead, and the agent's are kept in original_body. Sending goes through SendOwnerReply (D-27), which
     * does not pause the assistant (D-25).
     *
     * A draft that was replaced or already handled is refused before anything is checked about the text, so the
     * owner is told the customer wrote again even when their edit also had a problem.
     *
     * `$addBookingLink` only asks (D-45): the approve step that drew the booking link line passes the owner's box. It
     * defaults to false so no other caller asks for a link, and SendOwnerReply decides, checking every condition again.
     * `$addVisitLine` only asks too, for the visit line the approve step drew (D-39 amended, settled as D-45).
     *
     * @throws DomainException when the draft is gone, another reply is being sent, or the channel failed
     * @throws ValidationException when the text cannot or should not go out
     */
    public function approve(Message $draft, User $by, ?string $body = null, bool $addBookingLink = false, bool $addVisitLine = false): Message
    {
        $draft->refresh();

        if ($draft->status !== MessageStatus::Draft) {
            throw new DomainException(self::whyGone($draft));
        }

        $edited = $body !== null && trim($body) !== trim($draft->body);

        return $this->sendOwnerReply->sendDraft($draft, $body ?? $draft->body, $by, $edited, $addBookingLink, $addVisitLine);
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

    /**
     * Close a held draft because a text that went out to the customer already answered it (D-21).
     *
     * The draft is kept as it is, with its words and its verdict, and marked answered with the id of the answering
     * text. No owner is recorded, because code closed it, and no owner alert is raised. It never throws for a draft
     * that is gone: code calls this, not the owner, so a draft already replaced, approved or discarded is left alone
     * and false comes back. The draft is read again under a row lock, because a newer text or the owner may have
     * dealt with it since the caller looked.
     *
     * Call it only after the answering text went out.
     *
     * @return bool true when the draft was closed, false when it was no longer a draft
     */
    public function closeAsAnswered(Message $draft, Message $answer): bool
    {
        $contactId = DB::transaction(function () use ($draft, $answer): ?int {
            $locked = Message::query()->lockForUpdate()->findOrFail($draft->id);

            if ($locked->status !== MessageStatus::Draft) {
                return null;
            }

            $answeredAt = now();

            $locked->update([
                'status' => MessageStatus::Withdrawn,
                'resolution' => DraftResolution::Answered,
                'resolved_by' => null,
                'resolved_at' => $answeredAt,
                'meta' => [...($locked->meta ?? []), 'answered_by' => $answer->id],
            ]);

            $contact = $locked->conversation->contact;

            $contact->activities()->create([
                'type' => ActivityType::DraftAnswered,
                'summary' => 'Draft closed: a price sentence answered it',
                'subject_type' => $locked->getMorphClass(),
                'subject_id' => $locked->id,
                'occurred_at' => $answeredAt,
            ]);

            return $contact->id;
        });

        if ($contactId === null) {
            return false;
        }

        CustomerRecordChanged::dispatch($contactId);

        return true;
    }
}
