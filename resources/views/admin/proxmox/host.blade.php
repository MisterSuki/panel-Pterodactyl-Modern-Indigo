{{-- One Proxmox host in Administration > Proxmox: its nodes, its machines, its VPS, the creation form and its
     addresses. Included by admin/proxmox.blade.php, or sent alone when the page asks for it after opening. --}}
@php
    $bytes = function ($value) {
        $value = (float) $value;
        foreach (['B', 'KiB', 'MiB', 'GiB', 'TiB'] as $unit) {
            if ($value < 1024 || $unit === 'TiB') {
                return ($unit === 'B' ? (int) $value : number_format($value, 1)) . ' ' . $unit;
            }
            $value /= 1024;
        }
    };
    $percent = fn ($used, $total) => $total > 0 ? max(0, min(100, round($used / $total * 100))) : 0;
    $tone = fn ($p) => $p >= 90 ? 'is-red' : ($p >= 75 ? 'is-orange' : '');
    $uptime = function ($seconds) {
        $seconds = (int) $seconds;
        if ($seconds <= 0) return '—';
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        return $days > 0 ? $days . ' d ' . $hours . ' h' : $hours . ' h ' . intdiv($seconds % 3600, 60) . ' min';
    };
    $host = $entry['host'];
    $overview = $entry['overview'];
    $assigned = $entry['assigned'];
    $panelVms = $entry['panelVms'];
    $ips = $entry['ips'];
