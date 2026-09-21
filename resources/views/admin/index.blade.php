@extends('layouts.admin')

@section('title')
    Administration
@endsection

@section('content-header')
    <h1>Administrative Overview<small>A quick glance at your system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Index</li>
    </ol>
@endsection

@section('content')
@php
    $adminUser = Auth::user();
    $upToDate = $version->isLatestPanel();
    $counts = $overview['counts'];
    // A memory or disk size in MiB, as GB.
    $gb = fn ($mb) => rtrim(rtrim(number_format($mb / 1024, 1), '0'), '.');
    // The colour of a meter: calm until three quarters full, then amber, then red.
    $level = fn ($percent) => $percent === null ? 'ok' : ($percent >= 90 ? 'bad' : ($percent >= 75 ? 'warn' : 'ok'));
    $actions = array_values(array_filter([
        $adminUser->hasAdminPermission('servers.manage') ? ['route' => route('admin.servers.new'), 'icon' => 'fa-server', 'label' => 'Create a server'] : null,
        $adminUser->hasAdminPermission('users.manage') ? ['route' => route('admin.users.new'), 'icon' => 'fa-user-plus', 'label' => 'Create a user'] : null,
        $adminUser->hasAdminPermission('nodes.manage') ? ['route' => route('admin.nodes.new'), 'icon' => 'fa-sitemap', 'label' => 'Add a node'] : null,
        $adminUser->hasAdminPermission('settings.manage') ? ['route' => route('admin.settings'), 'icon' => 'fa-wrench', 'label' => 'Panel settings'] : null,
        $adminUser->root_admin ? ['route' => route('admin.api.index'), 'icon' => 'fa-gamepad', 'label' => 'Application API'] : null,
    ]));
@endphp

<div class="pd-hero">
    <div>
        <h2><span>Welcome back,</span> {{ $adminUser->name_first ?: $adminUser->username }}</h2>
        <p>Here is what is happening on your panel.</p>
    </div>
    <div class="pd-version {{ $upToDate ? 'pd-version--ok' : 'pd-version--warn' }}">
        <i class="fa {{ $upToDate ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i>
        <div>
            <strong><span>Pterodactyl</span> {{ config('app.version') }}</strong>
            @if ($upToDate)
                <small>Up to date</small>
            @else
                <small><a href="https://github.com/Pterodactyl/Panel/releases/v{{ $version->getPanel() }}" target="_blank" rel="noopener"><span>Update available:</span> {{ $version->getPanel() }}</a></small>
            @endif
        </div>
    </div>
</div>

@if (!empty($counts))
<div class="row pd-tiles">
    @isset($counts['servers'])
        <div class="col-xs-6 col-md-3">
            <a class="pd-tile" href="{{ route('admin.servers') }}">
                <span class="pd-tile-icon pd-tile-icon--indigo"><i class="fa fa-server"></i></span>
                <span class="pd-tile-body">
                    <small>Servers</small>
                    <strong>{{ $counts['servers']['total'] }}</strong>
                    @if ($counts['servers']['attention'] > 0)
                        <em class="pd-warn">{{ $counts['servers']['attention'] }} <span>need attention</span></em>
                    @elseif ($counts['servers']['suspended'] > 0)
                        <em>{{ $counts['servers']['suspended'] }} <span>suspended</span></em>
                    @else
                        <em><span>All running normally</span></em>
                    @endif
                </span>
            </a>
        </div>
    @endisset
    @isset($counts['users'])
        <div class="col-xs-6 col-md-3">
            <a class="pd-tile" href="{{ route('admin.users') }}">
                <span class="pd-tile-icon pd-tile-icon--cyan"><i class="fa fa-users"></i></span>
                <span class="pd-tile-body">
                    <small>Users</small>
                    <strong>{{ $counts['users']['total'] }}</strong>
                    <em>{{ $counts['users']['admins'] }} <span>administrators</span></em>
                </span>
            </a>
        </div>
    @endisset
    @isset($counts['nodes'])
        <div class="col-xs-6 col-md-3">
            <a class="pd-tile" href="{{ route('admin.nodes') }}">
                <span class="pd-tile-icon pd-tile-icon--green"><i class="fa fa-sitemap"></i></span>
                <span class="pd-tile-body">
                    <small>Nodes</small>
                    <strong>{{ $counts['nodes']['total'] }}</strong>
                    @if ($counts['nodes']['maintenance'] > 0)
                        <em class="pd-warn">{{ $counts['nodes']['maintenance'] }} <span>in maintenance</span></em>
                    @else
                        <em><span>All available</span></em>
                    @endif
                </span>
            </a>
        </div>
    @endisset
    @isset($counts['locations'])
        <div class="col-xs-6 col-md-3">
            <a class="pd-tile" href="{{ route('admin.locations') }}">
                <span class="pd-tile-icon pd-tile-icon--violet"><i class="fa fa-globe"></i></span>
                <span class="pd-tile-body">
                    <small>Locations</small>
                    <strong>{{ $counts['locations']['total'] }}</strong>
                    <em><span>Where your nodes are</span></em>
                </span>
            </a>
        </div>
    @endisset
