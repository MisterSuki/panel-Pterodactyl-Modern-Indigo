<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * An administrator opens the panel as one of its users, to see what they see (their servers, websites, files...),
 * then comes back to their own account with the banner at the top of the page. Only root administrators may do it,
 * never on another root administrator, and both the start and the end are written in the activity log.
 */
class ImpersonationController extends Controller
{
    /**
     * The id of the administrator, kept in the session while they use the account of someone else.
     */
    public const SESSION = 'impersonator_id';

    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function start(Request $request, User $user): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        if (!$admin->root_admin) {
            throw new AccessDeniedHttpException('Only a root administrator can open the account of a user.');
        }
        if ($user->id === $admin->id) {
            $this->alert->warning('This is your own account.')->flash();

            return redirect()->route('admin.users.view', $user->id);
        }
        if ($user->root_admin) {
            $this->alert->danger('The account of another root administrator cannot be opened.')->flash();

            return redirect()->route('admin.users.view', $user->id);
        }

        Activity::event('admin:user.impersonate')->subject($user)->property(['username' => $user->username])->log();

        // A session that is already somebody else's keeps the first administrator (no chain of accounts).
        $original = $request->session()->get(self::SESSION) ?? $admin->id;
        $this->switchTo($request, $user);
        $request->session()->put(self::SESSION, $original);

        return redirect('/');
    }

    /**
     * Back to the account of the administrator, on the page of the user they were looking at.
     */
    public function stop(Request $request): JsonResponse
    {
        $id = $request->session()->get(self::SESSION);
        /** @var User|null $admin */
        $admin = $id ? User::query()->find($id) : null;
        $request->session()->forget(self::SESSION);

        /** @var User|null $current */
        $current = $request->user();
        if (!$admin || !$admin->root_admin) {
            // The administrator is gone (or no longer one): the session simply ends.
            Auth::guard()->logout();
            $request->session()->invalidate();

            return new JsonResponse(['redirect' => '/auth/login']);
        }

        $this->switchTo($request, $admin);
        if ($current) {
            Activity::event('admin:user.impersonate-stop')->subject($current)->property(['username' => $current->username])->log();
        }

        return new JsonResponse(['redirect' => $current ? route('admin.users.view', $current->id, false) : '/admin']);
    }

    /**
     * Signs the session in as another account (a new session id), with the password print of that account: the check
     * of the sessions (auth.session) compares it on every page and would sign out a session that kept the old one.
     */
    private function switchTo(Request $request, User $user): void
    {
        /** @var \Illuminate\Auth\SessionGuard $guard */
        $guard = Auth::guard();
        $guard->login($user);
        $request->session()->put('password_hash_' . Auth::getDefaultDriver(), $guard->hashPasswordForCookie($user->getAuthPassword()));
    }
}
