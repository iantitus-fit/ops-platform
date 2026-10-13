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
            meta: [
                'to' => $text->to,
                'num_media' => $text->numMedia,
                ...($text->optOutType === null ? [] : ['opt_out_type' => $text->optOutType]),
            ],
        );
    }

    /**
     * A web form lead. The source is the lowercased, trimmed key the form named, or "direct"
     * when it named none. The form's own fields are kept under meta.form for the customer card.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromLeadForm(array $validated): self
    {
        $rawSource = $validated['source'] ?? null;
        $source = mb_strtolower(trim((string) $rawSource));

        return new self(
            channel: MessageChannel::Web,
            source: $source === '' ? 'direct' : $source,
            body: (string) ($validated['message'] ?? ''),
            phone: $validated['phone'] ?? null,
            email: $validated['email'] ?? null,
            name: $validated['name'] ?? null,
            provider: null,
            providerMessageId: null,
            meta: ['form' => [
                'name' => $validated['name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'source' => $rawSource,
                'message' => $validated['message'] ?? null,
            ]],
        );
    }
}