</div>
@endif

<div class="row">
    @isset($counts['nodes'])
    <div class="col-md-8">
        <div class="pd-card">
            <div class="pd-card-head">
                <h3><i class="fa fa-sitemap"></i> <span>Node capacity</span></h3>
                <a href="{{ route('admin.nodes') }}"><span>All nodes</span> <i class="fa fa-angle-right"></i></a>
            </div>
            @forelse ($overview['nodes'] as $node)
                <a class="pd-node" href="{{ route('admin.nodes.view', $node['id']) }}">
                    <div class="pd-node-name">
                        <strong>{{ $node['name'] }}</strong>
                        <small>
                            @if ($node['location']) {{ $node['location'] }} · @endif{{ $node['servers'] }} <span>servers</span>
                            @if ($node['maintenance']) · <b class="pd-warn"><span>Maintenance</span></b> @endif
                        </small>
                    </div>
                    @foreach (['memory' => 'Memory', 'disk' => 'Disk'] as $key => $label)
                        @php($usage = $node[$key])
                        <div class="pd-node-meter">
                            <div class="pd-meter-line">
                                <span>{{ $label }}</span>
                                <b>{{ $gb($usage['allocated']) }} / {{ $gb($usage['total']) }} GB</b>
                            </div>
                            <div class="pd-meter"><i class="pd-meter--{{ $level($usage['percent']) }}" style="width: {{ min(100, $usage['percent'] ?? 0) }}%"></i></div>
                        </div>
                    @endforeach
                </a>
            @empty
                <p class="pd-empty"><span>No node yet.</span></p>
            @endforelse
            <p class="pd-hint"><span>The bars show what is promised to servers, not what they use right now.</span></p>
        </div>
    </div>
    @endisset
    <div class="{{ isset($counts['nodes']) ? 'col-md-4' : 'col-md-12' }}">
        @isset($counts['users'])
        <div class="pd-card" id="pd-online" data-url="{{ route('admin.presence', [], false) }}" data-user-url="{{ route('admin.users.view', ['user' => '__ID__'], false) }}" data-me="{{ $adminUser->id }}">
            <div class="pd-card-head">
                <h3><i class="fa fa-circle pd-live"></i> <span>Active now</span></h3>
                <span class="pd-pill pd-pill--live" id="pd-online-count">{{ count($overview['online']) }}</span>
            </div>
            <div id="pd-online-list"></div>
            <p class="pd-empty" id="pd-online-empty" style="display:none"><span>Nobody is on the panel right now.</span></p>
        </div>
        @endisset
        @if (!empty($actions))
        <div class="pd-card">
            <div class="pd-card-head"><h3><i class="fa fa-bolt"></i> <span>Quick actions</span></h3></div>
            <div class="pd-actions">
                @foreach ($actions as $action)
                    <a href="{{ $action['route'] }}"><i class="fa {{ $action['icon'] }}"></i> <span>{{ $action['label'] }}</span> <i class="fa fa-angle-right pd-go"></i></a>
                @endforeach
            </div>
        </div>
        @endif
        <div class="pd-card">
            <div class="pd-card-head"><h3><i class="fa fa-life-ring"></i> <span>Help and links</span></h3></div>
            <div class="pd-actions">
                <a href="https://pterodactyl.io" target="_blank" rel="noopener"><i class="fa fa-book"></i> <span>Documentation</span> <i class="fa fa-external-link pd-go"></i></a>
                <a href="https://github.com/MisterSuki/panel-Pterodactyl-Modern-Indigo" target="_blank" rel="noopener"><i class="fa fa-github"></i> <span>This theme on GitHub</span> <i class="fa fa-external-link pd-go"></i></a>
                <a href="https://github.com/pterodactyl/panel" target="_blank" rel="noopener"><i class="fa fa-code-fork"></i> <span>Pterodactyl on GitHub</span> <i class="fa fa-external-link pd-go"></i></a>
            </div>
        </div>
    </div>
