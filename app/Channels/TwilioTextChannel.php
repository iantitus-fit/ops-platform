<?php

namespace App\Channels;

use Illuminate\Http\Request;
use LogicException;

class TwilioTextChannel implements TextChannel
{
    private const NOT_BUILT = 'The Twilio text channel is built in Phase 2 (TXT-01). Set TEXT_CHANNEL=fake.';

    public function name(): string
    {
        return 'twilio';
    }

    public function send(string $toE164, string $body): string
    {
        throw new LogicException(self::NOT_BUILT);
    }

    /**
     * Read the form Twilio posts to the inbound webhook (MessageSid, From, To, Body, NumMedia, OptOutType).
     *
     * Twilio's older name for the message id, SmsSid, is read when MessageSid is absent. OptOutType is only what
     * Twilio reports when the Messaging Service has Advanced Opt-Out on (STOP, START or HELP); with none, it stays
     * null and RecordInbound falls back to matching the words itself.
     */
    public function parseInbound(Request $request): InboundText
    {
        $to = $request->input('To');
        $optOutType = mb_strtoupper(trim((string) $request->input('OptOutType')));

        return new InboundText(
            provider: $this->name(),
            providerMessageId: (string) ($request->input('MessageSid') ?? $request->input('SmsSid')),
            from: (string) $request->input('From'),
            to: $to === null ? null : (string) $to,
            body: (string) $request->input('Body'),
            numMedia: (int) $request->input('NumMedia'),
            optOutType: $optOutType === '' ? null : $optOutType,
        );
    }
}
