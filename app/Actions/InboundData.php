<?php

namespace App\Actions;

use App\Channels\InboundText;
use App\Enums\MessageChannel;

readonly class InboundData
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public MessageChannel $channel,
        public string $source,
        public string $body,
        public ?string $phone,
        public ?string $email,
        public ?string $name,
        public ?string $provider,
        public ?string $providerMessageId,
        public array $meta = [],
    ) {}

    /**
     * An inbound text, recorded with the source "text".
     */
    public static function fromText(InboundText $text): self
    {
        return new self(
            channel: MessageChannel::Sms,
            source: 'text',
            body: $text->body,
            phone: $text->from,
            email: null,
            name: null,
            provider: $text->provider,
            providerMessageId: $text->providerMessageId,
            meta: ['to' => $text->to, 'num_media' => $text->numMedia],
        );
    }
}
