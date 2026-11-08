<?php

use App\Http\Middleware\StartSessionHidingBookingTokens;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: ['webhooks/*', 'leads']);

        /*
         * Twilio signs the form exactly as it sent it, so the Twilio webhooks must not have their values trimmed or
         * their empty strings turned into null before the signature is checked. A text that starts or ends with a
         * space would otherwise be refused as unsigned, and the stored words would not be the customer's own.
         */
        $middleware->trimStrings(except: [fn (Request $request): bool => $request->is('webhooks/text/twilio*')]);
        $middleware->convertEmptyStringsToNull(except: [fn (Request $request): bool => $request->is('webhooks/text/twilio*')]);

        /*
         * The session remembers the last page's address, and for /book/{token} that is a working link (D-12). The
         * replacement starts and saves the session as Laravel's does and stores the address with the token hidden.
         */
        $middleware->web(replace: [StartSession::class => StartSessionHidingBookingTokens::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
