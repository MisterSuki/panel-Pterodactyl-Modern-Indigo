<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A virtual machine of a Proxmox host, given to a user as a VPS. The user sees it in the panel and can start, stop and
 * restart it; everything else stays on Proxmox.
 *
 * @property int $id
 * @property string $uuid
 * @property int $host_id
 * @property string $node
 * @property int $vmid
 * @property int $user_id
 * @property string $name
 * @property string $state "ready", a step of its creation ("clone", "configure", "resize", "start"), of its deletion
 *                         ("stop", "destroy"), or "failed"
 * @property string|null $task
 * @property string|null $error
 * @property int $attempts
 * @property array|null $spec
 * @property string|null $password encrypted root password
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property ProxmoxHost $host
 * @property User $user
 * @property ProxmoxIp|null $ip
 * @property \Illuminate\Database\Eloquent\Collection<int, ProxmoxVmUser> $subusers
 */
class ProxmoxVm extends Model
{
    protected $table = 'proxmox_vms';

    public const CREATING = ['clone', 'configure', 'resize', 'start'];

    public const DELETING = ['stop', 'destroy'];

    protected $fillable = ['uuid', 'host_id', 'node', 'vmid', 'user_id', 'name', 'state', 'task', 'error', 'attempts', 'spec', 'password'];

    protected $hidden = ['password', 'spec'];

    protected $attributes = ['state' => 'ready', 'attempts' => 0];

    protected $casts = ['host_id' => 'integer', 'vmid' => 'integer', 'user_id' => 'integer', 'attempts' => 'integer', 'spec' => 'array'];

    public function isReady(): bool
    {
        return $this->state === 'ready';
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(ProxmoxHost::class, 'host_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ip(): HasOne
    {
        return $this->hasOne(ProxmoxIp::class, 'vm_id');
    }

    public function subusers(): HasMany
    {
        return $this->hasMany(ProxmoxVmUser::class, 'vm_id');
    }

    /**
     * The VPS a person can see: the ones they own and the ones they are a sub-user of.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereIn('id', ProxmoxVmUser::query()->select('vm_id')->where('user_id', $user->id));
        });
    }

    /**
     * What the person may do on this VPS: everything for the owner ("owner" included), the permissions given to a
     * sub-user, nothing for anyone else.
     *
     * @return array<int, string>
     */
    public function permissionsFor(User $user): array
    {
        if ($this->user_id === $user->id) {
            return array_merge(['owner'], ProxmoxVmUser::PERMISSIONS);
        }

        /** @var ProxmoxVmUser|null $subuser */
        $subuser = $this->subusers()->where('user_id', $user->id)->first();

        return $subuser ? array_values(array_intersect(ProxmoxVmUser::PERMISSIONS, (array) $subuser->permissions)) : [];
    }

    /**
     * Removes the machine from the panel, with its sub-users (the database removes them too where it can).
     */
    public function forgetWithSubusers(): void
    {
        $this->subusers()->delete();
        $this->delete();
    }
}
