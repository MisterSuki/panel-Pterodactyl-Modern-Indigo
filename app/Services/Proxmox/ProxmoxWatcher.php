<?php

namespace Pterodactyl\Services\Proxmox;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Models\ProxmoxHost;

/**
 * Asks every Proxmox host, every five minutes, which of its nodes are online: the VPS part of the status page.
 */
class ProxmoxWatcher
{
    public function __construct(private ProxmoxService $proxmox)
    {
    }

    public function sample(): void
    {
        if (!Schema::hasTable('proxmox_node_samples')) {
            return;
        }

        $rows = [];
        foreach (ProxmoxHost::query()->get() as $host) {
            $state = [];
            try {
                foreach ($this->proxmox->client($host)->nodes() as $node) {
                    $name = (string) ($node['node'] ?? '');
                    if ($name !== '') {
                        $state[$name] = ($node['status'] ?? '') === 'online';
                    }
                }
            } catch (\Throwable) {
                // The host does not answer: its nodes are down (the ones seen this week, or the host itself).
                $known = DB::table('proxmox_node_samples')->where('host_id', $host->id)
                    ->where('created_at', '>=', now()->subDays(7))->distinct()->pluck('node');
                foreach ($known as $name) {
                    $state[(string) $name] = false;
                }
                if (empty($state)) {
                    $state[mb_substr($host->name, 0, 64)] = false;
                }
            }

            cache()->put('watch:proxmox:' . $host->id, ['nodes' => $state, 'at' => time()], 900);
            foreach ($state as $name => $online) {
                $rows[] = ['host_id' => $host->id, 'node' => mb_substr((string) $name, 0, 64), 'online' => $online, 'created_at' => now()];
            }
        }

        if (!empty($rows)) {
            DB::table('proxmox_node_samples')->insert($rows);
        }
        if ((int) date('G') === 3 && (int) date('i') < 5) {
            DB::table('proxmox_node_samples')->where('created_at', '<', now()->subDays(30))->delete();
        }
    }
}
