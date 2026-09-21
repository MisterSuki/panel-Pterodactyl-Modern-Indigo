<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('namespace', '.*');

// The texts written straight into the pages, in the chosen language. Visitors who are not signed in need them too.
Route::get('/locales/ui.json', Base\UiLocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->name('locales.ui');

// The profile pictures, for the people who are signed in.
Route::get('/avatars/{uuid}', [Base\AvatarController::class, 'show'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->where('uuid', '[0-9a-fA-F-]{36}')
    ->name('avatar');

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon)).+');
