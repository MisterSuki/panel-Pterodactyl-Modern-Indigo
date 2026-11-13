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
    .pd-px-error { display: block; white-space: normal; font-size: 12px; color: rgba(255, 170, 160, 0.9); max-width: 360px; }
    .pd-px-cmd { display: block; white-space: pre; overflow-x: auto; padding: 10px 12px; margin-top: 8px; font-size: 11.5px; user-select: all; }
</style>

<div class="row">
    <div class="col-lg-8">
        @forelse ($hosts as $entry)
            @if ($entry['overview'] === null)
                {{-- Not asked yet: the page opens at once and this box is filled when Proxmox answers. --}}
                <div class="box box-primary" data-pd-px-load="{{ route('admin.proxmox.hosts.panel', $entry['host']->id) }}">
                    <div class="box-header with-border"><h3 class="box-title">{{ $entry['host']->name }}</h3></div>
                    <div class="box-body">
                        <p class="pd-px-help" style="margin: 0;"><i class="fa fa-spinner fa-spin"></i> <span>Asking Proxmox...</span></p>
                    </div>
                </div>
            @else
                @include('admin.proxmox.host', ['entry' => $entry])
            @endif
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
                <p class="pd-px-help" style="margin-top: 0;"><span>Create a VPS from a template right here, with "Create a VPS": the panel copies the template, sets its cores, memory, disk, address and root password, and starts it. It shows in the panel of the user at once, as "being created".</span></p>
                <p class="pd-px-help"><span>Machines made on Proxmox can also be given to a user with the "Give" button.</span></p>
                <p class="pd-px-help"><span>The user sees it in the panel, under VPS: its state, its CPU, memory and network, its IP addresses, its root password, and buttons to start, shut down, restart and stop it.</span></p>
                <p class="pd-px-help"><span>Other addresses come from the QEMU guest agent: install qemu-guest-agent in the machine and tick "QEMU Guest Agent" in its Options on Proxmox.</span></p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        // Fills the boxes of the hosts that were not asked yet, all at the same time.
        document.querySelectorAll('[data-pd-px-load]').forEach(function (box) {
            fetch(box.getAttribute('data-pd-px-load'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) {
                    if (!response.ok) throw new Error(String(response.status));
                    return response.text();
                })
                .then(function (html) {
                    var holder = document.createElement('div');
                    holder.innerHTML = html;
                    box.replaceWith.apply(box, Array.prototype.slice.call(holder.childNodes));
                })
                .catch(function () {
                    var body = box.querySelector('.box-body');
                    if (body) body.innerHTML = '<div class="callout callout-danger" style="margin: 0;"><p>Proxmox could not be asked. Reload the page.</p></div>';
                });
        });
    </script>
@endsection
