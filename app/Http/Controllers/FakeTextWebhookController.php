<?php

namespace App\Http\Controllers;

use App\Actions\InboundData;
use App\Actions\RecordInbound;
use App\Channels\FakeTextChannel;
use App\Http\Requests\FakeTextWebhookRequest;
use Illuminate\Http\Response;

class FakeTextWebhookController extends Controller
{
    /**
     * Record a Twilio-shaped text and answer with the empty TwiML reply Twilio expects.
     */
    public function __invoke(
        FakeTextWebhookRequest $request,
        FakeTextChannel $channel,
        RecordInbound $recordInbound,
    ): Response {
        $recordInbound->handle(InboundData::fromText($channel->parseInbound($request)));

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
