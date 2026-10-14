<?php

namespace App\Actions;

use App\Ai\AgentFile;
use App\Ai\AgentRun;
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
use App\Models\IntakeAnswer;
use App\Models\Message;
use App\Models\OwnerAlert;
use App\SendRule\AsksForPerson;
use App\SendRule\DecideSendOrDraft;
use App\SendRule\FixedReplies;
use App\SendRule\HeldReasons;
use App\SendRule\LifeSafety;
use App\SendRule\OfferSentences;
use App\SendRule\PricePick;
use App\SendRule\PriceSentencePicker;
use App\SendRule\ReplyParts;
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
 * A customer who asks about a discount gets the sentence of every offer live today, written by the owner and sent by
 * code with the price sentences (D-13). With no offer live the question is held as discount:no_live_offer, handled like
 * a price with no sentence on file, so the owner hears about it (D-14).
 *
 * A customer who writes while a draft waits is handled by answerWhileDraftWaits(). Two things can finish work there
 * without the owner: the owner's price sentence for the service a price-only draft waits on goes out and closes that
 * draft as answered (D-21), and an easy question asked in the same text as one that touches the waiting question is
 * answered now, with the partial holding line, while the rest becomes the fresh draft (D-19, D-20).
 *
 * A front desk that cannot answer (every model failed, no agent for texts) or cannot send alerts the owner and
 * returns, because the customer's burst is already claimed (AGNT-04). Anything else that goes wrong, such as a
 * database error, does throw, and ProcessInbound turns it into a note and an owner alert so a claimed text is never
 * left silent. A send the channel refused stays on the record as failed. A send that was only refused, such as while
 * the owner is sending to the same customer, becomes a draft.
 */
class RunFrontDesk
{
    /** The waiting_note value for a customer asking whether anyone has seen their question. */
    private const string CHECK_IN = 'check_in';

    /**
     * The only labels a split-off easy part may carry (D-20): it never commits to a day or time while a question is with
     * the owner.
     *
     * @var list<ReplyLabel>
     */
    private const array SPLIT_LABELS = [ReplyLabel::FaqAnswer, ReplyLabel::IntakeQuestion];

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
        private readonly PriceSentencePicker $priceSentencePicker,
        private readonly SendCombinedReply $sendCombinedReply,
        private readonly FixedReplies $fixedReplies,
        private readonly OfferSentences $offerSentences,
        private readonly ResolveDraft $resolveDraft,
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

        $waiting = $conversation->messages()->where('status', MessageStatus::Draft)->first();

        try {
            $run = $this->runFrontDeskAgent->handle($agent, $conversation, $burst, waitingDraft: $waiting);
        } catch (FrontDeskUnavailable) {
            $this->reportFrontDeskFailure->handle($conversation, $burst->last(), 'every model failed', 'Front desk could not answer');

            return;
        }

        if ($run->reply->wantsPerson) {
            $this->handOffToOwner($conversation, $burst);

            return;
        }

        $newAnswers = $this->recordIntakeAnswers->handle($conversation, $run->reply, $burst->last());
        $this->fillEmptyName($conversation, $run->reply->customerName, $burst->last());

        $askedSlugs = array_values(array_unique([...$run->reply->priceServices, ...$this->pendingPriceSlugs($conversation)]));
        $picks = $this->pickPrices($conversation, $askedSlugs, $newAnswers);
        $lookups = $this->priceLookups($askedSlugs, $picks);
        $priceSentences = array_map(fn (PricePick $pick): string => $this->sentenceText($pick), array_values(array_filter($picks, fn (PricePick $pick): bool => $pick->isSend())));
        $offers = $run->reply->asksDiscount ? $this->offerSentences->liveToday() : [];
        $sentences = [...$priceSentences, ...array_column($offers, 'sentence')];
        $unanswered = $this->slugsWith($picks, PricePick::HOLD);

        $words = $run->reply->reply;
        $customerText = $this->prompt->promptText($burst);
        $priceHeld = count($priceSentences) !== count($picks);
        $noLiveOffer = $run->reply->asksDiscount && $offers === [];

