<?php

namespace App\Http\Middleware;

use App\Support\BookingLinkTokens;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

/**
 * Laravel's own session middleware, remembering the last page's address with any booking token hidden.
 *
 * Laravel keeps the last page's full address in the session so redirect()->back() can return to it. With the database
 * session driver (.env.example) that address would sit in the sessions table, and for /book/{token} it is a working
 * link (D-12). Everything else about the session starts and saves exactly as before; only a booking address changes,
 * to /book/[link].
 */
class StartSessionHidingBookingTokens extends StartSession
{
    /**
     * Store the previous URL as Laravel does, then write it back with the booking token hidden.
     *
     * @param  Session  $session
     */
    protected function storeCurrentUrl(Request $request, $session): void
    {
        parent::storeCurrentUrl($request, $session);

        $previous = $session->previousUrl();

        if ($previous !== null) {
            $session->setPreviousUrl(BookingLinkTokens::hide($previous));
        }
    }
}
