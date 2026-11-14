<?php

namespace Pterodactyl\Services\Proxmox;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Jobs\ProxmoxVmJob;
use Pterodactyl\Models\ProxmoxHost;
use Pterodactyl\Models\ProxmoxIp;
use Pterodactyl\Models\ProxmoxVm;
use Pterodactyl\Models\User;

/**
 * Creates and deletes VPS on Proxmox. A new VPS is a full copy of a template prepared with a cloud-init drive; then its
 * cores, memory, root password, SSH key and address are set, its disk is grown and it is started. A deleted VPS is
 * stopped and destroyed with its disks.
 *
 * Each step is short and runs in the queue (ProxmoxVmJob), which comes back every few seconds until the task Proxmox
 * started for the step is over: copying a disk can take minutes, and no worker waits that long.
 */
class ProxmoxProvisioner
{
    // Where each step leads once it is done.
    private const NEXT = [
        'clone' => 'configure',
        'configure' => 'resize',
        'resize' => 'start',
        'start' => 'ready',
        'stop' => 'destroy',
        'destroy' => null,
    ];

    private const MAX_ATTEMPTS = 5;

    public function __construct(private ProxmoxService $proxmox)
    {
    }

    /**
     * Starts the creation of a VPS for a user. The machine shows at once in their panel, as "being created".
     *
     * @throws DisplayException
     */
    public function create(ProxmoxHost $host, User $user, array $data): ProxmoxVm
    {
        $client = $this->proxmox->client($host);
        $template = collect($client->templates())->firstWhere('vmid', (int) $data['template']);
        if (!$template) {
            throw new DisplayException('This template was not found on Proxmox.');
        }

        $sshKey = trim((string) ($data['ssh_key'] ?? ''));
        if ($sshKey !== '' && preg_match('/^(ssh-(rsa|ed25519|dss)|ecdsa-sha2-nistp(256|384|521)|sk-(ssh-ed25519|ecdsa-sha2-nistp256)@openssh\.com) [A-Za-z0-9+\/=]+( [^\r\n]*)?$/', $sshKey) !== 1) {
            throw new DisplayException('The SSH key does not look like a public OpenSSH key (ssh-ed25519 AAAA...).');
        }

        $diskGb = (int) $data['disk'];
        if ($template['disk'] > 0 && $diskGb * 1024 ** 3 < $template['disk']) {
            throw new DisplayException('The disk cannot be smaller than the one of the template (' . (int) ceil($template['disk'] / 1024 ** 3) . ' GB).');
        }

        return DB::transaction(function () use ($host, $user, $data, $client, $template, $sshKey, $diskGb) {
            $ip = null;
            if (!empty($data['ip'])) {
                /** @var ProxmoxIp|null $ip */
                $ip = ProxmoxIp::query()->where('host_id', $host->id)->whereKey((int) $data['ip'])->lockForUpdate()->first();
                if (!$ip || $ip->vm_id !== null) {
                    throw new DisplayException('This IP address is no longer free.');
                }
            }

            // The next free id of Proxmox, past the ones the panel already holds: two VPS made one after the other
            // would get the same id, the copy of the first one not having started yet.
            $vmid = $client->nextId();
            while (ProxmoxVm::query()->where('host_id', $host->id)->where('vmid', $vmid)->exists()) {
                ++$vmid;
            }
            $name = trim((string) $data['name']);
            $vm = ProxmoxVm::query()->create([
                'uuid' => (string) Str::uuid(),
                'host_id' => $host->id,
                'node' => $template['node'],
                'vmid' => $vmid,
                'user_id' => $user->id,
                'name' => $name,
                'state' => 'clone',
                'spec' => [
                    'template' => $template['vmid'],
                    'hostname' => $this->hostname($name, $vmid),
                    'cores' => (int) $data['cores'],
                    'memory' => (int) $data['memory'],
                    'disk' => $diskGb,
                    'ssh_key' => $sshKey !== '' ? $sshKey : null,
                ],
                'password' => Crypt::encryptString(Str::password(16, true, true, false)),
            ]);
            $ip?->update(['vm_id' => $vm->id]);

            DB::afterCommit(fn () => ProxmoxVmJob::dispatch($vm->id));

            return $vm;
        });
    }