        $answersWaiting = $waiting !== null && HeldReasons::answeredBy($waiting, $this->slugsWith($picks, PricePick::SEND), $run->reply->touchesWaiting);
        $stillWaiting = $answersWaiting ? null : $waiting;

        $checkIn = $stillWaiting !== null && $run->reply->waitingNote === self::CHECK_IN;
        $codeOnly = $words === '' && ($sentences !== [] || $checkIn) && ! $priceHeld && ! $noLiveOffer;

        if ($codeOnly && $stillWaiting === null) {
            $this->closeAsAnswered($answersWaiting ? $waiting : null, $this->sendCodeAnswer($run, $conversation, $burst, $picks, $sentences, array_column($offers, 'slug'), $lookups));

            return;
        }

        if ($codeOnly) {
            $composed = '';
            $verdict = null;
        } elseif ($words === '' && $noLiveOffer && ! $priceHeld) {
            $composed = '';
            $verdict = $this->decideSendOrDraft->holdWithoutWords($run, $customerText, 'discount:no_live_offer');
        } elseif ($words === '') {
            $composed = '';
            $verdict = $this->decideSendOrDraft->holdWithoutWords($run, $customerText, 'price:no_sentence', $this->noPriceReason([...$unanswered, ...$this->slugsWith($picks, PricePick::NEEDS_ANSWERS)]));
        } else {
            $composed = $this->composeAutomatedText->forReply($conversation, $words);
            $verdict = $this->decideSendOrDraft->decide($run, $customerText, $composed, $this->labelGuardFor($stillWaiting));
        }

        if ($verdict?->lifeSafety) {
            $this->sendLifeSafetyLine->handle($burst->last(), 'general', 'classifier');
            $verdict = $verdict->outcome === GateOutcome::Send ? $verdict->heldFor('classifier:life_safety') : $verdict;
            $picks = [];
            $offers = [];
            $sentences = [];
        } elseif ($verdict?->asksForPerson) {
            $this->handOffToOwner($conversation, $burst);

            return;
        }

        if ($unanswered !== [] && $verdict?->outcome === GateOutcome::Send) {
            $verdict = $verdict->heldFor('price:no_sentence', $this->noPriceReason($unanswered));
        } elseif ($noLiveOffer && $verdict?->outcome === GateOutcome::Send) {
            $verdict = $verdict->heldFor('discount:no_live_offer');
        }

        $easyAnswer = null;

        if ($stillWaiting !== null && $words !== '' && $verdict !== null && ! $verdict->lifeSafety && ($easyPart = $run->reply->easyPart()) !== null) {
            $easyVerdict = $this->decideSendOrDraft->decide(
                new AgentRun($easyPart, $run->provider, $run->model, $run->failedAttempts),
                $customerText,
                $this->composeAutomatedText->forReply($conversation, $easyPart->reply),
                self::SPLIT_LABELS,
            );

            if ($easyVerdict->lifeSafety) {
                $this->sendLifeSafetyLine->handle($burst->last(), 'general', 'classifier');
                $verdict = $verdict->flaggedLifeSafety();
                $verdict = $verdict->outcome === GateOutcome::Send ? $verdict->heldFor('classifier:life_safety') : $verdict;
                $picks = [];
                $offers = [];
                $sentences = [];
            } elseif ($easyVerdict->outcome === GateOutcome::Send) {
                $easyAnswer = ['words' => $easyPart->reply, 'attributes' => $easyVerdict->toAttributes($easyPart->label)];
            }
        }

        $attributes = [
            'kind' => MessageKind::AgentReply,
            'meta' => ['in_reply_to' => $burst->pluck('id')->all(), 'price_pending' => $this->slugsWith($picks, PricePick::NEEDS_ANSWERS), ...$lookups],
        ];

        $parts = new ReplyParts(codeSentences: $sentences, modelWords: $words);
        $sentMeta = ['meta' => [...$attributes['meta'], ...$this->codeSentencesMeta($picks, array_column($offers, 'slug'))]];

