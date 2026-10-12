<?php

namespace App\Http\Middleware;

use App\Channels\TwilioSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateTwilioSignature
{
    /**
     * Refuse any request whose X-Twilio-Signature is missing or does not match.
     *
     * The signature is checked against the URL typed into the Twilio Console and kept in config, never one rebuilt
     * from the request: behind a proxy or tunnel the rebuilt scheme, host or port differ and every webhook would be
     * refused. The parameter says which configured URL applies: "inbound" (the default) or "status".
     *
     * A failure is logged with the configured URL's host only: never the token, the signature or the form, which
     * holds the customer's words.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $kind = 'inbound'): Response
    {
        $url = (string) config($kind === 'status' ? 'services.twilio.status_callback_url' : 'services.twilio.webhook_url');
        $token = (string) config('services.twilio.auth_token');

        $valid = $url !== '' && TwilioSignature::matches(
            (string) $request->header('X-Twilio-Signature'),
            [$url],
            $request->request->all(),
            $token,
        );

        if (! $valid) {
            Log::warning('Twilio webhook refused: signature missing or wrong', [
                'host' => parse_url($url, PHP_URL_HOST) ?: null,
            ]);

            abort(403);
        }

        return $next($request);
    }
}
