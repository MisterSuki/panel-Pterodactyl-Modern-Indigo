@extends('layouts.admin')

@section('title')
    List Servers
@endsection

@section('content-header')
    <h1>Servers<small>All servers available on the system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Servers</li>
    </ol>
@endsection

@section('content')
@php($canManage = Auth::user()->hasAdminPermission('servers.manage'))
<div class="pd-srv" data-pd-servers data-live="{{ route('admin.servers.live') }}" data-power="{{ route('admin.servers.power') }}" data-suspension="{{ route('admin.servers.suspension') }}">
    <div class="pd-srv-top">
        <form action="{{ route('admin.servers') }}" method="GET" class="pd-srv-search">
            <i class="fa fa-search"></i>
            <input type="text" name="filter[*]" value="{{ request()->input()['filter']['*'] ?? '' }}" placeholder="Search a server, an owner, an email or a UUID">
        </form>
        <div class="pd-srv-chips" data-pd-filter>
            <button type="button" class="is-active" data-state="all"><span>All</span> <b data-count="all">{{ $servers->count() }}</b></button>
            <button type="button" data-state="running"><i class="pd-dot is-running"></i> <span>Running</span> <b data-count="running">–</b></button>
            <button type="button" data-state="offline"><i class="pd-dot is-offline"></i> <span>Offline</span> <b data-count="offline">–</b></button>
            <button type="button" data-state="suspended"><i class="pd-dot is-suspended"></i> <span>Suspended</span> <b data-count="suspended">–</b></button>
        </div>
        <a href="{{ route('admin.servers.new') }}" class="btn btn-primary btn-sm pd-srv-new"><i class="fa fa-plus"></i> <span>Create New</span></a>
    </div>

    @if($canManage)
        <div class="pd-srv-bulk" data-pd-bulk hidden>
            <span><b data-pd-selected>0</b> <span>selected</span></span>
            <button type="button" class="btn btn-sm btn-success" data-bulk-power="start"><i class="fa fa-play"></i> <span>Start</span></button>
            <button type="button" class="btn btn-sm btn-primary" data-bulk-power="restart"><i class="fa fa-refresh"></i> <span>Restart</span></button>
            <button type="button" class="btn btn-sm btn-default" data-bulk-power="stop"><i class="fa fa-stop"></i> <span>Stop</span></button>
            <button type="button" class="btn btn-sm btn-warning" data-bulk-suspend="suspend"><i class="fa fa-ban"></i> <span>Suspend</span></button>
            <button type="button" class="btn btn-sm btn-default" data-bulk-suspend="unsuspend"><i class="fa fa-check"></i> <span>Unsuspend</span></button>
            <button type="button" class="btn btn-sm btn-link" data-bulk-clear><span>Clear</span></button>
        </div>
    @endif

    <div class="box pd-srv-box">
        <div class="box-body table-responsive no-padding">
            <table class="table pd-srv-table">
                <thead>
                    <tr>
                        @if($canManage)<th class="pd-srv-check"><input type="checkbox" data-pd-all aria-label="Select all"></th>@endif
                        <th>Server Name</th>
                        <th>Status</th>
                        <th class="hidden-xs">Use</th>
                        <th class="hidden-xs">Owner</th>
                        <th class="hidden-sm hidden-xs">Node</th>
                        <th class="hidden-sm hidden-xs">Connection</th>
                        <th class="text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($servers as $server)
                        @php($suspension = $server->isSuspended() ? ($suspensions[$server->id] ?? null) : null)
                        <tr data-server-id="{{ $server->id }}" data-state="{{ $server->isSuspended() ? 'suspended' : ($server->isInstalled() ? 'unknown' : 'installing') }}">
                            @if($canManage)<td class="pd-srv-check"><input type="checkbox" value="{{ $server->id }}" aria-label="Select"></td>@endif
                            <td>
                                <a href="{{ route('admin.servers.view', $server->id) }}" class="pd-srv-name">{{ $server->name }}</a>
                                <span class="pd-srv-sub">{{ $server->egg->name ?? '' }} · <code title="{{ $server->uuid }}">{{ $server->uuidShort }}</code></span>
                            </td>
                            <td>
                                <span class="pd-state" data-pd-state
                                    @if($suspension) data-toggle="tooltip" data-html="true" title="{{ e($suspension->reason ?: 'No reason given') }}@if($suspension->suspended_until)<br>Until {{ $suspension->suspended_until->format('M j, H:i') }}@endif" @endif>
                                    @if($server->isSuspended())
                                        <i class="pd-dot is-suspended"></i> <span>Suspended</span>
                                    @elseif(! $server->isInstalled())
                                        <i class="pd-dot is-starting"></i> <span>Installing</span>
                                    @else
                                        <i class="pd-dot"></i> <span>…</span>
                                    @endif
                                </span>
                                <span class="pd-srv-sub" data-pd-uptime></span>
                            </td>
                            <td class="hidden-xs">
                                <div class="pd-use" data-pd-use hidden>
                                    <div><span>CPU</span><i><b data-pd-cpu-bar></b></i><em data-pd-cpu></em></div>
                                    <div><span>RAM</span><i><b data-pd-ram-bar></b></i><em data-pd-ram></em></div>
                                </div>
                            </td>
                            <td class="hidden-xs"><a href="{{ route('admin.users.view', $server->user->id) }}">{{ $server->user->username }}</a></td>
                            <td class="hidden-sm hidden-xs"><a href="{{ route('admin.nodes.view', $server->node->id) }}">{{ $server->node->name }}</a></td>
                            <td class="hidden-sm hidden-xs"><code>{{ $server->allocation->alias }}:{{ $server->allocation->port }}</code></td>
                            <td class="text-right pd-srv-actions">
                                @if($canManage)
                                    <button type="button" class="pd-icon-btn is-start" data-power="start" title="Start" hidden><i class="fa fa-play"></i></button>
                                    <button type="button" class="pd-icon-btn" data-power="restart" title="Restart" hidden><i class="fa fa-refresh"></i></button>
                                    <button type="button" class="pd-icon-btn is-stop" data-power="stop" title="Stop" hidden><i class="fa fa-stop"></i></button>
                                @endif
                                <a class="pd-icon-btn" href="/server/{{ $server->uuidShort }}" title="Open in the panel"><i class="fa fa-external-link"></i></a>
                                <a class="pd-icon-btn" href="{{ route('admin.servers.view', $server->id) }}" title="Manage"><i class="fa fa-wrench"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center pd-srv-empty">No server matches your search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($servers->hasPages())
            <div class="box-footer with-border">
                <div class="col-md-12 text-center">{!! $servers->appends(['filter' => Request::input('filter')])->render() !!}</div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    (function () {
        var root = document.querySelector('[data-pd-servers]');
        if (!root) return;
        var rows = Array.prototype.slice.call(root.querySelectorAll('tr[data-server-id]'));
        var token = document.querySelector('meta[name="_token"]').getAttribute('content');
        var filter = 'all';
        var LABELS = { running: 'Running', starting: 'Starting', stopping: 'Stopping', offline: 'Offline', suspended: 'Suspended', installing: 'Installing', unreachable: 'Node not answering', unknown: 'Unknown' };

        function bytes(n) {
            if (n >= 1073741824) return (n / 1073741824).toFixed(1) + ' GiB';
            return Math.round(n / 1048576) + ' MiB';
        }
        function uptime(ms) {
            var m = Math.floor(ms / 60000), h = Math.floor(m / 60);
            if (m < 1) return '< 1 min';
            if (m < 60) return m + ' min';
            return h < 48 ? h + ' h ' + (m % 60) + ' min' : Math.floor(h / 24) + ' d ' + (h % 24) + ' h';
        }
        function bar(el, ratio) {
            var r = Math.max(0, Math.min(1, ratio || 0));
            el.style.width = Math.max(2, r * 100) + '%';
            el.className = r >= 0.9 ? 'is-high' : r >= 0.75 ? 'is-warn' : '';
        }
        function bucket(state) {
            return state === 'running' || state === 'starting' || state === 'stopping' ? 'running' : state === 'suspended' ? 'suspended' : 'offline';
        }

        function paint(row, info) {
            var state = info.state;
            row.setAttribute('data-state', state);
            var badge = row.querySelector('[data-pd-state]');
            if (!badge.hasAttribute('data-toggle')) {
                badge.innerHTML = '<i class="pd-dot is-' + state + '"></i> <span>' + (LABELS[state] || state) + '</span>';
            }
            row.querySelector('[data-pd-uptime]').textContent = state === 'running' && info.uptime ? uptime(info.uptime) : '';
            var use = row.querySelector('[data-pd-use]');
            var live = state === 'running' || state === 'starting' || state === 'stopping';
            use.hidden = !live;
            if (live) {
                var cpuLimit = info.cpu_limit > 0 ? info.cpu_limit : 100;
                row.querySelector('[data-pd-cpu]').textContent = info.cpu + ' %';
                bar(row.querySelector('[data-pd-cpu-bar]'), info.cpu / cpuLimit);
                row.querySelector('[data-pd-ram]').textContent = bytes(info.memory) + (info.memory_limit ? ' / ' + bytes(info.memory_limit) : '');
                bar(row.querySelector('[data-pd-ram-bar]'), info.memory_limit ? info.memory / info.memory_limit : 0);
            }
            var off = state === 'offline';
            row.querySelectorAll('[data-power]').forEach(function (b) {
                var p = b.getAttribute('data-power');
                b.hidden = state === 'suspended' || state === 'installing' || state === 'unreachable' || state === 'unknown' || (p === 'start' ? !off : off);
            });
        }

        function counts() {
            var c = { all: rows.length, running: 0, offline: 0, suspended: 0 };
            rows.forEach(function (r) { c[bucket(r.getAttribute('data-state'))]++; });
            Object.keys(c).forEach(function (k) {
                var el = root.querySelector('[data-count="' + k + '"]');
                if (el) el.textContent = c[k];
            });
        }
        function applyFilter() {
            rows.forEach(function (r) { r.hidden = filter !== 'all' && bucket(r.getAttribute('data-state')) !== filter; });
        }

        function refresh() {
            if (document.hidden || !rows.length) return;
            var ids = rows.map(function (r) { return r.getAttribute('data-server-id'); }).join(',');
            fetch(root.getAttribute('data-live') + '?ids=' + ids, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    rows.forEach(function (row) {
                        var info = data.servers[row.getAttribute('data-server-id')];
                        if (info) paint(row, info);
                    });
                    counts();
                    applyFilter();
                })
                .catch(function () {});
        }

        function post(url, body) {
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(body),
            }).then(function (r) { return r.json(); });
        }
        function report(result) {
            var text = result.done + ' server(s) done.';
            if (result.errors && result.errors.length) text += '\n' + result.errors.join('\n');
            if (window.$ && $.notify) {
                $.notify({ message: text.replace(/\n/g, '<br>') }, { type: result.errors && result.errors.length ? 'warning' : 'success' });
            }
            setTimeout(refresh, 1500);
        }

        root.querySelectorAll('[data-pd-filter] button').forEach(function (b) {
            b.addEventListener('click', function () {
                filter = b.getAttribute('data-state');
                root.querySelectorAll('[data-pd-filter] button').forEach(function (x) { x.classList.toggle('is-active', x === b); });
                applyFilter();
            });
        });

        rows.forEach(function (row) {
            row.querySelectorAll('[data-power]').forEach(function (b) {
                b.addEventListener('click', function () {
                    b.disabled = true;
                    post(root.getAttribute('data-power'), { ids: [Number(row.getAttribute('data-server-id'))], signal: b.getAttribute('data-power') })
                        .then(report).catch(function () {}).then(function () { b.disabled = false; });
                });
            });
        });

        // Several servers at once.
        var bulk = root.querySelector('[data-pd-bulk]');
        var all = root.querySelector('[data-pd-all]');
        function selected() {
            return Array.prototype.slice.call(root.querySelectorAll('tbody input[type=checkbox]:checked')).map(function (c) { return Number(c.value); });
        }
        function updateBulk() {
            if (!bulk) return;
            var ids = selected();
            bulk.hidden = ids.length === 0;
            bulk.querySelector('[data-pd-selected]').textContent = ids.length;
        }
        if (all) {
            all.addEventListener('change', function () {
                rows.forEach(function (r) {
                    var c = r.querySelector('input[type=checkbox]');
                    if (c && !r.hidden) c.checked = all.checked;
                });
                updateBulk();
            });
        }
        root.querySelectorAll('tbody input[type=checkbox]').forEach(function (c) { c.addEventListener('change', updateBulk); });
        if (bulk) {
            bulk.querySelectorAll('[data-bulk-power]').forEach(function (b) {
                b.addEventListener('click', function () {
                    post(root.getAttribute('data-power'), { ids: selected(), signal: b.getAttribute('data-bulk-power') }).then(report);
                });
            });
            bulk.querySelectorAll('[data-bulk-suspend]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var action = b.getAttribute('data-bulk-suspend');
                    var reason = action === 'suspend' ? window.prompt('Reason of the suspension (optional)', '') : '';
                    if (reason === null) return;
                    post(root.getAttribute('data-suspension'), { ids: selected(), action: action, reason: reason || null })
                        .then(function (result) { report(result); setTimeout(function () { window.location.reload(); }, 1200); });
                });
            });
            bulk.querySelector('[data-bulk-clear]').addEventListener('click', function () {
                root.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; });
                updateBulk();
            });
        }

        refresh();
        setInterval(refresh, 5000);
    })();
    </script>
@endsection
