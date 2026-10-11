<?php

namespace App\Channels;

use Illuminate\Http\Request;

interface TextChannel
{
    /**
     * The driver name, stored as the provider on every message it carries.
     */
    public function name(): string;

    /**
     * Send a text to a phone number and return the provider's id for it.
     */
    public function send(string $toE164, string $body): string;

    /**
     * Read the provider's webhook request into the one shape the app understands.
     */
    public function parseInbound(Request $request): InboundText;
}
