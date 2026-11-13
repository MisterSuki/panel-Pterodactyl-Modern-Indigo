<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Proxmox server (or cluster) the panel talks to through its API, with an API token (see Services\Proxmox).
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $token_id
 * @property string $token_secret encrypted with the key of the panel
 * @property bool $verify_tls
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int, ProxmoxVm> $vms
 */
class ProxmoxHost extends Model
{
    protected $table = 'proxmox_hosts';

    protected $fillable = ['name', 'url', 'token_id', 'token_secret', 'verify_tls'];

    protected $hidden = ['token_secret'];

    protected $casts = ['verify_tls' => 'boolean'];

    public function vms(): HasMany
    {
        return $this->hasMany(ProxmoxVm::class, 'host_id');
    }
}