    /**
     * Starts deleting a VPS: the machine is stopped and destroyed with its disks, then it leaves the panel and its
     * address is free again.
     */
    public function delete(ProxmoxVm $vm): void
    {
        $vm->update(['state' => 'stop', 'task' => null, 'error' => null, 'attempts' => 0]);
        ProxmoxVmJob::dispatch($vm->id);
    }

    /**
     * Tries a failed creation again, from the step that failed.
     */
    public function retry(ProxmoxVm $vm): void
    {
        $step = (string) (($vm->spec ?? [])['failed_step'] ?? 'clone');
        $vm->update(['state' => array_key_exists($step, self::NEXT) ? $step : 'clone', 'task' => null, 'error' => null, 'attempts' => 0]);
        ProxmoxVmJob::dispatch($vm->id);
    }

    /**
     * Does one step for the machine. True when there is more to do (the job comes back a few seconds later).
     */
    public function step(ProxmoxVm $vm): bool
    {
        if (!array_key_exists($vm->state, self::NEXT)) {
            return false;
        }
        $client = $this->proxmox->client($vm->host);

        try {
            // A task of Proxmox is running for this step: wait for it, then go on.
            if ($vm->task) {
                $task = $client->task($vm->node, $vm->task);
                if ($task['running']) {
                    return true;
                }
                if (!$task['ok']) {
                    return $this->fail($vm, 'Proxmox: ' . ($task['message'] ?: 'the task failed.'), true);
                }

                return $this->advance($vm);
            }

            return match ($vm->state) {
                'clone' => $this->wait($vm, $client->cloneVm($vm->node, (int) $vm->spec['template'], $vm->vmid, (string) $vm->spec['hostname'])),
                'configure' => $this->configure($vm, $client),
                'resize' => $this->resize($vm, $client),
                'start' => $this->wait($vm, $client->power($vm->node, $vm->vmid, 'start')),
                'stop' => $this->stop($vm, $client),
                // The last step left: "destroy".
                default => $this->wait($vm, $client->destroy($vm->node, $vm->vmid)),
            };
        } catch (DisplayException $exception) {
            return $this->fail($vm, $exception->getMessage());
        }
    }

    private function configure(ProxmoxVm $vm, ProxmoxClient $client): bool
    {
        $spec = $vm->spec ?? [];
        $config = $client->config($vm->node, $vm->vmid);
        $values = [
            'cores' => (int) $spec['cores'],
            'sockets' => 1,
            'memory' => (int) $spec['memory'],
            'agent' => '1',
            'ciuser' => 'root',
            'cipassword' => Crypt::decryptString((string) $vm->password),
        ];

        $ip = $vm->ip;
        if ($ip) {
            $values['ipconfig0'] = 'ip=' . $ip->address . '/' . $ip->cidr . ',gw=' . $ip->gateway;
            $values['nameserver'] = '1.1.1.1 8.8.8.8';
            // A host that wants a virtual MAC for the additional IP: put it on the first network card.
            if ($ip->mac && isset($config['net0'])) {
                $values['net0'] = preg_replace('/^([a-z0-9]+)(=[0-9A-Fa-f:]{17})?/', '$1=' . strtoupper($ip->mac), (string) $config['net0'], 1);
            }
        } else {
            $values['ipconfig0'] = 'ip=dhcp';
        }
        if (!empty($spec['ssh_key'])) {
            // Proxmox wants the keys encoded once more than the rest of the form.
            $values['sshkeys'] = rawurlencode((string) $spec['ssh_key']);
        }

        return $this->wait($vm, $client->setConfig($vm->node, $vm->vmid, $values));
    }

