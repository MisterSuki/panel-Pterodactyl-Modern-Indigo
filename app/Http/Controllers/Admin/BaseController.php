<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Services\Admin\OverviewService;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\SoftwareVersionService;

class BaseController extends Controller
{
    /**
     * BaseController constructor.
     */
    public function __construct(private SoftwareVersionService $version, private OverviewService $overview)
    {
    }

    /**
     * Return the admin index view.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('admin.index', [
            'version' => $this->version,
            'overview' => $this->overview->build(fn (string $section) => $user->canAccessAdminSection($section)),
        ]);
    }
}
