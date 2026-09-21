<?php

namespace Pterodactyl\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Someone who signs out is no longer on the panel: they leave the "active now" list of the administration at once, and
 * not two minutes later.
 */
class ForgetPresenceListener
{
    public function handle(Logout $event): void
    {
        $user = $event->user;
        if ($user === null) {
            return;
        }

        try {
            DB::table('users')->where('id', $user->getAuthIdentifier())->update([
                'last_seen_at' => null,
                'last_seen_page' => null,
                'last_seen_server_id' => null,
            ]);
            Cache::forget('presence:' . $user->getAuthIdentifier());
        } catch (\Throwable) {
            // Signing out must never fail because of this.
        }
    }
}
