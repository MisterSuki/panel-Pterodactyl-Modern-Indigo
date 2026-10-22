<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Notifications\StatusPage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StatusPageController extends Controller
{
    public function __construct(private StatusPage $status)
    {
    }

    /**
     * The public status page (no account needed), when the administration opened it.
     */
    public function show(): View
    {
        if (!$this->status->enabled()) {
            throw new NotFoundHttpException();
        }

        return view('status', $this->status->build());
    }
}
