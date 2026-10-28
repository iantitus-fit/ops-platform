<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Events\CustomerRecordChanged;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The one door for "the front desk could not answer": a note on the customer's timeline and a front_desk_failed
 * alert to the owner, for the customer text the front desk had claimed (AGNT-04).
 *
 * The report is made once per claim token, whichever of the run's own catch, the queue's failed() and RunFrontDesk's
 * own reports gets there first (06 D-11). The first to arrive takes the report claim, one conditional update that sets
 * failure_reported_at on the token's report row, and every other path returns without writing anything. The claim is
 * taken on the token's report row, so it does not matter which of the run's rows a caller passes. A report that cannot
 * be written gives its claim back and rethrows, so another path reports: a missing alert is worse than a second note
 * (02.1 IN-11).
 *
 * A text with no claim token, which means a row from before the token existed, keeps the old check: an alert that
 * already exists for the text means the owner has been told and nothing more is written or sent.
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
        $claimedRow = null;

        if ($text->claim_token !== null) {
            $claimedRow = $this->takeTheReportClaim($conversation, $text);

            if ($claimedRow === null) {
                return;
            }
        }

        try {
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
        } catch (Throwable $exception) {
            if ($claimedRow !== null) {
                $this->giveTheReportClaimBack($claimedRow);
            }

            throw $exception;
        }
    }

    /**
     * Give the report claim back so another path reports. When the database is what broke, this update can fail too:
     * its failure is reported, never thrown, so the exception that broke the report is the one that escapes
     * (06-REVIEW WR-03).
     */
    private function giveTheReportClaimBack(Message $claimedRow): void
    {
        try {
            Message::query()->whereKey($claimedRow->id)->update(['failure_reported_at' => null]);
        } catch (Throwable $releaseFailure) {
            report($releaseFailure);
        }
    }

    /**
     * Take the claim to report this text's run: one conditional update on the run's report row, so of two paths only
     * one wins it, on SQLite and on Postgres.
     *
     * @return Message|null the report row this call claimed, or null when another path already took the claim
     */
    private function takeTheReportClaim(Conversation $conversation, Message $text): ?Message
    {
        $rows = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::In)
            ->where('author', MessageAuthor::Customer)
            ->where('claim_token', $text->claim_token)
            ->orderBy('id')
            ->get();

        $reportRow = $rows->isEmpty() ? $text : self::subjectOf($rows);

        $claimed = Message::query()->whereKey($reportRow->id)->whereNull('failure_reported_at')->update(['failure_reported_at' => now()]);

        return $claimed === 1 ? $reportRow : null;
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
