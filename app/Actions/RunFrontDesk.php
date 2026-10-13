<?php

namespace App\Actions;

use App\Ai\AgentFile;
use App\Ai\FrontDeskPrompt;
use App\Ai\FrontDeskUnavailable;
use App\Ai\RunFrontDeskAgent;
use App\Channels\RecipientOptedOut;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\DraftResolution;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\PausedReason;
use App\Enums\ReplyLabel;
use App\Enums\SendOrigin;
use App\Events\CustomerRecordChanged;
use App\Icm\ActiveConfig;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\SendRule\AsksForPerson;
use App\SendRule\DecideSendOrDraft;
use App\SendRule\LifeSafety;
use App\SendRule\Verdict;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Answers one claimed burst of customer texts: the agent writes a reply, the send rule decides, and the reply is
 * either sent through the one outbound path or kept as a draft with its verdict.
 *
 * A draft holds only the agent's own words. The disclosure and the sign-off are added when a text actually goes
 * out, so they land on whichever automated text reaches the customer first (D-50).
 *
 * While the assistant is paused in the conversation, the agent is not asked at all: the owner is alerted to the
 * customer's newest text instead, once per burst (D-24).
 *
 * A customer who opted out of texts is never answered: the owner is alerted to the newest text so they can call, and
 * the agent is not asked (D-40).
 *
 * Before the agent runs, two things can stop the run for good. A conversation that has reached the reply cap pauses
 * and alerts the owner (D-52), so two auto-responders cannot text each other all night. A customer who asks for a
 * person in words that cannot mean anything else is handed to the owner (D-23, D-47). The agent's own wants_person
 * and Jev's asks_for_person, once the agent has answered, lead to the same handoff, and the agent's words are then
 * not stored or sent.
 *
 * A front desk that cannot answer (every model failed, no agent for texts) or cannot send alerts the owner and
 * returns, because the customer's burst is already claimed (AGNT-04). Anything else that goes wrong, such as a
 * database error, does throw, and ProcessInbound turns it into a note and an owner alert so a claimed text is never
 * left silent. A send the channel refused stays on the record as failed. A send that was only refused, such as while
 * the owner is sending to the same customer, becomes a draft.
 */
class RunFrontDesk
{
    public function __construct(
        private readonly ActiveConfig $activeConfig,
        private readonly RunFrontDeskAgent $runFrontDeskAgent,
        private readonly FrontDeskPrompt $prompt,
        private readonly ComposeAutomatedText $composeAutomatedText,
        private readonly DecideSendOrDraft $decideSendOrDraft,
        private readonly OutboundSender $outboundSender,
        private readonly RaiseOwnerAlert $raiseOwnerAlert,
        private readonly RecordIntakeAnswers $recordIntakeAnswers,
        private readonly SendFixedReply $sendFixedReply,
        private readonly PauseConversation $pauseConversation,
        private readonly SendLifeSafetyLine $sendLifeSafetyLine,
        private readonly ReportFrontDeskFailure $reportFrontDeskFailure,
    ) {}

