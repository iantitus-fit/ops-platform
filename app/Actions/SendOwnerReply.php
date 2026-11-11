<?php

namespace App\Actions;

use App\Enums\DraftResolution;
use App\Enums\MessageAuthor;
use App\Enums\PausedReason;
use App\Enums\ReplyLabel;
use App\Enums\SendOrigin;
use App\Icm\ActiveConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Rooms\ScheduleAndBooking\BookingSettings;
use App\Rooms\ScheduleAndBooking\ServiceToBook;
use App\Rooms\ScheduleAndBooking\VisitAskedAbout;
use App\Rooms\ScheduleAndBooking\WithdrawUnsentLink;
use App\SendRule\CustomerReading;
use DomainException;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendOwnerReply
{
    /**
     * The longest reply the owner may send: Twilio's limit for one concatenated text.
     */
    public const int MAX_LENGTH = 1600;

    private const string TOO_LONG = 'Keep replies to 1,600 characters or fewer.';

    /**
     * The refusal when the owner's words plus the booking link line would pass the limit (U-36).
     */
    public const string TOO_LONG_WITH_LINK = 'With the booking link this text is over 1,600 characters. Shorten it, or leave the link off.';

    /**
     * How the notice starts when an approved draft went out without the link the owner asked for; the reason follows.
     */
    public const string SENT_WITHOUT_LINK = 'Draft sent without the booking link: ';

    /**
     * The refusal when the owner's words plus the visit line would pass the limit (U-37).
     */
    public const string TOO_LONG_WITH_VISIT_LINE = 'With the visit line this text is over 1,600 characters. Shorten it, or leave the visit line off.';

    /**
     * How the notice starts when an approved draft went out without the visit line the owner asked for; the reason follows.
     */
    public const string SENT_WITHOUT_VISIT_LINE = 'Draft sent without the visit line: ';

    public function __construct(
        private readonly OutboundSender $outboundSender,
        private readonly PauseConversation $pauseConversation,
        private readonly ComposeAutomatedText $composeAutomatedText,
        private readonly ServiceToBook $serviceToBook,
        private readonly MintBookingLink $mintBookingLink,
        private readonly WithdrawUnsentLink $withdrawUnsentLink,
        private readonly ActiveConfig $activeConfig,
        private readonly VisitAskedAbout $visitAskedAbout,
    ) {}

    /**
     * Send the owner's reply to the customer by text and record it.
     *
     * Refusals are validation errors on the body, raised before anything is stored. The sending itself is
     * OutboundSender's job, the one path every text to a customer takes: it holds the lock that lets only one
     * reply per conversation go out at a time, stores the message as queued first and as sent only once the text
     * channel has returned its id. A channel failure leaves the message stored as failed and is raised as a
     * DomainException for the owner, and so is a second reply sent while one is in flight.
     *
     * Once the reply has gone out the assistant steps back from the conversation (D-25), so it does not talk over
     * the owner. The pause happens only after a successful send, and it leaves a conversation that is already paused
     * exactly as it is, so a take over keeps its reason. `$by` is the signed-in owner, recorded as who paused.
     *
     * @throws ValidationException when the reply cannot or should not go out
     * @throws DomainException when the text channel failed to send, or another reply is being sent
     */
    public function handle(Conversation $conversation, string $body, ?User $by = null): Message
    {
        $conversation->refresh();
        $body = trim($body);

        $refusal = $this->whyCannotReply($conversation) ?? $this->whyBodyIsRefused($body);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['body' => $refusal]);
        }

        $message = $this->outboundSender->send($conversation, $body, MessageAuthor::Owner, SendOrigin::Owner);

        $this->pauseConversation->handle($conversation, PausedReason::OwnerReplied, $by);

        return $message;
    }

    /**
     * Send a held draft with the owner's say-so: as the agent wrote it, or with the owner's own words (D-27).
     *
     * The words go out exactly as typed, trimmed, with the sign-off added, and the AI note too if no automated text
     * has reached the customer yet (D-50). The length limit is checked on that composed text, because that is what the
     * customer receives. The draft row itself becomes the sent message, keeping who decided and, when the owner changed
     * the words, the agent's original (D-54).
     *
     * Unlike handle(), this never pauses the assistant: the owner approved the agent's reply, they did not take the
     * conversation over (D-25).
     *
     * A draft labeled scheduling can also carry the owner's booking link line, added here at send time and never put in
     * the draft (D-45). `$addBookingLink` only asks; the draft's label and every D-01 and D-02 condition are checked now,
     * at approval, by ServiceToBook, and a condition that fails after the owner saw the line sends the draft as it is
     * with the reason recorded in meta.booking_link_left_off. The stored reading of the customer's text plays no part
     * here: the owner's approval decides, and the reading only sets where the box starts (bookingLinkStartsOn()). The
     * sent row is the draft row (D-54), so the link's message_id is that row and a booking raises its alert on that
     * row's key (D-33).
     *
     * A draft for a contact with a visit booked ahead can carry the owner's visit line instead, added here at send time
     * and never put in the draft (D-39 amended, settled as D-45). `$visitLineShown` is the line the owner saw with the box
     * checked, and it only asks: when the draft is about scheduling by its label or the stored reading's intent,
     * VisitAskedAbout::whyNoLine() is asked now, at approval, and a reason sends the draft as it is with the reason
     * recorded in meta.visit_line_left_off; so does a line that would differ now from the one shown (WR-09). The two lines
     * are never both drawn, because a visit ahead stops the link (ServiceToBook::VISIT_BOOKED) and the visit line needs
     * one. Nothing is minted, so a refused or failed send has nothing to take back.
     *
     * @throws ValidationException when the reply cannot or should not go out
     * @throws DomainException when the draft is gone, the text channel failed, or another reply is being sent
     */
    public function sendDraft(Message $draft, string $body, User $by, bool $edited, bool $addBookingLink = false, ?string $visitLineShown = null): Message
    {
        $conversation = $draft->conversation;
        $conversation->refresh();
        $words = trim($body);

        $refusal = $this->whyCannotReply($conversation) ?? ($words === '' ? 'Type a message first.' : null);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['body' => $refusal]);
        }

        $composed = $this->composeAutomatedText->forReply($conversation, $words);

        if (mb_strlen($composed) > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => self::TOO_LONG]);
        }

        $attributes = [
            'resolution' => $edited ? DraftResolution::Edited : DraftResolution::Approved,
            'resolved_by' => $by->id,
            'resolved_at' => now(),
            'original_body' => $edited ? $draft->body : null,
            'activity_summary' => $edited ? 'Draft edited by owner and sent' : 'Draft approved by owner and sent',
        ];

        if ($addBookingLink && $draft->label === ReplyLabel::Scheduling) {
            return $this->sendDraftAskingForLink($draft, $conversation, $words, $composed, $attributes);
        }

        if ($visitLineShown !== null && VisitAskedAbout::aboutScheduling($this->storedIntent($draft), $draft->label)) {
            return $this->sendDraftAskingForVisitLine($draft, $conversation, $words, $composed, $attributes, $visitLineShown);
        }

        return $this->outboundSender->sendExisting($draft, $composed, SendOrigin::Owner, $attributes);
    }

    /**
     * The service a booking link would be for if this draft were approved now, or null when none could ride (D-45). The
     * approve step draws its line from this, and sendDraft() asks the same checks, so the screen and the send agree.
     */
    public function bookingLinkServiceFor(Message $draft): ?string
    {
        return $this->serviceToBook->handle($draft->conversation, $draft->label, $draft->meta['service_slug'] ?? null);
    }

    /**
     * Whether the booking link box starts checked on this draft: only when Jev's reading of the customer's text allows
     * owner sentences. A complaint, a failed reading or none starts it unchecked; the owner may still check it (D-45).
     */
    public function bookingLinkStartsOn(Message $draft): bool
    {
        return $this->readingAllowsOwnerSentences($draft);
    }

    /**
     * The visit line the box would add if this draft were approved now, or null when none could ride (D-39 amended). The
     * approve step draws its box from this, and sendDraft() asks the same class, so the screen and the send agree.
     */
    public function visitLineFor(Message $draft): ?string
    {
        $visit = $this->visitAskedAbout->find($draft->conversation->contact, $this->storedIntent($draft), $draft->label);

        return $visit === null ? null : $this->visitAskedAbout->line($visit);
    }

    /**
     * Whether the visit line box starts checked on this draft: under the booking link box's rule, only when Jev's reading
     * of the customer's text allows owner sentences. The owner may still check it (D-39 amended, D-45).
     */
    public function visitLineStartsOn(Message $draft): bool
    {
        return $this->readingAllowsOwnerSentences($draft);
    }

    private function readingAllowsOwnerSentences(Message $draft): bool
    {
        return CustomerReading::fromStoredIfMade($draft->gate['customer_reading'] ?? null)?->allowsOwnerSentences() ?? false;
    }

    /**
     * The intent of Jev's stored reading of the customer's text, or null when none was made.
     */
    private function storedIntent(Message $draft): ?string
    {
        $intent = CustomerReading::fromStoredIfMade($draft->gate['customer_reading'] ?? null)?->answers['intent'] ?? null;

        return is_string($intent) ? $intent : null;
    }

    /**
     * Send an approved draft the owner asked to add the visit line to. A condition that no longer holds sends the draft
     * as it is and records the plain reason. Otherwise the line is rendered now, so it is true when it goes, and it rides
     * only when it is the line the owner saw: a visit that moved, or another visit that became the one asked about, sends
     * the draft as it is with VisitAskedAbout::VISIT_CHANGED (WR-09). The length with the line is measured before
     * anything is sent: over the limit refuses and keeps the draft live (U-37), never drops the line. The line follows the
     * owner's words, and the sent row records which visit it named.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException when the words plus the visit line pass the limit
     * @throws DomainException when the draft is gone, the text channel failed, or another reply is being sent
     */
    private function sendDraftAskingForVisitLine(Message $draft, Conversation $conversation, string $words, string $composed, array $attributes, string $visitLineShown): Message
    {
        $contact = $conversation->contact;
        $reason = $this->visitAskedAbout->whyNoLine($contact);
        $visit = $reason === null ? $this->visitAskedAbout->find($contact, $this->storedIntent($draft), $draft->label) : null;
        $line = $visit === null ? null : $this->visitAskedAbout->line($visit);

        if ($visit === null || $line === null || $line !== $visitLineShown) {
            $leftOff = $line === null
                ? $reason ?? ($visit === null ? VisitAskedAbout::NO_VISIT_AHEAD : ServiceToBook::BOOKING_OFF)
                : VisitAskedAbout::VISIT_CHANGED;

            return $this->outboundSender->sendExisting($draft, $composed, SendOrigin::Owner, [
                ...$attributes,
                'meta' => [...($draft->meta ?? []), 'visit_line_left_off' => $leftOff],
            ]);
        }

        $withLine = $this->composeAutomatedText->forReply($conversation, $words.' '.$line);

        if (mb_strlen($withLine) > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => self::TOO_LONG_WITH_VISIT_LINE]);
        }

        return $this->outboundSender->sendExisting($draft, $withLine, SendOrigin::Owner, [
            ...$attributes,
            'meta' => [...($draft->meta ?? []), 'visit_line_appointment_id' => $visit->id],
        ]);
    }

    /**
     * Send an approved scheduling draft the owner asked to add a link to. A condition that no longer holds sends the
     * draft as it is and records the plain reason. Otherwise the length with the link is measured with a 64-character
     * placeholder token before anything is minted, so a refusal mints nothing; the link is then minted, the line added
     * after the owner's words, and the link attached to the sent row. A link is taken back only when the send itself
     * threw, which OutboundSender never does once the channel has accepted the text, even when its lock release fails
     * (WR-08): it is revoked, and the links its mint superseded are put back (WithdrawUnsentLink). Once the text is out
     * the link stays live and the owner sees the draft sent, even when recording which row carried the link fails; that
     * failure is reported, because BookAppointment::alertOwner() finds the row by meta.booking_link_id (D-33).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException when the words plus the link line pass the limit
     * @throws DomainException when the draft is gone, the text channel failed, or another reply is being sent
     */
    private function sendDraftAskingForLink(Message $draft, Conversation $conversation, string $words, string $composed, array $attributes): Message
    {
        $serviceSlug = $draft->meta['service_slug'] ?? null;
        $reason = $this->serviceToBook->whyNoLink($conversation, $serviceSlug);
        $slug = $reason === null ? $this->serviceToBook->handle($conversation, $draft->label, $serviceSlug) : null;
        $settings = BookingSettings::fromConfig($this->activeConfig);

        if ($reason !== null || $slug === null || $settings === null) {
            return $this->outboundSender->sendExisting($draft, $composed, SendOrigin::Owner, [
                ...$attributes,
                'meta' => [...($draft->meta ?? []), 'booking_link_left_off' => $reason ?? ServiceToBook::BOOKING_OFF],
            ]);
        }

        $measured = $this->composeAutomatedText->forReply($conversation, $words.' '.$settings->renderLink(route('booking.show', str_repeat('0', 64))));

        if (mb_strlen($measured) > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => self::TOO_LONG_WITH_LINK]);
        }

        $supersededIds = $this->withdrawUnsentLink->idsAMintWouldSupersede($conversation);
        [$link, $token] = $this->mintBookingLink->handle($conversation, $slug, now()->toImmutable());

        try {
            $fresh = $draft->fresh() ?? $draft;
            $withLink = $this->composeAutomatedText->forReply($conversation, $words.' '.$settings->renderLink(route('booking.show', $token)));

            $sent = $this->outboundSender->sendExisting($draft, $withLink, SendOrigin::Owner, [
                ...$attributes,
                'meta' => [...($fresh->meta ?? []), 'booking_link_id' => $link->id],
            ]);
        } catch (Throwable $exception) {
            try {
                $this->withdrawUnsentLink->handle($link, $supersededIds);
            } catch (Throwable $failure) {
                report($failure);
            }

            throw $exception;
        }

        try {
            $this->mintBookingLink->attachMessage($link, $sent);
        } catch (Throwable $failure) {
            report($failure);
        }

        return $sent;
    }

    /**
     * Why the owner cannot reply in this conversation at all, or null when a reply could go out.
     * The reply box shows this sentence in place of the live form.
     */
    public function whyCannotReply(Conversation $conversation): ?string
    {
        return $this->outboundSender->whyBlocked($conversation, SendOrigin::Owner);
    }

    private function whyBodyIsRefused(string $body): ?string
    {
        if ($body === '') {
            return 'Type a message first.';
        }

        if (mb_strlen($body) > self::MAX_LENGTH) {
            return self::TOO_LONG;
        }

        return null;
    }
}
