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

    public function parseInbound(Request $request): InboundText
    {
        throw new LogicException(self::NOT_BUILT);
    }
}