    /**
     * @param  Collection<int, Message>  $burst  The customer's texts this run claimed, oldest first. Must not be empty.
     */
    public function handle(Conversation $conversation, Collection $burst): void
    {
        $this->answerDanger($burst);

        if ($conversation->contact->refresh()->isOptedOut()) {
            $this->alertAboutOptedOutBurst($conversation, $burst);

            return;
        }

        if ($conversation->refresh()->isPaused()) {
            $this->alertAboutPausedBurst($conversation, $burst);

            return;
        }

        if ($this->replyCapReached($conversation)) {
            $this->raiseOwnerAlert->handle($conversation, AlertReason::ReplyCap, $burst->last(), 'Reply limit reached');
            $this->pauseConversation->handle($conversation, PausedReason::ReplyCap);

            return;
        }

        if (AsksForPerson::inText($this->prompt->promptText($burst))) {
            $this->handOffToOwner($conversation, $burst);

            return;
        }

        $agent = AgentFile::forChannel($this->activeConfig, 'sms');

        if ($agent === null) {
            $this->reportFrontDeskFailure->handle($conversation, $burst->last(), 'no agent file answers texts', 'No front desk agent is set up for texts');

            return;
        }

        try {
            $run = $this->runFrontDeskAgent->handle($agent, $conversation, $burst);
        } catch (FrontDeskUnavailable) {
            $this->reportFrontDeskFailure->handle($conversation, $burst->last(), 'every model failed', 'Front desk could not answer');

            return;
        }

        if ($run->reply->wantsPerson) {
            $this->handOffToOwner($conversation, $burst);

            return;
        }

        $this->recordIntakeAnswers->handle($conversation, $run->reply, $burst->last());
        $this->fillEmptyName($conversation, $run->reply->customerName, $burst->last());

        $words = $run->reply->reply;
        $composed = $this->composeAutomatedText->forReply($conversation, $words);
        $verdict = $this->decideSendOrDraft->decide($run, $this->prompt->promptText($burst), $composed);

        if ($verdict->lifeSafety) {
            $this->sendLifeSafetyLine->handle($burst->last(), 'general', 'classifier');
            $verdict = $verdict->outcome === GateOutcome::Send ? $verdict->heldFor('classifier:life_safety') : $verdict;
        } elseif ($verdict->asksForPerson) {
            $this->handOffToOwner($conversation, $burst);

            return;
        }

        $attributes = [
            'kind' => MessageKind::AgentReply,
            'meta' => ['in_reply_to' => $burst->pluck('id')->all()],
        ];

        if ($verdict->outcome === GateOutcome::Send) {
            try {
                $this->outboundSender->send(
                    $conversation,
                    $composed,
                    MessageAuthor::Agent,
                    SendOrigin::Automated,
                    [...$attributes, ...$verdict->toAttributes($run->reply->label)],
                );

                return;
            } catch (DomainException $exception) {
                if (str_starts_with($exception->getMessage(), OutboundSender::NOT_SENT)) {
                    $this->raiseOwnerAlert->handle($conversation, AlertReason::FrontDeskFailed, $burst->last(), 'The reply could not be sent');

                    return;
                }

                if ($exception->getPrevious() instanceof RecipientOptedOut) {
                    // The carrier knew the customer had opted out before we did; the contact is marked now.
                    $this->raiseOwnerAlert->handle($conversation, AlertReason::FrontDeskFailed, $burst->last(), 'The customer opted out of texts, so call them');

                    return;
                }

                $verdict = $verdict->heldFor('send_refused');
            }
        }

        $this->storeDraft($conversation, $words, $verdict, $run->reply->label, $attributes);
    }

    /**
     * The second chance for the safety line (D-17): any text in the burst whose words describe a danger and that no
     * safety line has answered yet gets one now, for the case where the immediate job never ran, such as a text the
     * sweep queued. A text already answered is skipped inside SendLifeSafetyLine.
     *
     * @param  Collection<int, Message>  $burst
     */
    private function answerDanger(Collection $burst): void
    {
        foreach ($burst as $message) {
            $hazard = $message->channel === MessageChannel::Sms ? LifeSafety::hazardIn($message->body) : null;

            if ($hazard !== null) {
                $this->sendLifeSafetyLine->handle($message, $hazard, 'keyword');
            }
        }
    }

    /**
     * Tell the owner a customer who opted out of texts has written again, so they can call (D-40). Nothing is sent
     * and the agent is not asked. A burst whose newest text already alerted the owner, such as a safety message that
     * could not be sent to a number that opted out, still gets this alert: it is a different reason, and it is the one
     * that tells the owner to call. Raising the same reason for the same text again is harmless.
     *
     * @param  Collection<int, Message>  $burst
     */
    private function alertAboutOptedOutBurst(Conversation $conversation, Collection $burst): void
    {
        $this->raiseOwnerAlert->handle($conversation, AlertReason::OptedOutMessage, $burst->last(), 'Opted out of texts, so call them');
    }

