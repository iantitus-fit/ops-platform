<?php

namespace App\Channels;

use App\Support\BookingLinkTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeTextChannel implements TextChannel
{
    /**
     * Every text sent through this instance, oldest first.
     *
     * @var list<array{to: string, body: string, provider_message_id: string}>
     */
    private array $sent = [];

    /**
     * Sends the channel refused because the number had opted out, oldest first.
     *
     * @var list<array{to: string, body: string}>
     */
    private array $refused = [];

    /**
     * The numbers that texted STOP and have not texted START since, like the carrier's own opt-out list.
     *
     * @var array<string, true>
     */
    private array $optedOut = [];

    public function name(): string
    {
        return 'fake';
    }

    /**
     * Pretend to send a text: remember it and log it.
     *
     * A number that opted out is refused the way Twilio refuses it (error 21610): the attempt is kept in refused()
     * and RecipientOptedOut is thrown.
     *
     * The body is customer data, so it is logged only in the local environment. Anywhere
     * else the log carries the number, the id and the length. The log is the app's and the memory is the carrier's: the
     * logged body has any booking address written /book/[link] (D-12), while sent() and refused() keep the whole text,
     * because they are the fake carrier's own memory for tests and the dev chat and are never stored.
     */
    public function send(string $toE164, string $body): string
    {
        if (isset($this->optedOut[$toE164])) {
            $this->refused[] = ['to' => $toE164, 'body' => $body];

            throw new RecipientOptedOut('Twilio error 21610: the recipient opted out');
        }

        $id = 'fake-'.Str::uuid();

        $this->sent[] = ['to' => $toE164, 'body' => $body, 'provider_message_id' => $id];

        $context = ['to' => $toE164, 'provider_message_id' => $id, 'length' => mb_strlen($body)];

        if (app()->environment('local')) {
            $context['body'] = BookingLinkTokens::hide($body);
        }

        Log::info('Fake text sent', $context);

        return $id;
    }

    /**
     * The texts sent through this instance, for tests and the dev chat page.
     *
     * @return list<array{to: string, body: string, provider_message_id: string}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    /**
     * The sends the channel refused because the number had opted out, for tests.
     *
     * @return list<array{to: string, body: string}>
     */
    public function refused(): array
    {
        return $this->refused;
    }

    /**
     * Put a number on the channel's opt-out list, as if it had texted STOP.
     */
    public function optOut(string $e164): void
    {
        $this->optedOut[$e164] = true;
    }

    /**
     * Take a number off the channel's opt-out list, as if it had texted START.
     */
    public function optIn(string $e164): void
    {
        unset($this->optedOut[$e164]);
    }

    /**
     * Read a Twilio-shaped form body (MessageSid, From, To, Body, NumMedia, OptOutType).
     *
     * With no OptOutType in the request, the type is set from the body the way Twilio's Advanced Opt-Out would. A STOP
     * or START then updates the channel's own opt-out list, so later sends behave like the carrier's.
     */
    public function parseInbound(Request $request): InboundText
    {
        $to = $request->input('To');
        $from = (string) $request->input('From');
        $body = (string) $request->input('Body');
        $optOutType = mb_strtoupper(trim((string) $request->input('OptOutType')));

        if ($optOutType === '') {
            $optOutType = (string) TextKeywords::twilioOptOutType($body);
        }

        match ($optOutType) {
            'STOP' => $this->optOut($from),
            'START' => $this->optIn($from),
            default => null,
        };

        return new InboundText(
            provider: $this->name(),
            providerMessageId: (string) $request->input('MessageSid'),
            from: $from,
            to: $to === null ? null : (string) $to,
            body: $body,
            numMedia: (int) $request->input('NumMedia'),
            optOutType: $optOutType === '' ? null : $optOutType,
        );
    }
}
