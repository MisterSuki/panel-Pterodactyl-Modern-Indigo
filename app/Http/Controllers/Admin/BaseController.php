<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Admin\OverviewService;
use Pterodactyl\Services\Admin\ThemeVersionService;
use Pterodactyl\Services\Admin\UserLiveService;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\SoftwareVersionService;

class BaseController extends Controller
{
    /**
     * BaseController constructor.
     */
    public function __construct(
        private SoftwareVersionService $version,
        private OverviewService $overview,
        private UserLiveService $live,
        private ThemeVersionService $theme
    ) {
    }

    /**
     * The people who are on the panel right now. The home page asks for it every few seconds.
     */
    public function presence(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAccessAdminSection('users'), 403);

        return new JsonResponse(['object' => 'presence', 'data' => $this->overview->online()]);
    }

    /**
     * Where one person is and what they did last, so that the staff can help them. For the staff who see the users or
     * the tickets, since it is asked from the page of a user and from a ticket.
     */
    public function person(Request $request, int $id): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer->canAccessAdminSection('users') || $viewer->canAccessAdminSection('tickets'), 403);

        return new JsonResponse($this->live->build(User::query()->findOrFail($id)));
    }

    /**
     * Return the admin index view.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('admin.index', [
            'version' => $this->version,
            'theme' => $this->theme->status(),
            'overview' => $this->overview->build(fn (string $section) => $user->canAccessAdminSection($section)),
        ]);
    }
}
