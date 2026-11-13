<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