@endphp
<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $host->name }}
            @if ($overview['ok'])
                <span class="pd-pill pd-pill--info" style="margin-left: 6px;">Proxmox VE {{ $overview['version'] }}</span>
            @else
                <span class="pd-pill" style="margin-left: 6px; color: #ff9592;"><span>Not reachable</span></span>
            @endif
        </h3>
        <div class="box-tools">
            <form method="POST" action="{{ route('admin.proxmox.hosts.test', $host->id) }}" style="display: inline;">
                {!! csrf_field() !!}
                <button type="submit" class="btn btn-xs btn-default"><i class="fa fa-plug"></i> <span>Test</span></button>
            </form>
            <a href="{{ route('admin.proxmox', ['edit' => $host->id]) }}" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i> <span>Edit</span></a>
            <form method="POST" action="{{ route('admin.proxmox.hosts.delete', $host->id) }}" style="display: inline;" onsubmit="return confirm('Remove this host? Its VPS will no longer be shown to their users. Nothing is changed on Proxmox.');">
                {!! csrf_field() !!}
                <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
            </form>
        </div>
    </div>
    <div class="box-body">
        <p class="pd-px-help" style="margin: 0 0 12px;"><code>{{ $host->url }}</code> · <code>{{ $host->token_id }}</code></p>

        @if (!$overview['ok'])
            <div class="callout callout-danger" style="margin-bottom: 0;"><p>{{ $overview['error'] }}</p></div>
        @else
            <div class="pd-px-nodes">
                @foreach ($overview['nodes'] as $node)
                    @php
                        $memP = $percent($node['memory'], $node['max_memory']);
                        $diskP = $percent($node['disk'], $node['max_disk']);
                    @endphp
                    <div class="pd-px-node">
                        <h4><span class="pd-px-dot" style="background: {{ $node['online'] ? '#3ecf8e' : '#e5484d' }}"></span>{{ $node['name'] }}
                            <small style="margin-left: auto; color: rgba(235,235,240,.55); font-weight: 400;">{{ $node['online'] ? $uptime($node['uptime']) : '' }}</small>
                        </h4>
                        @if ($node['online'])
                            <div class="pd-px-row"><span>CPU · {{ $node['cores'] }} <span>cores</span></span><b>{{ $node['cpu'] }} %</b></div>
                            <div class="pd-px-bar {{ $tone($node['cpu']) }}"><i style="width: {{ min(100, $node['cpu']) }}%"></i></div>
                            <div class="pd-px-row"><span>Memory</span><b>{{ $bytes($node['memory']) }} / {{ $bytes($node['max_memory']) }}</b></div>
                            <div class="pd-px-bar {{ $tone($memP) }}"><i style="width: {{ $memP }}%"></i></div>
                            <div class="pd-px-row"><span>System disk</span><b>{{ $bytes($node['disk']) }} / {{ $bytes($node['max_disk']) }}</b></div>
                            <div class="pd-px-bar {{ $tone($diskP) }}"><i style="width: {{ $diskP }}%"></i></div>
                            @if (count($node['storage']) > 0)
                                <div class="pd-px-store">
                                    @foreach ($node['storage'] as $store)
                                        @php $storeP = $percent($store['used'], $store['total']); @endphp
                                        <div class="pd-px-row"><span>{{ $store['name'] }} <small>({{ $store['type'] }})</small></span><b>{{ $bytes($store['used']) }} / {{ $bytes($store['total']) }}</b></div>
                                        <div class="pd-px-bar {{ $tone($storeP) }}"><i style="width: {{ $storeP }}%"></i></div>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <p class="pd-px-help"><span>This node is offline.</span></p>
                        @endif
                    </div>
                @endforeach
            </div>

            @php
                $running = collect($overview['vms'])->where('status', 'running')->count();
            @endphp
            <h4 style="font-size: 14px; margin: 0 0 8px;"><span>Virtual machines</span> <small>{{ $running }} / {{ count($overview['vms']) }} <span>running</span></small></h4>
            @if (count($overview['vms']) === 0)
                <p class="pd-px-help"><span>No virtual machine on this host yet.</span></p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover pd-px-table">
                        <thead><tr><th>ID</th><th>Name</th><th>Node</th><th>State</th><th>CPU</th><th>Memory</th><th>VPS of</th></tr></thead>
                        <tbody>
                            @foreach ($overview['vms'] as $vm)
                                @php $owner = $assigned->get($vm['vmid']); @endphp
                                <tr>
                                    <td><code>{{ $vm['vmid'] }}</code></td>
                                    <td>{{ $vm['name'] }}</td>
                                    <td>{{ $vm['node'] }}</td>
                                    <td><span class="pd-px-dot" style="background: {{ $vm['status'] === 'running' ? '#3ecf8e' : '#707070' }}"></span> <span>{{ $vm['status'] === 'running' ? 'Running' : 'Stopped' }}</span></td>
                                    <td>{{ $vm['status'] === 'running' ? $vm['cpu'] . ' %' : '—' }}</td>
                                    <td>{{ $vm['status'] === 'running' ? $bytes($vm['memory']) . ' / ' : '' }}{{ $bytes($vm['max_memory']) }}</td>
                                    <td>
                                        @if ($owner)
                                            <a href="{{ route('admin.users.view', $owner->user_id) }}">{{ $owner->user->username ?? '?' }}</a>
                                            <small class="text-muted">({{ $owner->name }})</small>
                                            <form method="POST" action="{{ route('admin.proxmox.unassign', $owner->id) }}" style="display: inline;" onsubmit="return confirm('Take this VPS back from its user? The machine itself is not touched.');">
                                                {!! csrf_field() !!}
                                                <button type="submit" class="btn btn-xs btn-default" title="Take back"><i class="fa fa-times"></i></button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.proxmox.assign', $host->id) }}" class="pd-px-assign">
                                                {!! csrf_field() !!}
                                                <input type="hidden" name="vmid" value="{{ $vm['vmid'] }}">
                                                <input type="hidden" name="node" value="{{ $vm['node'] }}">
                                                <input type="text" name="user" class="form-control input-sm" placeholder="Email or username" required>
                                                <button type="submit" class="btn btn-xs btn-primary"><span>Give</span></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- The VPS made or given by the panel on this host, with where their creation is. --}}
            @if ($panelVms->count() > 0)
                <h4 style="font-size: 14px; margin: 22px 0 8px;"><span>VPS of the panel</span></h4>
                <div class="table-responsive">
                    <table class="table table-hover pd-px-table">
                        <thead><tr><th>Name</th><th>User</th><th>IP</th><th>State</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($panelVms as $pvm)
                                <tr>
                                    <td>{{ $pvm->name }} <small class="text-muted">#{{ $pvm->vmid }}</small></td>
                                    <td><a href="{{ route('admin.users.view', $pvm->user_id) }}">{{ $pvm->user->username ?? '?' }}</a></td>
                                    <td>{!! $pvm->ip ? '<code>' . e($pvm->ip->address) . '</code>' : '<span class="text-muted">DHCP</span>' !!}</td>
                                    <td>
                                        @if ($pvm->state === 'ready')
                                            <span class="pd-px-dot" style="background: #3ecf8e"></span> <span>Ready</span>
                                        @elseif ($pvm->state === 'failed')
                                            <span class="pd-px-dot" style="background: #e5484d"></span> <span>Failed</span>
                                            <span class="pd-px-error">{{ $pvm->error }}</span>
                                        @elseif (in_array($pvm->state, ['stop', 'destroy'], true))
                                            <i class="fa fa-spinner fa-spin"></i> <span>Deleting</span>
                                        @else
                                            <i class="fa fa-spinner fa-spin"></i> <span>{{ ['clone' => 'Copying the template', 'configure' => 'Setting up', 'resize' => 'Growing the disk', 'start' => 'Starting'][$pvm->state] ?? 'Creating' }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($pvm->state === 'failed')
                                            <form method="POST" action="{{ route('admin.proxmox.retry', $pvm->id) }}" style="display: inline;">
                                                {!! csrf_field() !!}
                                                <button type="submit" class="btn btn-xs btn-default"><i class="fa fa-refresh"></i> <span>Try again</span></button>
                                            </form>
                                        @endif
                                        @if (!in_array($pvm->state, ['stop', 'destroy'], true))
                                            <form method="POST" action="{{ route('admin.proxmox.destroy', $pvm->id) }}" style="display: inline;" onsubmit="return confirm('Delete this VPS for good? The machine and its disks are destroyed on Proxmox. This cannot be undone.');">
                                                {!! csrf_field() !!}
                                                <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> <span>Delete</span></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="row" style="margin-top: 18px;">
                {{-- Creating a VPS: a copy of a template prepared with cloud-init. --}}
                <div class="col-md-7">
                    <h4 style="font-size: 14px; margin: 0 0 10px;"><span>Create a VPS</span></h4>
                    @if (count($overview['templates']) === 0)
                        <div class="callout callout-info">
                            <p><span>No template yet. A template is a machine prepared with a cloud-init drive, then turned into a template. For Debian 12, on the Proxmox shell:</span></p>
                            <code class="pd-px-cmd">wget https://cloud.debian.org/images/cloud/bookworm/latest/debian-12-genericcloud-amd64.qcow2
qm create 9000 --name debian-12 --memory 1024 --net0 virtio,bridge=vmbr0 --scsihw virtio-scsi-pci --agent 1
qm importdisk 9000 debian-12-genericcloud-amd64.qcow2 local-lvm
qm set 9000 --scsi0 local-lvm:vm-9000-disk-0 --ide2 local-lvm:cloudinit --boot order=scsi0 --serial0 socket --vga serial0
qm template 9000</code>
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.proxmox.create', $host->id) }}" autocomplete="off">
                            {!! csrf_field() !!}
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label>User</label>
                                    <input type="text" name="user" class="form-control input-sm" required placeholder="Email or username" value="{{ old('user') }}">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label>Name</label>
                                    <input type="text" name="name" class="form-control input-sm" required maxlength="63" placeholder="vps-alex" value="{{ old('name') }}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>System</label>
                                <select name="template" class="form-control input-sm" required>
                                    @foreach ($overview['templates'] as $template)
                                        <option value="{{ $template['vmid'] }}" @selected((int) old('template') === $template['vmid'])>{{ $template['name'] }} ({{ $template['node'] }} · #{{ $template['vmid'] }}{{ $template['disk'] ? ' · ' . $bytes($template['disk']) : '' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row">
                                <div class="form-group col-xs-4">
                                    <label>vCPU</label>
                                    <input type="number" name="cores" class="form-control input-sm" min="1" max="64" required value="{{ old('cores', 2) }}">
                                </div>
                                <div class="form-group col-xs-4">
                                    <label><span>Memory</span> (MB)</label>
                                    <input type="number" name="memory" class="form-control input-sm" min="256" step="256" required value="{{ old('memory', 2048) }}">
                                </div>
                                <div class="form-group col-xs-4">
                                    <label><span>Disk</span> (GB)</label>
                                    <input type="number" name="disk" class="form-control input-sm" min="1" required value="{{ old('disk', 20) }}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>IP</label>
                                <select name="ip" class="form-control input-sm">
                                    @foreach ($ips->whereNull('vm_id') as $freeIp)
                                        <option value="{{ $freeIp->id }}" @selected((int) old('ip') === $freeIp->id)>{{ $freeIp->address }}/{{ $freeIp->cidr }}</option>
                                    @endforeach
                                    <option value="" @selected(old('ip') === '')>DHCP</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><span>SSH key</span> <small class="text-muted">(<span>optional</span>)</small></label>
                                <textarea name="ssh_key" class="form-control input-sm" rows="2" placeholder="ssh-ed25519 AAAA...">{{ old('ssh_key') }}</textarea>
                            </div>
                            <p class="pd-px-help"><span>A root password is made and shown to the user in their panel.</span></p>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> <span>Create the VPS</span></button>
                        </form>
                    @endif
                </div>

                {{-- The public addresses the new VPS can take. --}}
                <div class="col-md-5">
                    <h4 style="font-size: 14px; margin: 0 0 10px;"><span>IP addresses</span> <small>{{ $ips->whereNull('vm_id')->count() }} <span>free</span></small></h4>
                    @if ($ips->count() > 0)
                        <table class="table table-condensed" style="margin-bottom: 12px;">
                            <tbody>
                                @foreach ($ips as $poolIp)
                                    <tr>
                                        <td><code>{{ $poolIp->address }}/{{ $poolIp->cidr }}</code>@if ($poolIp->mac)<br><small class="text-muted">{{ $poolIp->mac }}</small>@endif</td>
                                        <td><small class="text-muted">gw {{ $poolIp->gateway }}</small></td>
                                        <td class="text-right">
                                            @if ($poolIp->vm)
                                                <small>{{ $poolIp->vm->name }}</small>
                                            @else
                                                <form method="POST" action="{{ route('admin.proxmox.ips.delete', $poolIp->id) }}" style="display: inline;">
                                                    {!! csrf_field() !!}
                                                    <button type="submit" class="btn btn-xs btn-default" title="Remove"><i class="fa fa-times"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    <form method="POST" action="{{ route('admin.proxmox.ips.add', $host->id) }}" autocomplete="off">
                        {!! csrf_field() !!}
                        <div class="form-group">
                            <label>Add addresses</label>
                            <textarea name="addresses" class="form-control input-sm" rows="3" required placeholder="203.0.113.20&#10;203.0.113.21&#10;203.0.113.22 02:00:00:aa:bb:cc"></textarea>
                            <p class="pd-px-help"><span>One per line. Add the virtual MAC address after the IP when your host gave one for it.</span></p>
                        </div>
                        <div class="row">
                            <div class="form-group col-xs-5">
                                <label>Mask</label>
                                <select name="cidr" class="form-control input-sm">
                                    @foreach ([32, 30, 29, 28, 27, 26, 25, 24] as $mask)
                                        <option value="{{ $mask }}">/{{ $mask }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-xs-7">
                                <label>Gateway</label>
                                <input type="text" name="gateway" class="form-control input-sm" required placeholder="203.0.113.1">
                            </div>
                        </div>
                        <p class="pd-px-help"><span>Your host tells you the gateway of its additional IPs, and whether each one needs a virtual MAC address.</span></p>
                        <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-plus"></i> <span>Add</span></button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
