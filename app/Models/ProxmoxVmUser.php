<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sub-user of a VPS: someone the owner lets see the machine, with what they may do on it. Seeing its state, its
 * figures and its addresses comes with the access; the rest is given one by one.
 *
 * @property int $id
 * @property int $vm_id
 * @property int $user_id
 * @property array $permissions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property ProxmoxVm $vm
 * @property User $user
 */
class ProxmoxVmUser extends Model
{
    protected $table = 'proxmox_vm_users';

    // Start, shut down, restart and stop it; open its console; see the root password set when it was created.
    public const PERMISSIONS = ['power', 'console', 'password'];

    protected $fillable = ['vm_id', 'user_id', 'permissions'];

    protected $casts = ['vm_id' => 'integer', 'user_id' => 'integer', 'permissions' => 'array'];

    public function vm(): BelongsTo
    {
        return $this->belongsTo(ProxmoxVm::class, 'vm_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
