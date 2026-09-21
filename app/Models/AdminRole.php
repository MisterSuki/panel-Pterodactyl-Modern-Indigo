<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of permissions for Panel staff who are not full administrators.
 *
 * Permissions are written "<section>.<ability>", for example "users.view". Managing a section
 * always includes viewing it. Access to the Application API and to the roles themselves is
 * deliberately not part of this list: both can be used to give oneself more access, so they
 * stay reserved for full administrators.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property array $permissions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\User[] $users
 * @property int|null $users_count
 */
class AdminRole extends Model
{
    /**
     * Every section of the admin area that can be handed out, with the abilities it offers.
     */
    public const CATALOG = [
        'servers' => [
            'label' => 'Servers',
            'abilities' => [
                'view' => 'See servers and their details.',
                'manage' => 'Create, edit, suspend, reinstall, transfer and delete servers, and see their database credentials.',
            ],
        ],
        'users' => [
            'label' => 'Users',
            'abilities' => [
                'view' => 'See users.',
                'manage' => 'Create, edit and delete regular users. Administrators and other staff can never be changed this way.',
            ],
        ],
        'nodes' => [
            'label' => 'Nodes',
            'abilities' => [
                'view' => 'See nodes, their usage and allocations.',
                'manage' => 'Create, edit and delete nodes and allocations, and see the Wings configuration.',
            ],
        ],
        'locations' => [
            'label' => 'Locations',
            'abilities' => [
                'view' => 'See locations.',
                'manage' => 'Create and edit locations.',
            ],
        ],
        'databases' => [
            'label' => 'Databases',
            'abilities' => [
                'view' => 'See database hosts.',
                'manage' => 'Create, edit and delete database hosts.',
            ],
        ],
        'mounts' => [
            'label' => 'Mounts',
            'abilities' => [
                'view' => 'See mounts.',
                'manage' => 'Create, edit and delete mounts.',
            ],
        ],
        'nests' => [
            'label' => 'Nests & Eggs',
            'abilities' => [
                'view' => 'See nests and eggs.',
                'manage' => 'Create, edit, import and delete nests and eggs.',
            ],
        ],
        'tickets' => [
            'label' => 'Support Tickets',
            'abilities' => [
                'view' => 'Read the support tickets.',
                'manage' => 'Answer, assign and close support tickets, and write internal notes.',
            ],
        ],
        'settings' => [
            'label' => 'Panel Settings',
            'abilities' => [
                'manage' => 'See and change the Panel, mail and advanced settings.',
            ],
        ],
    ];

    protected $table = 'admin_roles';

    protected $fillable = ['name', 'description', 'permissions'];

    protected $casts = ['permissions' => 'array'];

    public static function catalog(): array
    {
        return self::CATALOG;
    }

    /**
     * @return string[]
     */
    public static function allPermissions(): array
    {
        $all = [];
        foreach (self::CATALOG as $section => $info) {
            foreach (array_keys($info['abilities']) as $ability) {
                $all[] = $section . '.' . $ability;
            }
        }

        return $all;
    }

    /**
     * Keep only permissions that exist, and make sure managing a section also allows viewing it.
     *
     * @return string[]
     */
    public static function normalize(array $requested): array
    {
        $valid = array_values(array_intersect(self::allPermissions(), array_map('strval', $requested)));

        foreach ($valid as $permission) {
            [$section, $ability] = explode('.', $permission);
            if ($ability === 'manage' && isset(self::CATALOG[$section]['abilities']['view'])) {
                $valid[] = $section . '.view';
            }
        }

        $valid = array_values(array_unique($valid));
        sort($valid);

        return $valid;
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'admin_role_id');
    }
}
