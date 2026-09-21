<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base\ProxyConfigController;

/*
|--------------------------------------------------------------------------
| The web server of the web hosting
|--------------------------------------------------------------------------
|
| Endpoint: /api/hosting. No session and no cookies: the web server (Caddy) fetches its configuration here, with a token.
|
*/
Route::get('/proxy-config', [ProxyConfigController::class, 'show'])->name('hosting.proxy-config');
