@extends('layouts.admin')

@section('title')
    {{ $node->name }}
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>A quick overview of your node.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nodes') }}">Nodes</a></li>
        <li class="active">{{ $node->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li class="active"><a href="{{ route('admin.nodes.view', $node->id) }}">About</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">Settings</a></li>
                <li><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a></li>
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">Servers</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary node-live">
                    <div class="box-header with-border">
                        <h3 class="box-title">Live usage</h3>
                        <div class="box-tools">
                            <span class="node-live-status" data-live="status"><i class="fa fa-circle-o-notch fa-spin"></i> Connecting</span>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="row node-live-tiles">
                            <div class="col-xs-12 col-sm-6">
                                <div class="node-live-tile" data-live-tile="cpu">
                                    <div class="node-live-label">CPU</div>
                                    <div class="node-live-value" data-live="cpu-value">&mdash;</div>
                                    <div class="node-live-sub" data-live="cpu-sub">&nbsp;</div>
                                    <div class="node-live-bar"><span data-live="cpu-bar"></span></div>
                                    <svg class="node-live-spark" viewBox="0 0 100 30" preserveAspectRatio="none" data-live="cpu-spark"></svg>
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-6">
                                <div class="node-live-tile" data-live-tile="memory">
                                    <div class="node-live-label">Memory</div>
                                    <div class="node-live-value" data-live="memory-value">&mdash;</div>
                                    <div class="node-live-sub" data-live="memory-sub">&nbsp;</div>
                                    <div class="node-live-bar"><span data-live="memory-bar"></span></div>
                                    <svg class="node-live-spark" viewBox="0 0 100 30" preserveAspectRatio="none" data-live="memory-spark"></svg>
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-6">
                                <div class="node-live-tile" data-live-tile="disk">
                                    <div class="node-live-label">Disk</div>
                                    <div class="node-live-value" data-live="disk-value">&mdash;</div>
                                    <div class="node-live-sub" data-live="disk-sub">&nbsp;</div>
                                    <div class="node-live-bar"><span data-live="disk-bar"></span></div>
                                    <svg class="node-live-spark" viewBox="0 0 100 30" preserveAspectRatio="none" data-live="disk-spark"></svg>
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-6">
                                <div class="node-live-tile" data-live-tile="network">
                                    <div class="node-live-label">Network</div>
                                    <div class="node-live-value" data-live="net-value">&mdash;</div>
                                    <div class="node-live-sub" data-live="net-sub">&nbsp;</div>
                                    <div class="node-live-bar"><span data-live="net-bar" class="node-live-bar-static"></span></div>
                                    <svg class="node-live-spark" viewBox="0 0 100 30" preserveAspectRatio="none" data-live="net-spark"></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding node-live-top" style="display: none;">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Busiest servers</th>
                                    <th>State</th>
                                    <th class="text-right">CPU</th>
                                    <th class="text-right">Memory</th>
                                </tr>
                            </thead>
                            <tbody data-live="top"></tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <small class="text-muted">Total of everything the servers on this node use right now, refreshed every 3 seconds. It does not include the operating system of the machine itself.</small>
                    </div>
                </div>
            </div>
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Information</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <tr>
                                <td>Daemon Version</td>
                                <td><code data-attr="info-version"><i class="fa fa-refresh fa-fw fa-spin"></i></code> (Latest: <code>{{ $version->getDaemon() }}</code>)</td>
                            </tr>
                            <tr>
                                <td>System Information</td>
                                <td data-attr="info-system"><i class="fa fa-refresh fa-fw fa-spin"></i></td>
                            </tr>
                            <tr>
                                <td>Total CPU Threads</td>
                                <td data-attr="info-cpus"><i class="fa fa-refresh fa-fw fa-spin"></i></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            @if ($node->description)
                <div class="col-xs-12">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            Description
                        </div>
                        <div class="box-body table-responsive">
                            <pre>{{ $node->description }}</pre>
                        </div>
                    </div>
                </div>
            @endif
            <div class="col-xs-12">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">Delete Node</h3>
                    </div>
                    <div class="box-body">
                        <p class="no-margin">Deleting a node is a irreversible action and will immediately remove this node from the panel. There must be no servers associated with this node in order to continue.</p>
                    </div>
                    <div class="box-footer">
                        <form action="{{ route('admin.nodes.view.delete', $node->id) }}" method="POST">
                            {!! csrf_field() !!}
                            {!! method_field('DELETE') !!}
                            <button type="submit" class="btn btn-danger btn-sm pull-right" {{ ($node->servers_count < 1) ?: 'disabled' }}>Yes, Delete This Node</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">At-a-Glance</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    @if($node->maintenance_mode)
                    <div class="col-sm-12">
                        <div class="info-box bg-orange">
                            <span class="info-box-icon"><i class="ion ion-wrench"></i></span>
                            <div class="info-box-content" style="padding: 23px 10px 0;">
                                <span class="info-box-text">This node is under</span>
                                <span class="info-box-number">Maintenance</span>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="col-sm-12">
                        <div class="info-box bg-{{ $stats['disk']['css'] }}">
                            <span class="info-box-icon"><i class="ion ion-ios-folder-outline"></i></span>
                            <div class="info-box-content" style="padding: 15px 10px 0;">
                                <span class="info-box-text">Disk Space Allocated</span>
                                <span class="info-box-number">{{ $stats['disk']['value'] }} / {{ $stats['disk']['max'] }} MiB</span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: {{ $stats['disk']['percent'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <div class="info-box bg-{{ $stats['memory']['css'] }}">
                            <span class="info-box-icon"><i class="ion ion-ios-barcode-outline"></i></span>
                            <div class="info-box-content" style="padding: 15px 10px 0;">
                                <span class="info-box-text">Memory Allocated</span>
                                <span class="info-box-number">{{ $stats['memory']['value'] }} / {{ $stats['memory']['max'] }} MiB</span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: {{ $stats['memory']['percent'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <div class="info-box bg-blue">
                            <span class="info-box-icon"><i class="ion ion-social-buffer-outline"></i></span>
                            <div class="info-box-content" style="padding: 23px 10px 0;">
                                <span class="info-box-text">Total Servers</span>
                                <span class="info-box-number">{{ $node->servers_count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <style>
        .node-live-status { font-size: 12px; color: #8a94ad; margin-right: 10px; }
        .node-live-status.ok { color: #4ade80; }
        .node-live-status.bad { color: #f87171; }
        .node-live-tile { position: relative; overflow: hidden; margin-bottom: 15px; padding: 14px 16px 0; border-radius: 12px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06); }
        .node-live-label { font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #8a94ad; }
        .node-live-value { margin-top: 4px; font-size: 24px; font-weight: 600; line-height: 1.2; font-variant-numeric: tabular-nums; }
        .node-live-sub { min-height: 18px; font-size: 12px; color: #8a94ad; font-variant-numeric: tabular-nums; }
        .node-live-bar { height: 5px; margin: 8px 0 0; border-radius: 3px; background: rgba(255, 255, 255, 0.07); overflow: hidden; }
        .node-live-bar > span { display: block; height: 100%; width: 0; border-radius: 3px; background: #6366f1; transition: width 0.6s ease, background-color 0.3s ease; }
        .node-live-bar > span.warn { background: #f59e0b; }
        .node-live-bar > span.danger { background: #ef4444; }
        .node-live-bar-static { opacity: 0; }
        .node-live-spark { display: block; width: calc(100% + 32px); height: 42px; margin: 6px -16px 0; }
        .node-live-spark path.area { fill: rgba(99, 102, 241, 0.18); }
        .node-live-spark path.line { fill: none; stroke: #818cf8; stroke-width: 1.5; vector-effect: non-scaling-stroke; }
        .node-live-spark path.line.b { stroke: #22d3ee; }
        .node-live-state { display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; background: #6b7280; }
        .node-live-state.running { background: #4ade80; }
        .node-live-state.starting, .node-live-state.stopping { background: #facc15; }
        .node-live-state.offline { background: #f87171; }
    </style>
    <script>
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    var liveCpuThreads = 0;

    (function () {
        var POINTS = 60;
        var history = { cpu: [], memory: [], disk: [], rx: [], tx: [] };
        var previous = null;
        var busy = false;

        function el(name) { return $('[data-live="' + name + '"]'); }

        function bytes(value) {
            var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'], i = 0;
            value = Math.max(0, value);
            while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
            return (i === 0 ? Math.round(value) : value.toFixed(value >= 100 ? 0 : 2)) + ' ' + units[i];
        }

        function mib(value) { return bytes(value * 1024 * 1024); }

        function push(list, value) {
            list.push(value);
            if (list.length > POINTS) { list.shift(); }
        }

        // Draws a line with a soft area under it. "max" is the value at the top of the box.
        function spark(name, series, max) {
            var svg = el(name + '-spark');
            var html = '';
            series.forEach(function (list, index) {
                if (list.length < 2) { return; }
                var top = max || Math.max.apply(null, list.concat([1]));
                var step = 100 / (POINTS - 1);
                var offset = 100 - (list.length - 1) * step;
                var path = list.map(function (v, i) {
                    var y = 28 - Math.min(1, v / top) * 26;
                    return (i === 0 ? 'M' : 'L') + (offset + i * step).toFixed(2) + ' ' + y.toFixed(2);
                }).join(' ');
                if (index === 0) {
                    html += '<path class="area" d="' + path + ' L100 30 L' + offset.toFixed(2) + ' 30 Z"></path>';
                }
                html += '<path class="line' + (index === 1 ? ' b' : '') + '" d="' + path + '"></path>';
            });
            svg.html(html);
        }

        function bar(name, ratio) {
            var percent = Math.max(0, Math.min(100, ratio * 100));
            el(name + '-bar').css('width', percent + '%')
                .toggleClass('warn', percent >= 70 && percent < 90)
                .toggleClass('danger', percent >= 90);
        }

        function render(data) {
            // CPU is reported per thread (100 = one full thread). Show it as a share of the whole machine.
            var cpuMax = liveCpuThreads > 0 ? liveCpuThreads * 100 : 0;
            var cpuRatio = cpuMax ? data.cpu / cpuMax : 0;
            el('cpu-value').text((cpuMax ? cpuRatio * 100 : data.cpu).toFixed(1) + ' %');
            el('cpu-sub').text(cpuMax ? data.cpu.toFixed(0) + ' % of ' + liveCpuThreads + ' threads (' + cpuMax + ' %)' : 'Waiting for the thread count');
            bar('cpu', cpuRatio);
            push(history.cpu, cpuMax ? cpuRatio : Math.min(1, data.cpu / 100));
            spark('cpu', [history.cpu], 1);

            var memoryLimit = data.memory_limit_mib * 1024 * 1024;
            el('memory-value').text(bytes(data.memory_bytes));
            el('memory-sub').text(memoryLimit ? 'of ' + mib(data.memory_limit_mib) + ' on this node' : 'No limit set on this node');
            bar('memory', memoryLimit ? data.memory_bytes / memoryLimit : 0);
            push(history.memory, memoryLimit ? data.memory_bytes / memoryLimit : 0);
            spark('memory', [history.memory], 1);

            var diskLimit = data.disk_limit_mib * 1024 * 1024;
            el('disk-value').text(bytes(data.disk_bytes));
            el('disk-sub').text(diskLimit ? 'of ' + mib(data.disk_limit_mib) + ' on this node' : 'No limit set on this node');
            bar('disk', diskLimit ? data.disk_bytes / diskLimit : 0);
            push(history.disk, diskLimit ? data.disk_bytes / diskLimit : 0);
            spark('disk', [history.disk], 1);

            // The daemon reports running totals, so the speed is the difference between two readings.
            var rx = 0, tx = 0;
            if (previous) {
                var seconds = Math.max(0.5, data.time - previous.time);
                rx = Math.max(0, (data.rx_bytes - previous.rx_bytes) / seconds);
                tx = Math.max(0, (data.tx_bytes - previous.tx_bytes) / seconds);
            }
            previous = data;
            push(history.rx, rx);
            push(history.tx, tx);
            el('net-value').text('\u2193 ' + bytes(rx) + '/s');
            el('net-sub').text('\u2191 ' + bytes(tx) + '/s  \u00b7  ' + data.servers.running + ' of ' + data.servers.total + ' servers running');
            spark('net', [history.rx, history.tx], 0);

            var rows = data.top.map(function (row) {
                var name = escapeHtml(row.name);
                if (row.id) { name = '<a href="/admin/servers/view/' + row.id + '">' + name + '</a>'; }
                return '<tr><td>' + name + '</td>' +
                    '<td><span class="node-live-state ' + escapeHtml(row.state) + '"></span>' + escapeHtml(row.state) + '</td>' +
                    '<td class="text-right">' + row.cpu.toFixed(1) + ' %</td>' +
                    '<td class="text-right">' + bytes(row.memory_bytes) + '</td></tr>';
            });
            el('top').html(rows.join(''));
            $('.node-live-top').toggle(rows.length > 0);

            el('status').removeClass('bad').addClass('ok').html('<i class="fa fa-circle"></i> Live');
        }

        function tick() {
            if (busy || document.hidden) { return; }
            busy = true;
            $.ajax({ method: 'GET', url: '{{ route('admin.nodes.view.utilization', $node->id) }}', timeout: 8000 })
                .done(render)
                .fail(function () {
                    el('status').removeClass('ok').addClass('bad').html('<i class="fa fa-exclamation-circle"></i> Daemon unreachable');
                })
                .always(function () { busy = false; });
        }

        tick();
        setInterval(tick, 3000);
    })();

    (function getInformation() {
        $.ajax({
            method: 'GET',
            url: '/admin/nodes/view/{{ $node->id }}/system-information',
            timeout: 5000,
        }).done(function (data) {
            $('[data-attr="info-version"]').html(escapeHtml(data.version));
            $('[data-attr="info-system"]').html(escapeHtml(data.system.type) + ' (' + escapeHtml(data.system.arch) + ') <code>' + escapeHtml(data.system.release) + '</code>');
            $('[data-attr="info-cpus"]').html(data.system.cpus);
            liveCpuThreads = parseInt(data.system.cpus, 10) || 0;
        }).fail(function (jqXHR) {

        }).always(function() {
            setTimeout(getInformation, 10000);
        });
    })();
    </script>
@endsection
