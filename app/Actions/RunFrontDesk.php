<?php

namespace App\Actions;

use App\Ai\AgentFile;
use App\Ai\FrontDeskPrompt;
use App\Ai\FrontDeskUnavailable;
use App\Ai\RunFrontDeskAgent;
use App\Enums\ActivityType;
use App\Enums\AlertReason;
use App\Enums\GateOutcome;
use App\Enums\MessageAuthor;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\MessageStatus;
use App\Enums\ReplyLabel;
use App\Enums\SendOrigin;
use App\Events\CustomerRecordChanged;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\SendRule\DecideSendOrDraft;
use App\SendRule\Verdict;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Answers one claimed burst of customer texts: the agent writes a reply, the send rule decides, and the reply is
 * either sent through the one outbound path or kept as a draft with its verdict.
 *
 * A draft holds only the agent's own words. The disclosure and the sign-off are added when a text actually goes
 * out, so they land on whichever automated text reaches the customer first (D-50).
 *
 * This never throws for a front desk that cannot answer or cannot send: the customer's burst is already claimed,
 * so the owner is alerted instead (AGNT-04). A send the channel refused stays on the record as failed. A send
 * that was only refused, such as while the owner is sending to the same customer, becomes a draft.
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
    ) {}

    /**
     * @param  Collection<int, Message>  $burst  The customer's texts this run claimed, oldest first. Must not be empty.
     */
    public function handle(Conversation $conversation, Collection $burst): void
    {
        $agent = AgentFile::forChannel($this->activeConfig, 'sms');

        if ($agent === null) {
            return;
        }

        try {
            $run = $this->runFrontDeskAgent->handle($agent, $conversation, $burst);
        } catch (FrontDeskUnavailable) {
            $this->noteThatNoModelAnswered($conversation, $burst);
            $this->raiseOwnerAlert->handle($conversation, AlertReason::FrontDeskFailed, $burst->last(), 'Front desk could not answer');

            return;
        }

        $words = $run->reply->reply;
        $composed = $this->composeAutomatedText->forReply($conversation, $words);
        $verdict = $this->decideSendOrDraft->decide($run, $this->prompt->promptText($burst), $composed);

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

                $verdict = $verdict->heldFor('send_refused');
            }
        }

        $this->storeDraft($conversation, $words, $verdict, $run->reply->label, $attributes);
    }

    /**
     * Keep the agent's words for the owner, with the verdict that held them.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function storeDraft(Conversation $conversation, string $words, Verdict $verdict, ReplyLabel $label, array $attributes): void
    {
        $conversation->messages()->create([
            ...$attributes,
            ...$verdict->toAttributes($label),
            'channel' => MessageChannel::Sms,
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Agent,
            'body' => $words,
            'status' => MessageStatus::Draft,
        ]);
    }

    /**
     * Leave a line on the timeline when every model in the agent file failed.
     *
     * @param  Collection<int, Message>  $burst
     */
    private function noteThatNoModelAnswered(Conversation $conversation, Collection $burst): void
    {
        $contact = $conversation->contact;

        $contact->activities()->create([
            'type' => ActivityType::Note,
            'summary' => 'Front desk could not answer: every model failed',
            'subject_type' => $burst->last()->getMorphClass(),
            'subject_id' => $burst->last()->id,
            'occurred_at' => now(),
        ]);

        CustomerRecordChanged::dispatch($contact->id);
    }
}
