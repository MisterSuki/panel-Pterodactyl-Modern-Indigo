<?php

namespace Pterodactyl\Http\Controllers\Api\Remote;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\WebHosting\WebHostingService;
use Webmozart\Assert\Assert;

/**
 * What the web agent of a node asks: it signs in with the token of Wings, so it only ever sees its own node.
 */
class WebSitesController extends Controller
{
    public function __construct(private WebHostingService $web)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Assert::isInstanceOf($node = $request->attributes->get('node'), Node::class);

        return new JsonResponse($this->web->feed($node));
    }

    public function status(Request $request): JsonResponse
    {
        Assert::isInstanceOf($node = $request->attributes->get('node'), Node::class);
        $data = $request->validate([
            'version' => ['required', 'string', 'max:16'],
            'domains' => ['present', 'array', 'max:5000'],
            'domains.*.hostname' => ['required', 'string', 'max:191'],
            'domains.*.ssl' => ['required', 'string', 'max:16'],
            'domains.*.error' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->web->saveReport($node, $data['domains'], $data['version']);

        return new JsonResponse([]);
    }
}
