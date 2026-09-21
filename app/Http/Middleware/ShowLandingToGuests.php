<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Pterodactyl\Http\Controllers\Base\LandingController;
use Pterodactyl\Services\Landing\LandingContent;

/**
 * Someone who is not signed in and opens the address of the panel gets the home page (when the administration has it
 * on) instead of being sent straight to the sign-in page. Signed-in people, and every other address, are not touched.
 */
class ShowLandingToGuests
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD'], true) && $request->path() === '/' && Auth::guest()) {
            if (app(LandingContent::class)->enabled()) {
                return response()->make(app(LandingController::class)->show($request));
            }

            return redirect()->route('auth.login');
        }

        return $next($request);
    }
}
