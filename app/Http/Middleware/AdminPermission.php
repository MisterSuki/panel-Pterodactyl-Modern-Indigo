<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Limits a section of the admin area to the staff whose role allows it.
 *
 * Usage on a route group: "admin.can:users". Reading pages (GET, HEAD, OPTIONS) needs the
 * "view" or "manage" permission of the section, anything that changes data needs "manage".
 * Add ",manage" to require the manage permission even for reading, which is used for pages
 * that show secrets: "admin.can:nodes,manage". The special section "root" is reserved for
 * full administrators.
 */
class AdminPermission
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, \Closure $next, string $section, ?string $ability = null): mixed
    {
        $user = $request->user();
        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        if ($user->root_admin) {
            return $next($request);
        }

        if ($section === 'root' || !$user->isStaff()) {
            throw new AccessDeniedHttpException();
        }

        $needsManage = $ability === 'manage' || !in_array($request->getMethod(), self::READ_METHODS, true);

        $allowed = $needsManage
            ? $user->hasAdminPermission($section . '.manage')
            : $user->canAccessAdminSection($section);

        if (!$allowed) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }
}
