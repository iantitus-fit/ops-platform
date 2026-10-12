<?php

namespace App\Channels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioTextChannel implements TextChannel
{
    /**
     * The code Twilio gives when a number has opted out of the Messaging Service.
     */
    private const int OPTED_OUT_CODE = 21610;

    public function name(): string
    {
        return 'twilio';
    }

    /**
     * Send a text through the Messaging Service and return Twilio's sid for it.
     *
     * Sending through the Messaging Service, not from a bare number, is what makes Twilio's Advanced Opt-Out apply
     * to our sends (D-39). A refusal with HTTP 400 and Twilio code 21610 means the customer opted out and our own
     * record had not caught it yet, so it is raised as RecipientOptedOut for OutboundSender to act on (D-40). Any
     * other failure is raised with the HTTP status and Twilio's code only: never the auth token, the account, or
     * Twilio's own wording, which can repeat the customer's number.
     *
     * @throws RecipientOptedOut when Twilio refuses because the number opted out
     * @throws RuntimeException when Twilio is not configured or the send failed
     */
    public function send(string $toE164, string $body): string
    {
        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');
        $messagingServiceSid = (string) config('services.twilio.messaging_service_sid');
        $statusCallback = (string) config('services.twilio.status_callback_url');

        if ($accountSid === '' || $authToken === '' || $messagingServiceSid === '') {
            throw new RuntimeException('Twilio is not configured');
        }

        $form = [
            'To' => $toE164,
            'MessagingServiceSid' => $messagingServiceSid,
            'Body' => $body,
        ];

        if ($statusCallback !== '') {
            $form['StatusCallback'] = $statusCallback;
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->acceptJson()
                ->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $form);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Twilio send failed (no answer from Twilio)', previous: $exception);
        }

        $code = $response->json('code');
        $code = is_scalar($code) ? (string) $code : 'none';

        if ($response->status() === 400 && $code === (string) self::OPTED_OUT_CODE) {
            throw new RecipientOptedOut('Twilio refused the text: the number has opted out (21610)');
        }

        if ($response->failed()) {
            throw new RuntimeException("Twilio send failed (HTTP {$response->status()}, code {$code})");
        }

        $sid = $response->json('sid');

        if (! is_string($sid) || $sid === '') {
            throw new RuntimeException("Twilio send failed (HTTP {$response->status()}, no message sid)");
        }

        return $sid;
    }

    /**
     * Read the form Twilio posts to the inbound webhook (MessageSid, From, To, Body, NumMedia, OptOutType).
     *
     * Twilio's older name for the message id, SmsSid, is read when MessageSid is absent. OptOutType is only what
     * Twilio reports when the Messaging Service has Advanced Opt-Out on (STOP, START or HELP); with none, it stays
     * null and RecordInbound falls back to matching the words itself.
     */
    public function parseInbound(Request $request): InboundText
    {
        $to = $request->input('To');
        $optOutType = mb_strtoupper(trim((string) $request->input('OptOutType')));

        return new InboundText(
            provider: $this->name(),
            providerMessageId: (string) ($request->input('MessageSid') ?? $request->input('SmsSid')),
            from: (string) $request->input('From'),
            to: $to === null ? null : (string) $to,
            body: (string) $request->input('Body'),
            numMedia: (int) $request->input('NumMedia'),
            optOutType: $optOutType === '' ? null : $optOutType,
        );
    }
}
