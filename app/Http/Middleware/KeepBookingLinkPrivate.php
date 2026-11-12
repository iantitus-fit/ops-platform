<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KeepBookingLinkPrivate
{
    /**
     * A booking link is a password in a web address. Ask search engines not to index the page and browsers not to send
     * the address on as a referrer, so the token goes nowhere (U-16, T-08-34). The page's meta tags say the same.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
