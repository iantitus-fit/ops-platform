<?php

namespace App\Channels;

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

    public function name(): string
    {
        return 'fake';
    }

    /**
     * Pretend to send a text: remember it and log it.
     *
     * The body is customer data, so it is logged only in the local environment. Anywhere
     * else the log carries the number, the id and the length.
     */
    public function send(string $toE164, string $body): string
    {
        $id = 'fake-'.Str::uuid();

        $this->sent[] = ['to' => $toE164, 'body' => $body, 'provider_message_id' => $id];

        $context = ['to' => $toE164, 'provider_message_id' => $id, 'length' => mb_strlen($body)];

        if (app()->environment('local')) {
            $context['body'] = $body;
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
     * Read a Twilio-shaped form body (MessageSid, From, To, Body, NumMedia, OptOutType).
     */
    public function parseInbound(Request $request): InboundText
    {
        $to = $request->input('To');
        $optOutType = trim((string) $request->input('OptOutType'));

        return new InboundText(
            provider: $this->name(),
            providerMessageId: (string) $request->input('MessageSid'),
            from: (string) $request->input('From'),
            to: $to === null ? null : (string) $to,
            body: (string) $request->input('Body'),
            numMedia: (int) $request->input('NumMedia'),
            optOutType: $optOutType === '' ? null : mb_strtoupper($optOutType),
        );
    }
}