        if ($stillWaiting !== null) {
            $this->answerWhileDraftWaits($run, $conversation, $burst, $stillWaiting, $verdict, $composed, $parts, $attributes, $sentMeta, $picks, array_column($offers, 'slug'), $checkIn, $easyAnswer);

            return;
        }

        if ($verdict->outcome === GateOutcome::Send && $sentences !== [] && $this->sendCombinedReply->composedLength($conversation, $parts) > SendOwnerReply::MAX_LENGTH) {
            $verdict = $verdict->heldFor('too_long');
        }

        if ($verdict->outcome === GateOutcome::Send) {
            $sent = $this->sendPassingReply($run, $conversation, $burst, $verdict, $composed, $parts, $attributes, $sentMeta);

            if (! $sent instanceof Verdict) {
                $this->closeAsAnswered($answersWaiting && $sentences !== [] ? $waiting : null, $sent);

                return;
            }

            $verdict = $sent;
        }

        if ($answersWaiting && $sentences !== []) {
            $this->closeAsAnswered($waiting, $this->sendPartialHolding($conversation, $parts, $sentMeta, $verdict->reasonCode));
            $this->storeDraft($conversation, $words, $verdict, $run->reply->label, $attributes, sendHolding: false);

            return;
        }

        $this->storeDraft($conversation, $words, $verdict, $run->reply->label, $attributes, sendHolding: $sentences === []);

