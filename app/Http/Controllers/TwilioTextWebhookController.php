<?php

namespace App\Http\Controllers;

use App\Actions\InboundData;
use App\Actions\RecordInbound;
use App\Channels\TwilioTextChannel;
use App\Http\Requests\TwilioTextWebhookRequest;
use Illuminate\Http\Response;

class TwilioTextWebhookController extends Controller
{
    /**
     * Record a signed Twilio text and answer with the empty TwiML reply Twilio expects.
     *
     * Opt-out keywords are handled inside RecordInbound; Twilio sends the customer its own confirmation, so
     * this answer is empty either way.
     */
    public function __invoke(
        TwilioTextWebhookRequest $request,
        TwilioTextChannel $channel,
        RecordInbound $recordInbound,
    ): Response {
        $recordInbound->handle(InboundData::fromText($channel->parseInbound($request)));

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