    /**
     * Tell the owner a customer wrote while the assistant is paused, once per burst (D-24). A burst whose newest text
     * already alerted the owner, such as a safety message that paused the conversation, is not alerted twice.
     *
     * @param  Collection<int, Message>  $burst
     */
    private function alertAboutPausedBurst(Conversation $conversation, Collection $burst): void
    {
        if (OwnerAlert::query()->where('message_id', $burst->last()->id)->exists()) {
            return;
        }

        $this->raiseOwnerAlert->handle($conversation, AlertReason::PausedMessage, $burst->last(), 'New message while you are handling it');
    }

    /**
     * Whether the assistant has already sent as many texts here as the reply cap allows, in the last hour or the last
     * 24 hours (D-52). Each text counts when it went out; one still queued counts at its creation, a second earlier.
     * Only sent or queued texts count, and the safety line never does, so it can neither use up the cap nor be stopped.
     */
    private function replyCapReached(Conversation $conversation): bool
    {
        $counted = fn () => $conversation->messages()
            ->where('direction', MessageDirection::Out)
            ->where('author', MessageAuthor::Agent)
            ->whereIn('status', [MessageStatus::Queued, MessageStatus::Sent, MessageStatus::Delivered])
            ->where(fn ($query) => $query->whereNull('kind')->orWhere('kind', '!=', MessageKind::LifeSafety));

        return $counted()->whereRaw('coalesce(sent_at, created_at) >= ?', [now()->subHour()])->count() >= config('gate.reply_cap.per_hour')
            || $counted()->whereRaw('coalesce(sent_at, created_at) >= ?', [now()->subDay()])->count() >= config('gate.reply_cap.per_day');
    }

    /**
     * Hand a customer who asked for a person to the owner: the owner is alerted right away, the customer is told the
     * owner knows and when he will reach out, and the assistant steps back (D-23). The text goes before the pause
     * because the outbound path refuses automated texts once the conversation is paused.
     *
     * @param  Collection<int, Message>  $burst
     */
    private function handOffToOwner(Conversation $conversation, Collection $burst): void
    {
        $this->raiseOwnerAlert->handle($conversation, AlertReason::AskedForPerson, $burst->last(), 'Asked for a person');
        $this->sendFixedReply->handoff($conversation);
        $this->pauseConversation->handle($conversation, PausedReason::AskedForPerson);
    }

    /**
     * Give a contact with no name the name the customer gave in their text, and leave a line on the timeline.
     *
     * The model's name is untrusted, so it is cut to one trimmed line of at most 120 characters, and the update only
     * matches a contact whose name is still empty: a stored name is never overwritten, even by a run that raced
     * another (D-43).
     */
    private function fillEmptyName(Conversation $conversation, ?string $given, Message $source): void
    {
        $name = trim(mb_substr(Str::squish((string) $given), 0, 120));

        if ($name === '') {
            return;
        }

        $contact = $conversation->contact;

        $filled = DB::transaction(function () use ($contact, $name, $source): bool {
            $updated = Contact::query()
                ->whereKey($contact->id)
                ->where(fn ($query) => $query->whereNull('name')->orWhere('name', ''))
                ->update(['name' => $name]);

            if ($updated === 0) {
                return false;
            }

            $contact->activities()->create([
                'type' => ActivityType::Note,
                'summary' => "Name from the customer's text: {$name}",
                'subject_type' => $source->getMorphClass(),
                'subject_id' => $source->id,
                'occurred_at' => now(),
            ]);

            return true;
        });

        if ($filled) {
            $contact->refresh();
            CustomerRecordChanged::dispatch($contact->id);
        }
    }