    private function resize(ProxmoxVm $vm, ProxmoxClient $client): bool
    {
        $config = $client->config($vm->node, $vm->vmid);
        $disk = $this->bootDisk($config);
        $wanted = (int) ($vm->spec['disk'] ?? 0);
        if ($disk === null || $wanted <= 0 || $this->sizeInGb((string) $config[$disk]) >= $wanted) {
            return $this->advance($vm);
        }

        return $this->wait($vm, $client->resize($vm->node, $vm->vmid, $disk, $wanted . 'G'));
    }

    private function stop(ProxmoxVm $vm, ProxmoxClient $client): bool
    {
        try {
            $status = $client->status($vm->node, $vm->vmid);
        } catch (DisplayException $exception) {
            // The machine is already gone from Proxmox (never created, or deleted there): only the panel is left.
            if (stripos($exception->getMessage(), 'does not exist') !== false) {
                $this->forget($vm);

                return false;
            }
            throw $exception;
        }

        if (($status['status'] ?? '') !== 'running') {
            return $this->advance($vm);
        }

        return $this->wait($vm, $client->power($vm->node, $vm->vmid, 'stop'));
    }

    /**
     * Keeps the task Proxmox started for the step and waits for it; a step that ran at once goes on directly.
     */
    private function wait(ProxmoxVm $vm, ?string $task): bool
    {
        if ($task === null || $task === '' || !str_starts_with($task, 'UPID:')) {
            return $this->advance($vm);
        }
        $vm->update(['task' => $task]);

        return true;
    }

    private function advance(ProxmoxVm $vm): bool
    {
        $next = self::NEXT[$vm->state] ?? null;
        if ($next === null) {
            $this->forget($vm);

            return false;
        }
        $vm->update(['state' => $next, 'task' => null, 'attempts' => 0, 'error' => null]);

        return $next !== 'ready';
    }

    /**
     * A step went wrong. It is tried again a few times (Proxmox can be busy), then the VPS is marked as failed with
     * the reason, for the administration to see and try again.
     */
    private function fail(ProxmoxVm $vm, string $message, bool $final = false): bool
    {
        $attempts = $vm->attempts + 1;
        if (!$final && $attempts < self::MAX_ATTEMPTS) {
            $vm->update(['attempts' => $attempts, 'task' => null]);

            return true;
        }

        $spec = $vm->spec ?? [];
        $spec['failed_step'] = $vm->state;
        $vm->update(['state' => 'failed', 'task' => null, 'error' => mb_substr($message, 0, 500), 'attempts' => $attempts, 'spec' => $spec]);

        return false;
    }

    private function forget(ProxmoxVm $vm): void
    {
        ProxmoxIp::query()->where('vm_id', $vm->id)->update(['vm_id' => null]);
        $vm->forgetWithSubusers();
    }

    /**
     * The disk the machine starts from: the first disk of its boot order, or the first disk found.
     */
    private function bootDisk(array $config): ?string
    {
        $isDisk = fn (string $key) => preg_match('/^(scsi|virtio|sata|ide)\d+$/', $key) === 1
            && isset($config[$key])
            && !str_contains((string) $config[$key], 'media=cdrom')
            && !str_contains((string) $config[$key], 'cloudinit');

        if (preg_match('/order=([^,]+)/', (string) ($config['boot'] ?? ''), $match) === 1) {
            foreach (explode(';', $match[1]) as $key) {
                if ($isDisk($key)) {
                    return $key;
                }
            }
        }
        foreach (['scsi0', 'virtio0', 'sata0', 'ide0'] as $key) {
            if ($isDisk($key)) {
                return $key;
            }
        }

        return null;
    }

    private function sizeInGb(string $value): float
    {
        if (preg_match('/size=(\d+(?:\.\d+)?)([KMGT])?/', $value, $match) !== 1) {
            return 0;
        }
        $unit = ($match[2] ?? '') ?: 'G';
        $factor = ['K' => 1 / 1024 ** 2, 'M' => 1 / 1024, 'G' => 1, 'T' => 1024][$unit];

        return (float) $match[1] * $factor;
    }

    private function hostname(string $name, int $vmid): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9-]+/', '-', Str::lower(Str::ascii($name))), '-');

        return $slug !== '' ? substr($slug, 0, 63) : 'vps-' . $vmid;
    }
}
