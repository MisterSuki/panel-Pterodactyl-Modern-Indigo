<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base\PaymentWebhookController;

/*
|--------------------------------------------------------------------------
| Calls of the payment providers
|--------------------------------------------------------------------------
|
| Endpoint: /api/payments. No session and no cookies: the providers call these addresses themselves.
|
*/
Route::post('/stripe', [PaymentWebhookController::class, 'stripe'])->name('payments.stripe');
Route::post('/sumup', [PaymentWebhookController::class, 'sumup'])->name('payments.sumup');
