<?php

use App\Http\Controllers\FakeTextWebhookController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\TwilioStatusCallbackController;
use App\Http\Controllers\TwilioTextWebhookController;
use App\Http\Middleware\EnsureLocalEnvironment;
use App\Http\Middleware\EnsureTextDriverIsFake;
use App\Http\Middleware\EnsureTextDriverIsTwilio;
use App\Http\Middleware\ValidateTwilioSignature;
use App\Livewire\DevChat;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/text/fake', FakeTextWebhookController::class)
    ->middleware(EnsureTextDriverIsFake::class)
    ->name('webhooks.text.fake');

Route::post('/webhooks/text/twilio', TwilioTextWebhookController::class)
    ->middleware([EnsureTextDriverIsTwilio::class, ValidateTwilioSignature::class.':inbound'])
    ->name('webhooks.text.twilio');

Route::post('/webhooks/text/twilio/status', TwilioStatusCallbackController::class)
    ->middleware([EnsureTextDriverIsTwilio::class, ValidateTwilioSignature::class.':status'])
    ->name('webhooks.text.twilio.status');

Route::post('/leads', [LeadController::class, 'store'])
    ->middleware('throttle:leads')
    ->name('leads.store');

Route::livewire('/dev/chat', DevChat::class)
    ->middleware(EnsureLocalEnvironment::class)
    ->name('dev.chat');