        if ($sentences !== []) {
            $this->sendPartialHolding($conversation, $parts, $sentMeta, $verdict->reasonCode);
        }
    }

    /**
     * Close the waiting draft as answered because the text with the price sentence for it went out (D-21). The text must
     * have been sent: a draft is never closed on a send that failed, so it stays with the owner. A draft the owner or a
     * newer text already dealt with is left alone, and nothing more is done about it. No alert follows a close.
     */
    private function closeAsAnswered(?Message $waiting, ?Message $answer): void
    {
        if ($waiting === null || $answer === null) {
            return;
        }

        $this->resolveDraft->closeAsAnswered($waiting, $answer);
    }

    /**
     * What a draft stored by this run still waits on, written by code from its own price picks (D-21). `price_slugs`
     * are the services the run looked a price sentence up for, the customer's asks then the ones still pending, each
     * once; `price_open` are those that got no sentence in this run because the customer still owes an answer or the
     * owner has to quote it. Read later, only on a draft, to tell which sentence would answer it.
     *
     * @param  list<string>  $askedSlugs
     * @param  list<PricePick>  $picks
     * @return array{price_slugs: list<string>, price_open: list<string>}
     */
    private function priceLookups(array $askedSlugs, array $picks): array
    {
        $unsent = [...$this->slugsWith($picks, PricePick::NEEDS_ANSWERS), ...$this->slugsWith($picks, PricePick::HOLD)];

        return [
            'price_slugs' => $askedSlugs,
            'price_open' => array_values(array_filter($askedSlugs, fn (string $slug): bool => in_array($slug, $unsent, true))),
        ];
    }

    /**
     * The labels that may still send while a draft waits (D-20). A booking answer must not go out while a booking
     * question is with the owner, because the model could commit to a day the owner is about to settle, so a waiting
     * scheduling draft limits the reply to a faq answer or an intake question. Any other waiting draft, and no waiting
     * draft, limits nothing. The label is read from the stored draft.
     *
     * @return ?list<ReplyLabel>
     */
    private function labelGuardFor(?Message $waiting): ?array
    {
        return $waiting?->label === ReplyLabel::Scheduling ? [ReplyLabel::FaqAnswer, ReplyLabel::IntakeQuestion] : null;
    }

    /**
     * Send the model's words that passed the send rule, with any owner sentences and fixed line that ride with them, as
     * one text. Returns the message that went out, or null when its failure was raised to the owner. Returns the verdict
     * held for `send_refused` when the sender only refused it, so the caller keeps the words as a draft.
     *
     * @param  Collection<int, Message>  $burst
     * @param  array<string, mixed>  $attributes
     * @param  array{meta: array<string, mixed>}  $sentMeta
     */
    private function sendPassingReply(AgentRun $run, Conversation $conversation, Collection $burst, Verdict $verdict, string $composed, ReplyParts $parts, array $attributes, array $sentMeta): Message|Verdict|null
    {
        try {
            if ($parts->codeSentences === [] && $parts->line === null) {
                return $this->outboundSender->send(
                    $conversation,
                    $composed,
                    MessageAuthor::Agent,
                    SendOrigin::Automated,
                    [...$attributes, ...$verdict->toAttributes($run->reply->label)],
                );
            }

            return $this->sendCombinedReply->handle($conversation, $parts, [...$sentMeta, ...$verdict->toAttributes($run->reply->label)]);
        } catch (DomainException $exception) {
            if ($this->alertAboutFailedSend($exception, $conversation, $burst)) {
                return null;
            }

            return $verdict->heldFor('send_refused');
        }
    }

    /**
     * The run for a customer who writes while a draft waits for the owner (D-18, D-22, D-28, D-29). The waiting draft W
     * is the live draft when the run started; the model's words are R and the owner's price and offer sentences are S.
     *
     * Only a reply the model marks as not touching W (`touches_waiting` exactly false) may send while W stays live: it
     * goes out beside W with no new alert. A reply that touches W, or says nothing about it, never sends, because a
     * stale "Thursday" draft beside a "Friday" answer would send the wrong day; it becomes the one fresh draft that
     * replaces W.
     *
     * A check-in adds nothing for the owner to answer, so its words never replace W and never send beside it: when they
     * would send but touch W, are held only by the label guard, or are blank, W stays as it is, the words are dropped,
     * and the owner-written check-in reply goes with any sentences. A check-in whose words are held for a reason of their
     * own still replaces W, but the fresh draft keeps W's body, so the owner can never send a reassurance as the answer.
     *
     * A customer is never left with no reply: a run sends one owner-written line, chosen in order: the complaint reply,
     * the check-in reply, the partial holding line when an answer rides, the added-info reply, or, when the owner's own
     * text already ended the wait, a new holding reply (D-34, D-36). Only a check-in alerts the owner (D-29).
     *
     * When the text touches W and also asks something easy (D-19), the easy part has already been checked on its own
     * with the label guard. If it passed, its words ride in the same text as the owner's sentences, in front of whichever
     * line is chosen; the touching reply is stored as the fresh draft as usual. If it was held, nothing of it is sent.
     *
     * @param  Collection<int, Message>  $burst
     * @param  array<string, mixed>  $attributes
     * @param  array{meta: array<string, mixed>}  $sentMeta
     * @param  list<PricePick>  $picks
     * @param  list<string>  $offerSlugs
     * @param  ?array{words: string, attributes: array<string, mixed>}  $easy  The split-off easy answer that passed every check, with the verdict it is stored under.
     */
    private function answerWhileDraftWaits(AgentRun $run, Conversation $conversation, Collection $burst, Message $waiting, ?Verdict $verdict, string $composed, ReplyParts $parts, array $attributes, array $sentMeta, array $picks, array $offerSlugs, bool $checkIn, ?array $easy = null): void
    {
        if ($verdict?->outcome === GateOutcome::Send && $run->reply->touchesWaiting !== false) {
            $verdict = $checkIn ? null : $verdict->heldFor('touches_waiting');
        } elseif ($checkIn && $this->holdsOnlyBecauseWordsAreNotAnAnswer($verdict)) {
            $verdict = null;
        }

        if ($verdict === null) {
            if ($checkIn) {
                $this->sendCheckInReply($conversation, $burst, $parts, $sentMeta, $easy);
            } else {
                $this->sendCodeAnswer($run, $conversation, $burst, $picks, $parts->codeSentences, $offerSlugs, array_intersect_key($attributes['meta'] ?? [], ['price_slugs' => true, 'price_open' => true]));
            }

            return;
        }

        $withLine = $checkIn ? new ReplyParts(codeSentences: $parts->codeSentences, modelWords: $parts->modelWords, lineKind: MessageKind::CheckInReply, line: $this->fixedReplies->checkInReply(now())) : $parts;

        if ($verdict->outcome === GateOutcome::Send && ($withLine->codeSentences !== [] || $withLine->line !== null) && $this->sendCombinedReply->composedLength($conversation, $withLine) > SendOwnerReply::MAX_LENGTH) {
            $verdict = $verdict->heldFor('too_long');
        }

        if ($verdict->outcome === GateOutcome::Send) {
            $sent = $this->sendPassingReply($run, $conversation, $burst, $verdict, $composed, $withLine, $attributes, $sentMeta);

            if (! $sent instanceof Verdict) {
                if ($sent !== null && $checkIn) {
                    $this->raiseOwnerAlert->handle($conversation, AlertReason::CheckIn, $burst->last(), 'Customer checked in');
                }

                return;
            }

            $verdict = $sent;
        }

        $keepsWaitingBody = $parts->modelWords === '' || $checkIn;

        $this->storeDraft(
            $conversation,
            $keepsWaitingBody ? $waiting->body : (string) $parts->modelWords,
            $verdict,
            $keepsWaitingBody ? $waiting->label ?? $run->reply->label : $run->reply->label,
            $attributes,
            sendHolding: false,
        );

        if ($verdict->lifeSafety || $conversation->refresh()->isPaused()) {
            return;
        }

        if (in_array($verdict->reasonCode, SendFixedReply::COMPLAINT_CODES, true) || (($parts->codeSentences !== [] || $easy !== null) && ! $checkIn)) {
            $this->sendPartialHolding($conversation, $parts, $sentMeta, $verdict->reasonCode, $easy);
        } elseif ($checkIn) {
            $this->sendCheckInReply($conversation, $burst, $parts, $sentMeta, $easy);
        } elseif ($this->sendFixedReply->holdingAlreadySent($conversation)) {
            $this->sendAddedInfoReply($conversation, $sentMeta);
        } else {
            $this->sendFixedReply->holding($conversation, $verdict->reasonCode);
        }
    }

    /**
     * Whether a check-in's words were held only because they are not an answer to send: the label guard of a waiting
     * scheduling draft, or a label such as other that the owner did not put on the send_on_own list. A hold for a reason
     * of the words' own, such as a price, a promise, a complaint or a code check, is not this.
     */
    private function holdsOnlyBecauseWordsAreNotAnAnswer(?Verdict $verdict): bool
    {
        $reason = (string) $verdict?->reasonCode;

        if (str_starts_with($reason, 'waiting_guard:')) {
            return true;
        }

        $label = str_starts_with($reason, 'label:') ? ReplyLabel::tryFrom(substr($reason, strlen('label:'))) : null;

        return $label !== null && ! in_array($label, ReplyLabel::neverOnItsOwn(), true);
    }

    /**
     * Tell a customer who checked in on a waiting question that the owner still has it, with any owner sentences in front
     * of the line, and alert the owner that the customer checked in (D-28). The alert follows only a text that went out,
     * and its timing is the owner's: RaiseOwnerAlert follows the right_away list (D-38). A text that cannot go out is
     * reported to the log; the draft and its own alert already exist.
     *
     * @param  Collection<int, Message>  $burst
     * @param  array{meta: array<string, mixed>}  $attributes
     * @param  ?array{words: string, attributes: array<string, mixed>}  $easy  Split-off easy words that passed every check; they follow the sentences.
     */
    private function sendCheckInReply(Conversation $conversation, Collection $burst, ReplyParts $parts, array $attributes, ?array $easy = null): void
    {
        [$text, $attributes] = $this->withEasyWords($conversation, new ReplyParts(
            codeSentences: $parts->codeSentences,
            lineKind: MessageKind::CheckInReply,
            line: $this->fixedReplies->checkInReply(now()),
        ), $easy, $attributes);

        try {
            $this->sendCombinedReply->handle($conversation, $text, $attributes);
        } catch (DomainException $exception) {
            if (! $this->alertAboutFailedSend($exception, $conversation, $burst)) {
                report($exception);
            }

            return;
        }

        $this->raiseOwnerAlert->handle($conversation, AlertReason::CheckIn, $burst->last(), 'Customer checked in');
    }

    /**
     * Put split-off easy words into a text that is about to go out, behind its sentences and in front of its fixed line,
     * with the verdict they passed under (D-19). Words that would push the whole text past one message are left out, so
     * the customer still gets the sentences and the line.
     *
     * @param  array<string, mixed>  $attributes
     * @param  ?array{words: string, attributes: array<string, mixed>}  $easy
     * @return array{0: ReplyParts, 1: array<string, mixed>}
     */
    private function withEasyWords(Conversation $conversation, ReplyParts $parts, ?array $easy, array $attributes): array
    {
        if ($easy === null) {
            return [$parts, $attributes];
        }

        $withEasy = new ReplyParts(
            complaintLine: $parts->complaintLine,
            codeSentences: $parts->codeSentences,
            modelWords: $easy['words'],
            lineKind: $parts->lineKind,
            line: $parts->line,
        );

        if ($this->sendCombinedReply->composedLength($conversation, $withEasy) > SendOwnerReply::MAX_LENGTH) {
            return [$parts, $attributes];
        }

        return [$withEasy, [...$attributes, ...$easy['attributes']]];
    }

    /**
     * Tell a customer who added to a question the owner already holds that it was passed along. A text that cannot go
     * out is reported to the log and never raised: the draft and its alert already exist.
     *
     * @param  array{meta: array<string, mixed>}  $attributes
     */
    private function sendAddedInfoReply(Conversation $conversation, array $attributes): void
    {
        try {
            $this->sendCombinedReply->handle($conversation, new ReplyParts(
                lineKind: MessageKind::AddedInfoReply,
                line: $this->fixedReplies->addedInfoReply(now()),
            ), $attributes);
        } catch (DomainException $exception) {
            report($exception);
        }
    }

    /**
     * Tell the customer the owner's sentence and that the rest of their question is with the owner, in one text. The
     * draft and the owner's alert already exist, so a text that cannot go out falls back to the full holding reply and
     * is otherwise reported to the log, never raised (D-30). When the words were held as a complaint, the owner's
     * complaint reply leads the text instead of the partial line, so an upset customer is acknowledged before anything
     * else (D-36).
     *
     * @param  array{meta: array<string, mixed>}  $attributes
     * @param  ?string  $reasonCode  Why the words were held.
     * @param  ?array{words: string, attributes: array<string, mixed>}  $easy  Split-off easy words that passed every check; they follow the sentences and the verdict they passed under is stored on the text.
     * @return ?Message The text that went out with the sentences, or null when it could not be sent or the assistant is paused.
     */
    private function sendPartialHolding(Conversation $conversation, ReplyParts $parts, array $attributes, ?string $reasonCode, ?array $easy = null): ?Message
    {
        if ($conversation->refresh()->isPaused()) {
            return null;
        }

        $partial = in_array($reasonCode, SendFixedReply::COMPLAINT_CODES, true)
            ? new ReplyParts(complaintLine: $this->fixedReplies->complaintReply(now()), codeSentences: $parts->codeSentences)
            : new ReplyParts(
                codeSentences: $parts->codeSentences,
                lineKind: MessageKind::PartialHolding,
                line: $this->fixedReplies->partialHoldingReply(now()),
            );

        [$partial, $attributes] = $this->withEasyWords($conversation, $partial, $easy, $attributes);

        try {
            return $this->sendCombinedReply->handle($conversation, $partial, $attributes);
        } catch (DomainException $exception) {
            report($exception);

            $this->sendFixedReply->holding($conversation, $reasonCode);

            return null;
        }
    }

    /**
     * The price outcome of each service the customer asked about, in the order asked, each service once, then each service
     * whose price their new intake answers may have changed. The model only names the service; the picker reads the
     * owner's sentence from the live version (D-04). A service with nothing to send and nothing to hold is left out.
     *
     * @param  list<string>  $askedSlugs  Services asked about in this run or still pending from an earlier one.
     * @param  Collection<int, IntakeAnswer>  $newAnswers  The intake answers stored in this run.
     * @return list<PricePick>
     */
    private function pickPrices(Conversation $conversation, array $askedSlugs, Collection $newAnswers): array
    {
        $correctedSlugs = array_values(array_diff($newAnswers->pluck('service_slug')->unique()->all(), $askedSlugs));

        $picks = [
            ...array_map(fn (string $slug): PricePick => $this->priceSentencePicker->pick($conversation, $slug, true, $newAnswers), $askedSlugs),
            ...array_map(fn (string $slug): PricePick => $this->priceSentencePicker->pick($conversation, $slug, false, $newAnswers), $correctedSlugs),
        ];

        return array_values(array_filter($picks, fn (PricePick $pick): bool => $pick->outcome !== PricePick::NOTHING));
    }

    /**
     * The text a sent pick puts in the message: the owner's sentence, behind the owner's lead when it is a second
     * telling. A folder with no such lead sends the sentence alone.
     */
    private function sentenceText(PricePick $pick): string
    {
        $lead = $pick->lead === null ? null : $this->fixedReplies->priceLead($pick->lead, now());

        return $lead === null || trim($lead) === '' ? (string) $pick->sentence : trim($lead).' '.$pick->sentence;
    }

    /**
     * The services whose price is still waiting on the customer's answers, as the previous run left them in the meta
     * of the message it wrote. Holding and handoff replies carry no `in_reply_to`, so they are skipped.
     *
     * @return list<string>
     */
    private function pendingPriceSlugs(Conversation $conversation): array
    {
        $previous = $conversation->messages()
            ->where('direction', MessageDirection::Out)
            ->reorder()
            ->orderByRaw('coalesce(sent_at, created_at) desc')
            ->orderByDesc('id')
            ->lazy()
            ->first(fn (Message $message): bool => isset($message->meta['in_reply_to']));

        $pending = $previous?->meta['price_pending'] ?? [];

        return array_values(array_filter(is_array($pending) ? $pending : [], is_string(...)));
    }

    /**
     * The services whose pick has the given outcome, in the order picked.
     *
     * @param  list<PricePick>  $picks
     * @return list<string>
     */
    private function slugsWith(array $picks, string $outcome): array
    {
        return array_values(array_map(
            fn (PricePick $pick): string => $pick->slug,
            array_filter($picks, fn (PricePick $pick): bool => $pick->outcome === $outcome),
        ));
    }

    /**
     * The `price_sentences` meta of a text: which sentence of which service went out, and the amounts it named.
     *
     * @param  list<PricePick>  $picks
     * @return list<array{slug: string, key: ?string, amounts_cents: list<int>}>
     */
    private function priceSentencesMeta(array $picks): array
    {
        return array_map(
            fn (PricePick $pick): array => ['slug' => $pick->slug, 'key' => $pick->sentenceKey, 'amounts_cents' => $pick->amountsCents],
            array_values(array_filter($picks, fn (PricePick $pick): bool => $pick->isSend())),
        );
    }

    /**
     * The meta of a text that carries owner sentences: the price sentences it carried, and, when it carried offers, which
     * ones (D-12, D-13).
     *
     * @param  list<PricePick>  $picks
     * @param  list<string>  $offerSlugs
     * @return array<string, mixed>
     */
    private function codeSentencesMeta(array $picks, array $offerSlugs): array
    {
        return [
            'price_sentences' => $this->priceSentencesMeta($picks),
            ...($offerSlugs === [] ? [] : ['offers' => $offerSlugs]),
        ];
    }

    /**
     * Send the owner's sentences alone, the customer's whole answer to a price or discount question. Code wrote them, so Jev is not
     * asked (D-04). A send that failed alerts the owner exactly as a reply that failed does; any other refusal becomes
     * an empty price draft, so the question still reaches the owner, as a refused reply does.
     *
     * @param  Collection<int, Message>  $burst
     * @param  list<PricePick>  $picks
     * @param  list<string>  $sentences
     * @param  list<string>  $offerSlugs  The offers whose sentences are among the sentences.
     * @param  array<string, list<string>>  $lookups  The price_slugs and price_open of the draft stored when the send is refused.
     * @return ?Message The text that went out, or null when it did not.
     */
    private function sendCodeAnswer(AgentRun $run, Conversation $conversation, Collection $burst, array $picks, array $sentences, array $offerSlugs, array $lookups = []): ?Message
    {
        $inReplyTo = $burst->pluck('id')->all();

        try {
            return $this->sendCombinedReply->handle($conversation, new ReplyParts(codeSentences: $sentences), [
                'meta' => ['in_reply_to' => $inReplyTo, 'price_pending' => [], ...$this->codeSentencesMeta($picks, $offerSlugs)],
            ]);
        } catch (DomainException $exception) {
            if ($this->alertAboutFailedSend($exception, $conversation, $burst)) {
                return null;
            }

            report($exception);

            $verdict = $this->decideSendOrDraft->holdWithoutWords($run, $this->prompt->promptText($burst), 'send_refused');

            $this->storeDraft($conversation, '', $verdict, $run->reply->label, [
                'kind' => MessageKind::AgentReply,
                'meta' => ['in_reply_to' => $inReplyTo, 'price_pending' => [], ...$lookups],
            ]);

            return null;
        }
    }

    /**
     * The sentence for the owner when a price question could not be answered by code: which services have no price on
     * file, by the name in the service file. A slug the live folder does not have is never shown, because the model
     * wrote it (D-40).
     *
     * @param  list<string>  $slugs
     */
    private function noPriceReason(array $slugs): string
    {
        $names = array_map(function (string $slug): string {
            $name = $this->activeConfig->service($slug)['name'] ?? null;

            return is_string($name) && trim($name) !== '' ? trim($name) : 'a service not in your folder';
        }, array_values(array_unique($slugs)));

        if ($names === []) {
            return Verdict::plainReasonFor('price:no_sentence');
        }

        $last = array_pop($names);
        $listed = $names === [] ? $last : implode(', ', $names).' and '.$last;

        return "Held: no price on file for {$listed}";
    }

    /**
     * Alert the owner when a send failed in the channel or the carrier knew the customer had opted out. Returns false
     * for any other refusal, which the caller handles.
     *
     * @param  Collection<int, Message>  $burst
     */
    private function alertAboutFailedSend(DomainException $exception, Conversation $conversation, Collection $burst): bool
    {
        if (str_starts_with($exception->getMessage(), OutboundSender::NOT_SENT)) {
            $this->raiseOwnerAlert->handle($conversation, AlertReason::FrontDeskFailed, $burst->last(), 'The reply could not be sent');

            return true;
        }

        if ($exception->getPrevious() instanceof RecipientOptedOut) {
            // The carrier knew the customer had opted out before we did; the contact is marked now.
            $this->raiseOwnerAlert->handle($conversation, AlertReason::FrontDeskFailed, $burst->last(), 'The customer opted out of texts, so call them');

            return true;
        }

        return false;
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
     * A caller that sends its own text beside the draft, such as the partial holding line, passes `$sendHolding` false.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function storeDraft(Conversation $conversation, string $words, Verdict $verdict, ReplyLabel $label, array $attributes, bool $sendHolding = true): void
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

        if ($sendHolding && ! $verdict->lifeSafety && ! $conversation->refresh()->isPaused()) {
            $this->sendFixedReply->holding($conversation, $verdict->reasonCode);
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
        $isComplaint = in_array($verdict->reasonCode, SendFixedReply::COMPLAINT_CODES, true);

        return $isComplaint ? AlertReason::Complaint : AlertReason::DraftWaiting;
    }
}
