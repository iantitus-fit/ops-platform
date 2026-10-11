<?php

use App\Http\Controllers\FakeTextWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/text/fake', FakeTextWebhookController::class)->name('webhooks.text.fake');
