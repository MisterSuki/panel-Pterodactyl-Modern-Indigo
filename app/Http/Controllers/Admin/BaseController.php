<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Admin\OverviewService;
use Pterodactyl\Services\Admin\ThemeUpdater;
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
     * Asks the update service of the server to update the theme (full administrators only).
     */
    public function themeUpdate(Request $request, ThemeUpdater $updater): JsonResponse
    {
        abort_unless($request->user()->root_admin, 403);
        if (!$updater->available()) {
            return new JsonResponse(['error' => 'The update service is not installed on this server yet.'], 409);
        }
        if (!$updater->request()) {
            return new JsonResponse(['error' => 'An update is already running.'], 409);
        }
        // Tells every user in the bell (and on the status page) that the panel is being updated.
        if ($request->boolean('notify')) {
            app(\Pterodactyl\Services\Notifications\Notifier::class)->broadcast(
                'update',
                'The panel is being updated',
                'It goes into maintenance for a few minutes, then comes back by itself. Game servers keep running.',
                null,
                true
            );
        }

        return new JsonResponse($updater->status());
    }

    /**
     * Where the update asked from the admin area is. The page asks for it every few seconds while it runs.
     */
    public function themeUpdateStatus(Request $request, ThemeUpdater $updater): JsonResponse
    {
        abort_unless($request->user()->root_admin, 403);

        return new JsonResponse($updater->status());
    }

    /**
     * Return the admin index view.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // "Check now": ask GitHub again straight away instead of waiting for the kept answer to expire.
        if ($request->boolean('check_update')) {
            $this->theme->refresh();
        }

        return view('admin.index', [
            'version' => $this->version,
            'theme' => $this->theme->status(),
            'updater' => app(ThemeUpdater::class)->status(),
            'overview' => $this->overview->build(fn (string $section) => $user->canAccessAdminSection($section)),
        ]);
    }
}
