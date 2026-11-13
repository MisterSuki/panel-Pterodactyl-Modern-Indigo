<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public address that can be given to a VPS created by the panel. cloud-init writes it in the machine, with its
 * gateway; some hosts also want a virtual MAC address on the network card for an additional IP.
 *
 * @property int $id
 * @property int $host_id
 * @property string $address
 * @property int $cidr
 * @property string $gateway
 * @property string|null $mac
 * @property int|null $vm_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property ProxmoxHost $host
 * @property ProxmoxVm|null $vm
 */
class ProxmoxIp extends Model
{
    protected $table = 'proxmox_ips';

    protected $fillable = ['host_id', 'address', 'cidr', 'gateway', 'mac', 'vm_id'];

    protected $casts = ['host_id' => 'integer', 'cidr' => 'integer', 'vm_id' => 'integer'];

    public function host(): BelongsTo
    {
        return $this->belongsTo(ProxmoxHost::class, 'host_id');
    }

    public function vm(): BelongsTo
    {
        return $this->belongsTo(ProxmoxVm::class, 'vm_id');
    }
}
