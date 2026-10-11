<?php

namespace App\Channels;

use Illuminate\Http\Request;
use LogicException;

class FakeTextChannel implements TextChannel
{
    public function name(): string
    {
        return 'fake';
    }

    public function send(string $toE164, string $body): string
    {
        throw new LogicException('The fake text channel cannot send yet.');
    }

    /**
     * Read a Twilio-shaped form body (MessageSid, From, To, Body, NumMedia).
     */
    public function parseInbound(Request $request): InboundText
    {
        $to = $request->input('To');

        return new InboundText(
            provider: $this->name(),
            providerMessageId: (string) $request->input('MessageSid'),
            from: (string) $request->input('From'),
            to: $to === null ? null : (string) $to,
            body: (string) $request->input('Body'),
            numMedia: (int) $request->input('NumMedia'),
        );
    }
}
