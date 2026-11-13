@extends('layouts.admin')

@section('title')
    Proxmox
@endsection

@section('content-header')
    <h1>Proxmox<small>The virtual machines of Proxmox, given to users as VPS.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Proxmox</li>
    </ol>
@endsection

@section('content')
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
    $form = $editing;
@endphp
<style>
    .pd-px-nodes { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; margin-bottom: 18px; }
    .pd-px-node { border: 1px solid var(--pd-border, #1f1f1f); border-radius: 12px; padding: 14px; background: rgba(255, 255, 255, 0.02); }
    .pd-px-node h4 { margin: 0 0 10px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
    .pd-px-row { display: flex; justify-content: space-between; font-size: 12px; color: rgba(235, 235, 240, 0.7); margin-top: 8px; }
    .pd-px-row b { color: #ededed; font-weight: 500; font-variant-numeric: tabular-nums; }
    .pd-px-bar { height: 4px; border-radius: 4px; background: rgba(255, 255, 255, 0.08); overflow: hidden; margin-top: 4px; }
    .pd-px-bar i { display: block; height: 100%; background: rgb(var(--pd-accent-500, 10 132 255)); }
    .pd-px-bar.is-orange i { background: #f5a524; }
    .pd-px-bar.is-red i { background: #e5484d; }
    .pd-px-store { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--pd-border, #1f1f1f); }
    .pd-px-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 6px 0 0; }
    .pd-px-assign { display: flex; gap: 6px; }
    .pd-px-assign input { max-width: 170px; height: 30px; padding: 4px 8px; }
    .pd-px-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .pd-px-table td { vertical-align: middle !important; white-space: nowrap; }
    .pd-px-steps { margin: 6px 0 0; padding-left: 20px; }
    .pd-px-steps li { margin-bottom: 6px; }
</style>

<div class="row">
    <div class="col-lg-8">
        @forelse ($hosts as $entry)
            @php
                $host = $entry['host'];
                $overview = $entry['overview'];
                $assigned = $entry['assigned'];
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
                    @endif
                </div>
            </div>
        @empty
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Connect Proxmox</h3></div>
                <div class="box-body">
                    <p><span>The panel talks to Proxmox with an API token. On Proxmox:</span></p>
                    <ol class="pd-px-steps">
                        <li><span>Datacenter → Permissions → API Tokens → Add. Pick the user root@pam (or a dedicated user), give the token an ID such as "panel", and untick "Privilege Separation".</span></li>
                        <li><span>Copy the Token ID (root@pam!panel) and the Secret: Proxmox shows the secret only once.</span></li>
                        <li><span>With a dedicated user, give it the roles PVEVMUser and PVEAuditor on "/" (Datacenter → Permissions → Add).</span></li>
                        <li><span>Fill in the form on the right with the address of Proxmox (https://IP:8006), the Token ID and the Secret.</span></li>
                    </ol>
                </div>
            </div>
        @endforelse
        @if (count($hosts) > 0)
            <p class="text-right"><a href="{{ route('admin.proxmox', ['refresh' => 1]) }}" class="btn btn-sm btn-default"><i class="fa fa-refresh"></i> <span>Refresh</span></a></p>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">{{ $form ? 'Edit ' . $form->name : 'Add a Proxmox host' }}</h3></div>
            <form method="POST" action="{{ $form ? route('admin.proxmox.hosts.update', $form->id) : route('admin.proxmox.hosts.store') }}" autocomplete="off">
                {!! csrf_field() !!}
                <div class="box-body">
                    <div class="form-group">
                        <label for="pxName">Name</label>
                        <input type="text" id="pxName" name="name" class="form-control" maxlength="80" required value="{{ old('name', $form->name ?? '') }}" placeholder="Proxmox 1">
                    </div>
                    <div class="form-group">
                        <label for="pxUrl">Address</label>
                        <input type="text" id="pxUrl" name="url" class="form-control" maxlength="191" required value="{{ old('url', $form->url ?? '') }}" placeholder="https://203.0.113.10:8006">
                        <p class="pd-px-help"><span>The address of the web interface of Proxmox. The panel must be able to reach port 8006.</span></p>
                    </div>
                    <div class="form-group">
                        <label for="pxTokenId">Token ID</label>
                        <input type="text" id="pxTokenId" name="token_id" class="form-control" maxlength="191" required value="{{ old('token_id', $form->token_id ?? '') }}" placeholder="root@pam!panel">
                    </div>
                    <div class="form-group">
                        <label for="pxSecret">Secret</label>
                        <input type="password" id="pxSecret" name="token_secret" class="form-control" maxlength="191" autocomplete="new-password" @if (!$form) required @endif placeholder="{{ $form ? '••••••••' : 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' }}">
                        <p class="pd-px-help"><span>{{ $form ? 'Leave empty to keep the current secret.' : 'Kept encrypted with the key of the panel.' }}</span></p>
                    </div>
                    <label class="pd-switch"><input type="checkbox" name="verify_tls" value="1" @checked(old('verify_tls', $form->verify_tls ?? false))><i class="pd-switch-track"></i><span>Check the certificate</span></label>
                    <p class="pd-px-help"><span>Leave off with the self-signed certificate Proxmox installs by default.</span></p>
                </div>
                <div class="box-footer">
                    @if ($form)<a href="{{ route('admin.proxmox') }}" class="btn btn-sm btn-default"><span>Cancel</span></a>@endif
                    <button type="submit" class="btn btn-sm btn-primary pull-right"><i class="fa fa-save"></i> <span>{{ $form ? 'Save' : 'Add and test' }}</span></button>
                </div>
            </form>
        </div>
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">How it works</h3></div>
            <div class="box-body">
                <p class="pd-px-help" style="margin-top: 0;"><span>Create the virtual machines on Proxmox as usual, then give each one to a user with the "Give" button. The user sees it in the panel, under VPS: its state, its CPU, memory and network, its IP addresses, and buttons to start, shut down, restart and stop it.</span></p>
                <p class="pd-px-help"><span>The IP addresses come from the QEMU guest agent: install qemu-guest-agent in the machine and tick "QEMU Guest Agent" in its Options on Proxmox.</span></p>
            </div>
        </div>
    </div>
</div>
@endsection
