<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTextDriverIsFake
{
    /**
     * The fake webhook has no signature check, so it exists only while the fake driver is on.
     * The config is read on every request, not when routes are registered.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('channels.text.driver') === 'fake', 404);

        return $next($request);
    }
}
