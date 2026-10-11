<?php

use App\Http\Controllers\FakeTextWebhookController;
use App\Http\Controllers\LeadController;
use App\Http\Middleware\EnsureTextDriverIsFake;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/text/fake', FakeTextWebhookController::class)
    ->middleware(EnsureTextDriverIsFake::class)
    ->name('webhooks.text.fake');

Route::post('/leads', [LeadController::class, 'store'])
    ->middleware('throttle:leads')
    ->name('leads.store');
