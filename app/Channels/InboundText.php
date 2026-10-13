<?php

namespace App\Channels;

readonly class InboundText
{
    /**
     * @param  ?string  $optOutType  STOP, START or HELP when the provider recognised a keyword (Twilio Advanced Opt-Out), otherwise null
     */
    public function __construct(
        public string $provider,
        public string $providerMessageId,
        public string $from,
        public ?string $to,
        public string $body,
        public int $numMedia,
        public ?string $optOutType = null,
    ) {}
}