</div>

@if (!empty($overview['recent_servers']) || !empty($overview['recent_users']))
<div class="row">
    @if (!empty($overview['recent_servers']))
    <div class="{{ !empty($overview['recent_users']) ? 'col-md-6' : 'col-md-12' }}">
        <div class="pd-card">
            <div class="pd-card-head">
                <h3><i class="fa fa-server"></i> <span>Latest servers</span></h3>
                <a href="{{ route('admin.servers') }}"><span>All servers</span> <i class="fa fa-angle-right"></i></a>
            </div>
            @foreach ($overview['recent_servers'] as $server)
                <a class="pd-row" href="{{ route('admin.servers.view', $server['id']) }}">
                    <span class="pd-row-main">
                        <strong>{{ $server['name'] }}</strong>
                        <small>{{ $server['owner'] }} @if ($server['node']) · {{ $server['node'] }} @endif</small>
                    </span>
                    @if ($server['status'] === 'suspended')
                        <span class="pd-pill pd-pill--bad"><span>Suspended</span></span>
                    @elseif ($server['status'])
                        <span class="pd-pill pd-pill--warn"><span>Installing</span></span>
                    @endif
                    <time title="{{ $server['created_at'] }}">{{ $server['created_at']?->diffForHumans() }}</time>
                </a>
            @endforeach
        </div>
    </div>
    @endif
    @if (!empty($overview['recent_users']))
    <div class="{{ !empty($overview['recent_servers']) ? 'col-md-6' : 'col-md-12' }}">
        <div class="pd-card">
            <div class="pd-card-head">
                <h3><i class="fa fa-users"></i> <span>Latest users</span></h3>
                <a href="{{ route('admin.users') }}"><span>All users</span> <i class="fa fa-angle-right"></i></a>
            </div>
            @foreach ($overview['recent_users'] as $user)
                <a class="pd-row" href="{{ route('admin.users.view', $user['id']) }}">
                    <span class="pd-row-main">
                        <strong>{{ $user['username'] }}</strong>
                        <small>{{ $user['email'] }}</small>
                    </span>
                    @if ($user['admin'])
                        <span class="pd-pill pd-pill--info"><span>Administrator</span></span>
                    @endif
                    @if ($user['discord'])
                        <span class="pd-pill pd-pill--discord"><i class="fa fa-comments"></i> Discord</span>
                    @endif
                    <time title="{{ $user['created_at'] }}">{{ $user['created_at']?->diffForHumans() }}</time>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endif
@endsection

