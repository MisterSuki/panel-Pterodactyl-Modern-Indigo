<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Discord\DiscordBot;
use Pterodactyl\Services\Discord\DiscordInteractions;

/**
 * The address Discord sends the slash commands and the buttons of the bot to. Nothing is trusted before the signature
 * of Discord is checked with the public key of the application.
 */
class DiscordInteractionController extends Controller
{
    // Discord sends at once: an older request is one sent again by someone else.
    private const MAX_AGE_SECONDS = 300;

    public function __construct(private DiscordBot $bot, private DiscordInteractions $interactions)
    {
    }

    public function __invoke(Request $request): JsonResponse|Response
    {
        $signature = (string) $request->header('X-Signature-Ed25519');
        $timestamp = (string) $request->header('X-Signature-Timestamp');
        $key = $this->bot->publicKey();

        if (
            preg_match('/^[0-9a-f]{128}$/i', $signature) !== 1
            || preg_match('/^\d{1,12}$/', $timestamp) !== 1
            || abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS
            || preg_match('/^[0-9a-f]{64}$/', $key) !== 1
            || !sodium_crypto_sign_verify_detached(hex2bin($signature), $timestamp . $request->getContent(), hex2bin($key))
        ) {
            return new Response('invalid request signature', 401);
        }

        $interaction = json_decode($request->getContent(), true);

        return new JsonResponse($this->interactions->handle(is_array($interaction) ? $interaction : []));
    }
}
