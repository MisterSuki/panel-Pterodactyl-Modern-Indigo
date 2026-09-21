<?php

namespace Pterodactyl\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Databases\PhpMyAdminSignOn;

class PhpMyAdminRedeemController extends Controller
{
    public function __construct(private PhpMyAdminSignOn $signOn)
    {
    }

    /**
     * Called by the sign-in script of phpMyAdmin, never by a browser. It gives the credentials of a
     * database in exchange for a single-use token, and only to a caller that knows the shared secret.
     * Every kind of failure gets the same answer, so it says nothing about what was wrong.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = PhpMyAdminSignOn::secret();
        $given = (string) $request->header('X-Signon-Secret', '');
        $token = $request->json('token');

        $credentials = null;
        if ($secret !== null && hash_equals($secret, $given) && is_string($token)) {
            $credentials = $this->signOn->redeem($token);
        }

        if ($credentials === null) {
            return new JsonResponse(['error' => 'Not found.'], 404, ['Cache-Control' => 'no-store']);
        }

        return new JsonResponse($credentials, 200, ['Cache-Control' => 'no-store']);
    }
}
