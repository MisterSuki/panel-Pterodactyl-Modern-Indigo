<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property ProxmoxHost $host
 * @property User $user
 */
class ProxmoxVm extends Model
{
    protected $table = 'proxmox_vms';

    protected $fillable = ['uuid', 'host_id', 'node', 'vmid', 'user_id', 'name'];

    protected $casts = ['host_id' => 'integer', 'vmid' => 'integer', 'user_id' => 'integer'];

    public function host(): BelongsTo
    {
        return $this->belongsTo(ProxmoxHost::class, 'host_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