    /**
     * Keep the agent's words for the owner, with the verdict that held them, and tell the owner.
     *
     * A conversation has one live draft (D-28). If an older draft is still waiting, it is withdrawn as replaced and
     * kept as history in the same transaction that writes the new one, so the owner only ever sees the newest. The
     * draft and its timeline entry are written together, so a held reply never exists without a record of why.
     * The alert goes out only after that commit: a text to the owner's phone cannot be taken back, and it must not
     * point at a draft that was rolled away (D-07, D-20). Last, the customer gets one holding reply saying the owner
     * has their question and when he will answer, unless one is already waiting on this same question (D-12, D-14).
     * A reply held for a danger never gets the holding reply, because "the owner will answer" is the wrong thing to
     * tell someone describing a fire or a gas leak.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function storeDraft(Conversation $conversation, string $words, Verdict $verdict, ReplyLabel $label, array $attributes): void
    {
        $plainReason = $verdict->plainReason ?? Verdict::plainReasonFor((string) $verdict->reasonCode);
        $alertReason = $this->alertReasonFor($verdict);

        [$draft, $replacedChain] = DB::transaction(function () use ($conversation, $words, $verdict, $label, $attributes, $plainReason): array {
            $replaced = $conversation->messages()
                ->where('status', MessageStatus::Draft)
                ->lockForUpdate()
                ->first();

            $chain = [];

            if ($replaced !== null) {
                $chain = [...($replaced->meta['replaces'] ?? []), $replaced->id];

                $replaced->update([
                    'status' => MessageStatus::Withdrawn,
                    'resolution' => DraftResolution::Replaced,
                    'resolved_at' => now(),
                ]);

                $attributes['meta'] = [...($attributes['meta'] ?? []), 'replaces' => $chain];
            }

            $draft = $conversation->messages()->create([
                ...$attributes,
                ...$verdict->toAttributes($label),
                'channel' => MessageChannel::Sms,
                'direction' => MessageDirection::Out,
                'author' => MessageAuthor::Agent,
                'body' => $words,
                'status' => MessageStatus::Draft,
            ]);

            $conversation->contact->activities()->create([
                'type' => ActivityType::DraftHeld,
                'summary' => $plainReason,
                'subject_type' => $draft->getMorphClass(),
                'subject_id' => $draft->id,
                'occurred_at' => now(),
            ]);

            return [$draft, $chain];
        });

        CustomerRecordChanged::dispatch($conversation->contact->id);

        if ($this->ownerNeedsAlertFor($alertReason, $replacedChain)) {
            $this->raiseOwnerAlert->handle($conversation, $alertReason, $draft, $plainReason);
        }

        if (! $verdict->lifeSafety && ! $conversation->refresh()->isPaused()) {
            $this->sendFixedReply->holding($conversation);
        }
    }

    /**
     * Whether a replacement draft earns a new alert (D-22). A first draft always does. A replacement does only when
     * urgency has risen, so a customer who texts three times in a row costs the owner one alert, not three.
     *
     * Urgency counts as risen when the reason is urgent and no alert that actually reached the owner for this draft
     * chain was. An alert that never reached the owner, because it is still waiting for opening time or failed to
     * send, does not count as telling them: the older draft is withdrawn, so its waiting alert would be dropped as
     * handled, and the owner would never hear about the question.
     *
     * @param  list<int>  $replacedChain  The ids of the drafts this one replaces, oldest first; empty for a first draft.
     */
    private function ownerNeedsAlertFor(AlertReason $reason, array $replacedChain): bool
    {
        if ($replacedChain === []) {
            return true;
        }

        $told = OwnerAlert::query()
            ->whereIn('message_id', $replacedChain)
            ->whereNotNull('sent_at')
            ->get();

        if ($told->isEmpty()) {
            return true;
        }

        return $this->raiseOwnerAlert->isUrgent($reason) && ! $told->contains('urgent', true);
    }

    /**
     * A held complaint alerts the owner as a complaint, which the folder usually lists under right_away; every other
     * held reply is simply a draft waiting. A complaint is recognised by the reason code the send rule gave, whether
     * the label, the classifier or its intent found it.
     */
    private function alertReasonFor(Verdict $verdict): AlertReason
    {
        $isComplaint = in_array($verdict->reasonCode, ['label:complaint', 'classifier:complaint', 'classifier_intent:complaint'], true);

        return $isComplaint ? AlertReason::Complaint : AlertReason::DraftWaiting;
    }
}
