<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerCustomDomain;
use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Models\ServerWebDomain;
use Pterodactyl\Models\User;

/**
 * The search of the whole administration (Ctrl+K): servers, users, nodes and domains, only in the sections the person
 * may see. An address such as 1.2.3.4:25565 or a domain leads to the server that uses it.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 6;

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));
        if (mb_strlen($query) < 2) {
            return new JsonResponse(['groups' => []]);
        }
        $query = mb_substr($query, 0, 100);
        $like = '%' . addcslashes(mb_strtolower($query), '%_\\') . '%';
        $user = $request->user();
        $groups = [];

        if ($user->canAccessAdminSection('servers')) {
            $groups[] = ['label' => 'Servers', 'items' => $this->servers($query, $like)];
        }
        if ($user->canAccessAdminSection('users')) {
            $groups[] = ['label' => 'Users', 'items' => User::query()
                ->where(fn (Builder $q) => $q->whereRaw('LOWER(username) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(CONCAT(name_first, ' ', name_last)) LIKE ?", [$like]))
                ->orderBy('username')->limit(self::PER_GROUP)->get()
                ->map(fn (User $u) => [
                    'title' => $u->username,
                    'subtitle' => $u->email . ($u->root_admin ? ' · admin' : ''),
                    'icon' => 'fa-user',
                    'url' => route('admin.users.view', $u->id),
                ])->values()];
        }
        if ($user->canAccessAdminSection('nodes')) {
            $groups[] = ['label' => 'Nodes', 'items' => Node::query()
                ->where(fn (Builder $q) => $q->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(fqdn) LIKE ?', [$like]))
                ->orderBy('name')->limit(self::PER_GROUP)->get()
                ->map(fn (Node $n) => [
                    'title' => $n->name,
                    'subtitle' => $n->fqdn,
                    'icon' => 'fa-sitemap',
                    'url' => route('admin.nodes.view', $n->id),
                ])->values()];
        }

        return new JsonResponse(['groups' => array_values(array_filter($groups, fn ($g) => count($g['items']) > 0))]);
    }

    private function servers(string $query, string $like): array
    {
        $ids = Server::query()
            ->leftJoin('users', 'users.id', '=', 'servers.owner_id')
            ->where(fn (Builder $q) => $q->whereRaw('LOWER(servers.name) LIKE ?', [$like])
                ->orWhere('servers.uuidShort', $query)
                ->orWhere('servers.uuid', 'LIKE', addcslashes($query, '%_\\') . '%')
                ->orWhereRaw('LOWER(users.username) LIKE ?', [$like])
                ->orWhereRaw('LOWER(users.email) LIKE ?', [$like]))
            ->limit(self::PER_GROUP)->pluck('servers.id')->all();

        // An address (IP, IP:port or port) or a domain of the server.
        if (preg_match('/^(?:(\d{1,3}(?:\.\d{1,3}){3}))?:?(\d{1,5})?$/', $query, $m) === 1 && (($m[1] ?? '') !== '' || ($m[2] ?? '') !== '')) {
            $allocations = Allocation::query()->whereNotNull('server_id')
                ->when(($m[1] ?? '') !== '', fn ($q) => $q->where(fn ($w) => $w->where('ip', $m[1])->orWhere('ip_alias', $m[1])))
                ->when(($m[2] ?? '') !== '', fn ($q) => $q->where('port', (int) $m[2]))
                ->limit(self::PER_GROUP)->pluck('server_id')->all();
            $ids = array_merge($ids, $allocations);
        }
        $lower = mb_strtolower($query);
        $ids = array_merge(
            $ids,
            ServerWebDomain::query()->whereRaw('LOWER(hostname) LIKE ?', [$like])->limit(self::PER_GROUP)->pluck('server_id')->all(),
            ServerCustomDomain::query()->whereRaw('LOWER(hostname) LIKE ?', [$like])->limit(self::PER_GROUP)->pluck('server_id')->all(),
            ServerSubdomain::query()->whereRaw('LOWER(name) = ?', [explode('.', $lower)[0]])->limit(self::PER_GROUP)->pluck('server_id')->all(),
        );

        return Server::query()->with(['user', 'node', 'allocation'])->whereIn('id', array_unique($ids))
            ->limit(self::PER_GROUP)->get()
            ->map(fn (Server $s) => [
                'title' => $s->name,
                'subtitle' => implode(' · ', array_filter([
                    $s->user?->username,
                    $s->node?->name,
                    $s->allocation ? $s->allocation->alias . ':' . $s->allocation->port : null,
                ])),
                'icon' => 'fa-server',
                'url' => route('admin.servers.view', $s->id),
                'badge' => $s->isSuspended() ? 'Suspended' : null,
            ])->values()->all();
    }
}
