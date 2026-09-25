<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api;

/*
|--------------------------------------------------------------------------
| phpMyAdmin sign-in
|--------------------------------------------------------------------------
|
| Endpoint: /api/phpmyadmin
|
| Called by the sign-in script of phpMyAdmin, which runs on the same machine. There is no session
| or user here: the shared secret and the single-use token in the request are what authorise it.
*/

Route::post('/redeem', Api\PhpMyAdminRedeemController::class)->name('api:phpmyadmin.redeem');