@isset($overview['counts']['users'])
@section('footer-scripts')
    @parent
    <script>
        (function () {
            var card = document.getElementById('pd-online');
            if (!card) { return; }
            var list = document.getElementById('pd-online-list');
            var empty = document.getElementById('pd-online-empty');
            var count = document.getElementById('pd-online-count');
            var me = String(card.getAttribute('data-me'));
            var userUrl = card.getAttribute('data-user-url');
            var people = @json($overview['online']);
            // The pages the dashboard can tell about, by code. The texts are translated with the rest of the page.
            var pages = {
                dashboard: 'Dashboard', console: 'Console', files: 'Files', databases: 'Databases', schedules: 'Schedules',
                users: 'Users', backups: 'Backups', network: 'Network', startup: 'Startup', settings: 'Settings',
                activity: 'Activity', account: 'Account', tickets: 'Support', admin: 'Administration', server: 'Server',
                panel: 'On the panel'
            };
            var fetchedAt = Date.now();

            function el(tag, className, text) {
                var node = document.createElement(tag);
                if (className) { node.className = className; }
                if (text !== undefined) { node.textContent = text; }
                return node;
            }

            // "Just now", "42 s" or "3 min": the number and the unit are apart, so the unit can be translated.
            function age(seconds) {
                var time = el('time', 'pd-age');
                if (seconds < 10) {
                    time.appendChild(el('span', null, 'Just now'));
                } else if (seconds < 60) {
                    time.appendChild(document.createTextNode(seconds + ' '));
                    time.appendChild(el('span', null, 's'));
                } else {
                    time.appendChild(document.createTextNode(Math.floor(seconds / 60) + ' '));
                    time.appendChild(el('span', null, 'min'));
                }
                return time;
            }

            function row(person) {
                var link = el('a', 'pd-row');
                link.href = userUrl.replace('__ID__', encodeURIComponent(person.id));
                var img = el('img', 'pd-avatar');
                img.src = person.avatar;
                img.alt = '';
                link.appendChild(img);

                var main = el('span', 'pd-row-main');
                var name = el('strong', null, person.username);
                main.appendChild(name);
                // Where the person is: the page, and the server if it is a page of a server.
                var where = el('small');
                where.appendChild(el('span', null, pages[person.page] || pages.panel));
                if (person.server) {
                    where.appendChild(document.createTextNode(' \u00b7 ' + person.server));
                }
                main.appendChild(where);
                link.appendChild(main);

                if (String(person.id) === me) {
                    link.appendChild(el('span', 'pd-pill pd-pill--info')).appendChild(el('span', null, 'You'));
                } else if (person.admin) {
                    link.appendChild(el('span', 'pd-pill pd-pill--info')).appendChild(el('span', null, 'Administrator'));
                }
                link.appendChild(age(person.seconds + Math.round((Date.now() - fetchedAt) / 1000)));
                link.setAttribute('data-seconds', person.seconds);

                return link;
            }

            function render() {
                list.textContent = '';
                people.forEach(function (person) { list.appendChild(row(person)); });
                count.textContent = people.length;
                empty.style.display = people.length === 0 ? '' : 'none';
            }

            // Between two answers of the server the ages go on counting.
            function tick() {
                var elapsed = Math.round((Date.now() - fetchedAt) / 1000);
                Array.prototype.forEach.call(list.children, function (link, index) {
                    var seconds = people[index].seconds + elapsed;
                    var current = link.querySelector('.pd-age');
                    var fresh = age(seconds);
                    if (current && current.textContent !== fresh.textContent) { link.replaceChild(fresh, current); }
                });
            }

            function refresh() {
                if (document.hidden) { return; }
                fetch(card.getAttribute('data-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (body) {
                        if (body && Array.isArray(body.data)) {
                            people = body.data;
                            fetchedAt = Date.now();
                            render();
                        }
                    })
                    .catch(function () { /* the list keeps its last state until the next try */ });
            }

            render();
            window.setInterval(refresh, 5000);
            window.setInterval(tick, 1000);
            document.addEventListener('visibilitychange', refresh);
        })();
    </script>
@endsection
@endisset
