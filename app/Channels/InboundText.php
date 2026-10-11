<?php

namespace App\Channels;

readonly class InboundText
{
    public function __construct(
        public string $provider,
        public string $providerMessageId,
        public string $from,
        public ?string $to,
        public string $body,
        public int $numMedia,
    ) {}
}
