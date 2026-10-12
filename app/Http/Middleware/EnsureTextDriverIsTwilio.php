<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTextDriverIsTwilio
{
    /**
     * The Twilio webhooks exist only while the Twilio driver is on, so switching TEXT_CHANNEL changes which
     * door answers with no code change. The config is read on every request, not when routes are registered.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('channels.text.driver') === 'twilio', 404);

        return $next($request);
    }
}
