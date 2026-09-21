<?php

namespace Pterodactyl\Providers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Database;
use Pterodactyl\Enum\ResourceLimit;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Pterodactyl\Http\Middleware\TrimStrings;
use Pterodactyl\Http\Middleware\AdminAuthenticate;
use Pterodactyl\Http\Middleware\ShowLandingToGuests;
use Pterodactyl\Http\Controllers\Base\LandingController;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected const FILE_PATH_REGEX = '/^\/api\/client\/servers\/([a-z0-9-]{36})\/files(\/?$|\/(.)*$)/i';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Disable trimming string values when requesting file information — it isn't helpful
        // and messes up the ability to actually open a directory that ends with a space.
        TrimStrings::skipWhen(function (Request $request) {
            return preg_match(self::FILE_PATH_REGEX, $request->getPathInfo()) === 1;
        });

        // This is needed to make use of the "resolveRouteBinding" functionality in the
        // model. Without it you'll never trigger that logic flow thus resulting in a 404
        // error because we request databases with a HashID, and not with a normal ID.
        Route::model('database', Database::class);

        $this->routes(function () {
            Route::middleware('web')->group(function () {
                // The home page, which anybody can open (the administration can see it even when signed in).
                Route::get('/welcome', [LandingController::class, 'show'])->name('landing');

                Route::middleware([ShowLandingToGuests::class, 'auth.session', RequireTwoFactorAuthentication::class])
                    ->group(base_path('routes/base.php'));

                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class, AdminAuthenticate::class])
                    ->prefix('/admin')
                    ->group(base_path('routes/admin.php'));

                Route::middleware('guest')->prefix('/auth')->group(base_path('routes/auth.php'));
            });

            Route::middleware(['api', RequireTwoFactorAuthentication::class])->group(function () {
                Route::middleware(['application-api', 'throttle:api.application'])
                    ->prefix('/api/application')
                    ->scopeBindings()
                    ->group(base_path('routes/api-application.php'));

                Route::middleware(['client-api', 'throttle:api.client'])
                    ->prefix('/api/client')
                    ->scopeBindings()
                    ->group(base_path('routes/api-client.php'));
            });

            // Called by the web server of the web hosting (Caddy): it fetches its configuration with a token.
            Route::middleware('throttle:60,1')
                ->prefix('/api/hosting')
                ->group(base_path('routes/hosting.php'));

            // Called by the payment providers (Stripe, SumUp): no session, no user, and every call is checked with the provider.
            Route::middleware('throttle:120,1')
                ->prefix('/api/payments')
                ->group(base_path('routes/payments.php'));

            // Called by the phpMyAdmin sign-in script on this machine: no session, no user, only a shared secret.
            Route::middleware('throttle:60,1')
                ->prefix('/api/phpmyadmin')
                ->group(base_path('routes/phpmyadmin.php'));

            Route::middleware('daemon')
                ->prefix('/api/remote')
                ->scopeBindings()
                ->group(base_path('routes/api-remote.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Authentication rate limiting. For login and checkpoint endpoints we'll apply
        // a limit of 10 requests per minute, for the forgot password endpoint apply a
        // limit of two per minute for the requester so that there is less ability to
        // trigger email spam.
        RateLimiter::for('authentication', function (Request $request) {
            if ($request->route()->named('auth.post.forgot-password')) {
                return Limit::perMinute(2)->by($request->ip());
            }

            return Limit::perMinute(10)->by($request->ip());
        });

        // Configure the throttles for both the application and client APIs below.
        // This is configurable per-instance in "config/http.php". By default this
        // limiter will be tied to the specific request user, and falls back to the
        // request IP if there is no request user present for the key.
        //
        // This means that an authenticated API user cannot use IP switching to get
        // around the limits.
        RateLimiter::for('api.client', function (Request $request) {
            $key = optional($request->user())->uuid ?: $request->ip();

            return Limit::perMinutes(
                config('http.rate_limit.client_period'),
                config('http.rate_limit.client')
            )->by($key);
        });

        RateLimiter::for('api.application', function (Request $request) {
            $key = optional($request->user())->uuid ?: $request->ip();

            return Limit::perMinutes(
                config('http.rate_limit.application_period'),
                config('http.rate_limit.application')
            )->by($key);
        });

        ResourceLimit::boot();
    }
}
