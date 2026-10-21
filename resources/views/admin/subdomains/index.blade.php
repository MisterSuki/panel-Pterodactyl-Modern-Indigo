@extends('layouts.admin')

@section('title')
    Subdomains
@endsection

@section('content-header')
    <h1>Subdomains<small>Domains your users can make subdomains under. Each server's limit is on its Build page.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Subdomains</li>
    </ol>
@endsection

@section('content')
<style>
    .pd-sd-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 6px 0 0; }
    .pd-sd-actions { white-space: nowrap; text-align: right; }
    .pd-sd-actions form { display: inline-block; margin: 0; }
    .pd-sd-actions .btn { margin-left: 4px; }
    .pd-sd-provider { color: rgba(235, 235, 240, 0.6); font-size: 12px; }
    .pd-sd-records .pd-sd-row { position: relative; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 14px 52px 6px 14px; margin-bottom: 12px; background: rgba(0, 0, 0, 0.2); }
    .pd-sd-records .pd-sd-remove { position: absolute; top: 14px; right: 12px; }
    .pd-sd-records label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: rgba(235, 235, 240, 0.7); }
    .pd-sd-vars code { margin-right: 4px; }
    .pd-sd-unassigned { color: #ff9f0a; }
    .pd-sd-fields { display: none; }
    .pd-sd-fields.is-active { display: block; }
</style>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Domains</h3>
                <div class="box-tools">
                    <button type="button" class="btn btn-sm btn-primary" data-sd-domain="new"><i class="fa fa-plus"></i> <span>Add domain</span></button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Subdomains</th>
                            <th>Available</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($domains as $domain)
                            <tr>
                                <td>
                                    <strong>{{ $domain->domain }}</strong><br>
                                    <span class="pd-sd-provider">{{ $domain->providerName() }}</span>
                                </td>
                                <td>{{ $domain->subdomains_count }}</td>
                                <td>
                                    <form action="{{ route('admin.subdomains.domains.toggle', $domain->id) }}" method="POST">
                                        {!! csrf_field() !!}
                                        <label class="pd-switch"><input type="checkbox" onchange="this.form.submit()" @checked($domain->available)><i class="pd-switch-track"></i></label>
                                    </form>
                                </td>
                                <td class="pd-sd-actions">
                                    <form action="{{ route('admin.subdomains.domains.test', $domain->id) }}" method="POST">
                                        {!! csrf_field() !!}
                                        <button type="submit" class="btn btn-xs btn-default" title="Test the connection"><i class="fa fa-shield"></i></button>
                                    </form>
                                    <button type="button" class="btn btn-xs btn-default" title="Edit"
                                            data-sd-domain="edit"
                                            data-id="{{ $domain->id }}"
                                            data-domain="{{ $domain->domain }}"
                                            data-provider="{{ $domain->provider }}"
                                            data-available="{{ $domain->available ? '1' : '0' }}"
                                            data-url="{{ $domain->secrets()['url'] ?? '' }}"
                                            data-server="{{ $domain->secrets()['server'] ?? '' }}"><i class="fa fa-pencil"></i></button>
                                    <form action="{{ route('admin.subdomains.domains.delete', $domain->id) }}" method="POST" class="pd-sd-confirm" data-kind="domain" data-name="{{ $domain->domain }}">
                                        {!! csrf_field() !!}
                                        <button type="submit" class="btn btn-xs btn-danger" title="Remove"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted"><span>No domain yet. Add one to let servers make subdomains.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<form action="{{ route('admin.subdomains.settings') }}" method="POST" id="pdSdSettings">
    {!! csrf_field() !!}
    <input type="hidden" name="records" id="pdSdRecordsInput" value="">
    <div class="row">
        <div class="col-md-4">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Limit</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pdSdDefaultLimit">Subdomains per server by default</label>
                        <input type="number" min="0" max="100" class="form-control" id="pdSdDefaultLimit" name="default_limit" value="{{ old('default_limit', $defaultLimit) }}">
                        <p class="pd-sd-help"><span>0 = no subdomains. A server can have its own number, on its Build page.</span></p>
                    </div>
                    <label class="pd-switch"><input type="checkbox" name="user_domains" value="1" @checked(old('user_domains', $userDomains))><i class="pd-switch-track"></i><span>Let users add their own domains</span></label>
                    <p class="pd-sd-help"><span>In the Subdomains tab of a server, users can then add their own name (such as play.their-domain.com). They create the record in their own DNS, pointing at your IP: the panel shows them what to create and checks it. No key is asked, and nothing of their DNS is changed by the panel.</span></p>
                </div>
            </div>
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Name blacklist</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pdSdBlacklist">Blocked names</label>
                        <textarea class="form-control" id="pdSdBlacklist" name="blacklist" rows="8" style="font-family: monospace;">{{ old('blacklist', $blacklist) }}</textarea>
                        <p class="pd-sd-help"><span>One regular expression per line, matched case-insensitively against the name users pick. ^admin$ blocks exactly "admin"; admin blocks any name containing it.</span></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Default records</h3>
                    <div class="box-tools">
                        <button type="button" class="btn btn-xs btn-default" id="pdSdAddRecord"><i class="fa fa-plus"></i> <span>Add a record</span></button>
                    </div>
                </div>
                <div class="box-body">
                    <p class="pd-sd-help pd-sd-vars" style="margin: 0 0 12px;">
                        <span>The DNS records created for each subdomain. Use</span>
                        <code>{name}</code><code>{domain}</code><code>{fqdn}</code><code>{ip}</code><code>{port}</code><code>{server}</code><code>{server_name}</code>
                        <span>in names and content. A TTL of 0 uses the provider's automatic value. "Address" makes an A, AAAA or CNAME record, from the address of the allocation.</span>
                    </p>
                    <div class="pd-sd-records" id="pdSdRecords"></div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-save"></i> <span>Save</span></button>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">All subdomains</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Subdomain</th>
                            <th>Server</th>
                            <th>Points at</th>
                            <th>Records</th>
                            <th>Notes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subdomains as $subdomain)
                            <tr>
                                <td><code>{{ $subdomain->fqdn() }}</code></td>
                                <td>
                                    @if ($subdomain->server)
                                        <a href="{{ route('admin.servers.view', $subdomain->server->id) }}">{{ $subdomain->server->name }}</a>
                                    @endif
                                </td>
                                <td>
                                    @if ($subdomain->allocation)
                                        <code>{{ $subdomain->allocation->ip_alias ?: $subdomain->allocation->ip }}:{{ $subdomain->allocation->port }}</code>
                                    @else
                                        <span class="pd-sd-unassigned">Unassigned</span>
                                    @endif
                                </td>
                                <td>{{ count($subdomain->records ?? []) }}</td>
                                <td>{{ $subdomain->notes }}</td>
                                <td class="pd-sd-actions">
                                    <form action="{{ route('admin.subdomains.delete', $subdomain->id) }}" method="POST" class="pd-sd-confirm" data-kind="sub" data-name="{{ $subdomain->fqdn() }}">
                                        {!! csrf_field() !!}
                                        <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> <span>Remove</span></button>
                                    </form>
                                    <form action="{{ route('admin.subdomains.delete', $subdomain->id) }}" method="POST" class="pd-sd-confirm" data-kind="force" data-name="{{ $subdomain->fqdn() }}">
                                        {!! csrf_field() !!}
                                        <input type="hidden" name="force" value="1">
                                        <button type="submit" class="btn btn-xs btn-default"><span>Remove anyway</span></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted"><span>No subdomain yet.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Own domains of the users</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Server</th>
                            <th>Points at</th>
                            <th>DNS</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customDomains as $custom)
                            <tr>
                                <td><code>{{ $custom->hostname }}</code></td>
                                <td>
                                    @if ($custom->server)
                                        <a href="{{ route('admin.servers.view', $custom->server->id) }}">{{ $custom->server->name }}</a>
                                    @endif
                                </td>
                                <td>
                                    @if ($custom->allocation)
                                        <code>{{ $custom->allocation->ip_alias ?: $custom->allocation->ip }}:{{ $custom->allocation->port }}</code>
                                    @else
                                        <span class="pd-sd-unassigned">Unassigned</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($custom->verified_at)
                                        <span class="label label-success">Verified</span>
                                    @else
                                        <span class="label label-warning">Waiting</span>
                                    @endif
                                </td>
                                <td class="pd-sd-actions">
                                    <form action="{{ route('admin.subdomains.custom.delete', $custom->id) }}" method="POST" class="pd-sd-confirm" data-kind="custom" data-name="{{ $custom->hostname }}">
                                        {!! csrf_field() !!}
                                        <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> <span>Remove</span></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted"><span>No own domain yet.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pdSdDomainModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form class="modal-content" method="POST" id="pdSdDomainForm" data-store="{{ route('admin.subdomains.domains.store') }}" data-update="{{ route('admin.subdomains.domains.update', ['domain' => '__ID__']) }}" autocomplete="off">
            {!! csrf_field() !!}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="pdSdDomainTitleNew"><span>Add domain</span></h4>
                <h4 class="modal-title" id="pdSdDomainTitleEdit" style="display: none;"><span>Edit the domain</span></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="pdSdDomain">Domain</label>
                    <input type="text" class="form-control" id="pdSdDomain" name="domain" placeholder="example.com" value="{{ old('domain') }}">
                    <p class="pd-sd-help"><span>The zone users pick subdomains under.</span></p>
                </div>
                <div class="form-group">
                    <label for="pdSdProvider">Provider</label>
                    <select class="form-control" id="pdSdProvider" name="provider">
                        @foreach ($providers as $value => $label)
                            <option value="{{ $value }}" @selected(old('provider', 'cloudflare') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pd-sd-fields" data-provider="cloudflare">
                    <div class="form-group">
                        <label for="pdSdToken">API token (Zone:DNS:Edit)</label>
                        <input type="password" class="form-control" id="pdSdToken" name="token" autocomplete="new-password">
                        <p class="pd-sd-help"><span>Cloudflare → My Profile → API Tokens → Create Token, with the permission Zone → DNS → Edit on this zone.</span></p>
                    </div>
                </div>
                <div class="pd-sd-fields" data-provider="bunny">
                    <div class="form-group">
                        <label for="pdSdBunnyKey">API key</label>
                        <input type="password" class="form-control" id="pdSdBunnyKey" name="key" autocomplete="new-password">
                        <p class="pd-sd-help"><span>bunny.net → Account settings → API key.</span></p>
                    </div>
                </div>
                <div class="pd-sd-fields" data-provider="powerdns">
                    <div class="form-group">
                        <label for="pdSdPdnsUrl">API address</label>
                        <input type="url" class="form-control" id="pdSdPdnsUrl" name="url" placeholder="http://ns1.example.com:8081" value="{{ old('url') }}">
                    </div>
                    <div class="form-group">
                        <label for="pdSdPdnsKey">API key</label>
                        <input type="password" class="form-control" id="pdSdPdnsKey" name="key" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="pdSdPdnsServer">Server</label>
                        <input type="text" class="form-control" id="pdSdPdnsServer" name="server" placeholder="localhost" value="{{ old('server') }}">
                    </div>
                </div>
                <p class="pd-sd-help" id="pdSdKeepHint" style="display: none;"><span>Leave a key empty to keep the one already saved.</span></p>
                <label class="pd-switch" style="margin-top: 8px;"><input type="checkbox" name="available" value="1" id="pdSdAvailable" checked><i class="pd-switch-track"></i><span>Available to the servers</span></label>
                <p class="pd-sd-help"><span>The connection is tested before saving.</span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><span>Cancel</span></button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <span>Save</span></button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="pdSdConfirm" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body"><p id="pdSdConfirmText"></p><p><code id="pdSdConfirmName"></code></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><span>Cancel</span></button>
                <button type="button" class="btn btn-danger" id="pdSdConfirmGo"><span>Remove</span></button>
            </div>
        </div>
    </div>
</div>

<span hidden id="pdSdT-confirm-domain">Remove this domain? Its subdomains must be removed first.</span>
<span hidden id="pdSdT-confirm-sub">Remove this subdomain and its DNS records?</span>
<span hidden id="pdSdT-confirm-force">Remove this subdomain from the panel, even if its DNS records cannot be removed?</span>
<span hidden id="pdSdT-confirm-custom">Remove this own domain from the server? Its DNS is not touched.</span>
<span hidden id="pdSdT-address">Address (A, AAAA or CNAME)</span>
<span hidden id="pdSdT-type">Type</span>
<span hidden id="pdSdT-name">Name</span>
<span hidden id="pdSdT-ttl">TTL</span>
<span hidden id="pdSdT-content">Content</span>
<span hidden id="pdSdT-service">Service</span>
<span hidden id="pdSdT-protocol">Protocol</span>
<span hidden id="pdSdT-priority">Priority</span>
<span hidden id="pdSdT-weight">Weight</span>
<span hidden id="pdSdT-port">Port</span>
<span hidden id="pdSdT-target">Target</span>
<span hidden id="pdSdT-proxied">Proxy through Cloudflare</span>
<span hidden id="pdSdT-proxiedHelp">Only applies to Cloudflare domains. Leave off for game servers, since the proxy only passes web traffic.</span>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var templates = @json($templates);
            var list = document.getElementById('pdSdRecords');
            var t = function (key) { var el = document.getElementById('pdSdT-' + key); return el ? el.textContent : key; };

            function field(label, input, cols) {
                var col = document.createElement('div');
                col.className = 'form-group col-sm-' + cols;
                var l = document.createElement('label');
                l.textContent = label;
                col.appendChild(l);
                col.appendChild(input);
                return col;
            }

            function input(key, value, type) {
                var el = document.createElement('input');
                el.className = 'form-control';
                el.type = type || 'text';
                el.value = value === undefined || value === null ? '' : value;
                el.setAttribute('data-key', key);
                return el;
            }

            function row(record) {
                var box = document.createElement('div');
                box.className = 'pd-sd-row';
                var grid = document.createElement('div');
                grid.className = 'row';
                var type = document.createElement('select');
                type.className = 'form-control';
                type.setAttribute('data-key', 'type');
                [['address', t('address')], ['CNAME', 'CNAME'], ['TXT', 'TXT'], ['SRV', 'SRV']].forEach(function (o) {
                    var option = document.createElement('option');
                    option.value = o[0];
                    option.textContent = o[1];
                    option.selected = record.type === o[0];
                    type.appendChild(option);
                });
                grid.appendChild(field(t('type'), type, 4));

                if (record.type === 'SRV') {
                    grid.appendChild(field(t('service'), input('service', record.service || '_minecraft'), 3));
                    var protocol = document.createElement('select');
                    protocol.className = 'form-control';
                    protocol.setAttribute('data-key', 'protocol');
                    ['_tcp', '_udp', '_tls'].forEach(function (p) {
                        var option = document.createElement('option');
                        option.value = p; option.textContent = p; option.selected = (record.protocol || '_tcp') === p;
                        protocol.appendChild(option);
                    });
                    grid.appendChild(field(t('protocol'), protocol, 2));
                    grid.appendChild(field(t('ttl'), input('ttl', record.ttl || 0, 'number'), 3));
                    grid.appendChild(field(t('name'), input('name', record.name || '{name}'), 4));
                    grid.appendChild(field(t('priority'), input('priority', record.priority || 0, 'number'), 2));
                    grid.appendChild(field(t('weight'), input('weight', record.weight === undefined ? 5 : record.weight, 'number'), 2));
                    grid.appendChild(field(t('port'), input('port', record.port || '{port}'), 2));
                    grid.appendChild(field(t('target'), input('target', record.target || '{fqdn}'), 2));
                } else {
                    grid.appendChild(field(t('name'), input('name', record.name || '{name}'), 5));
                    grid.appendChild(field(t('ttl'), input('ttl', record.ttl || 0, 'number'), 3));
                    if (record.type === 'CNAME' || record.type === 'TXT') {
                        grid.appendChild(field(t('content'), input('content', record.content || ''), 12));
                    }
                }
                box.appendChild(grid);

                if (record.type === 'address' || record.type === 'CNAME') {
                    var proxy = document.createElement('label');
                    proxy.className = 'pd-switch';
                    var check = document.createElement('input');
                    check.type = 'checkbox';
                    check.checked = !!record.proxied;
                    check.setAttribute('data-key', 'proxied');
                    var track = document.createElement('i');
                    track.className = 'pd-switch-track';
                    var text = document.createElement('span');
                    text.textContent = t('proxied');
                    proxy.appendChild(check); proxy.appendChild(track); proxy.appendChild(text);
                    box.appendChild(proxy);
                    var help = document.createElement('p');
                    help.className = 'pd-sd-help';
                    help.style.marginBottom = '8px';
                    help.textContent = t('proxiedHelp');
                    box.appendChild(help);
                }

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-xs btn-danger pd-sd-remove';
                remove.innerHTML = '<i class="fa fa-trash"></i>';
                remove.addEventListener('click', function () { box.remove(); });
                box.appendChild(remove);

                type.addEventListener('change', function () {
                    var current = read(box);
                    current.type = type.value;
                    box.replaceWith(row(current));
                });

                return box;
            }

            function read(box) {
                var record = {};
                box.querySelectorAll('[data-key]').forEach(function (el) {
                    record[el.getAttribute('data-key')] = el.type === 'checkbox' ? el.checked : el.value;
                });
                return record;
            }

            templates.forEach(function (record) { list.appendChild(row(record)); });
            document.getElementById('pdSdAddRecord').addEventListener('click', function () {
                list.appendChild(row({ type: 'address', name: '{name}', ttl: 0 }));
            });
            document.getElementById('pdSdSettings').addEventListener('submit', function () {
                var records = [];
                list.querySelectorAll('.pd-sd-row').forEach(function (box) { records.push(read(box)); });
                document.getElementById('pdSdRecordsInput').value = JSON.stringify(records);
            });

            // The form of a domain: new or edited, with the fields of its provider.
            var form = document.getElementById('pdSdDomainForm');
            var provider = document.getElementById('pdSdProvider');
            function showFields() {
                form.querySelectorAll('.pd-sd-fields').forEach(function (box) {
                    var on = box.getAttribute('data-provider') === provider.value;
                    box.classList.toggle('is-active', on);
                    box.querySelectorAll('input').forEach(function (el) { el.disabled = !on; });
                });
            }
            provider.addEventListener('change', showFields);
            showFields();

            document.querySelectorAll('[data-sd-domain]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var editing = button.getAttribute('data-sd-domain') === 'edit';
                    form.reset();
                    form.action = editing ? form.getAttribute('data-update').replace('__ID__', button.getAttribute('data-id')) : form.getAttribute('data-store');
                    document.getElementById('pdSdDomainTitleNew').style.display = editing ? 'none' : '';
                    document.getElementById('pdSdDomainTitleEdit').style.display = editing ? '' : 'none';
                    document.getElementById('pdSdKeepHint').style.display = editing ? '' : 'none';
                    var domainInput = document.getElementById('pdSdDomain');
                    domainInput.readOnly = editing;
                    if (editing) {
                        domainInput.value = button.getAttribute('data-domain');
                        provider.value = button.getAttribute('data-provider');
                        document.getElementById('pdSdAvailable').checked = button.getAttribute('data-available') === '1';
                        document.getElementById('pdSdPdnsUrl').value = button.getAttribute('data-url');
                        document.getElementById('pdSdPdnsServer').value = button.getAttribute('data-server');
                    }
                    showFields();
                    window.jQuery('#pdSdDomainModal').modal('show');
                });
            });
            @if ($errors->any() || old('domain'))
                window.jQuery(function () { window.jQuery('#pdSdDomainModal').modal('show'); });
            @endif

            // A small window instead of the browser's confirm().
            var pending = null;
            document.querySelectorAll('form.pd-sd-confirm').forEach(function (f) {
                f.addEventListener('submit', function (event) {
                    if (f.getAttribute('data-ok') === '1') { return; }
                    event.preventDefault();
                    pending = f;
                    document.getElementById('pdSdConfirmText').textContent = t('confirm-' + f.getAttribute('data-kind'));
                    document.getElementById('pdSdConfirmName').textContent = f.getAttribute('data-name');
                    window.jQuery('#pdSdConfirm').modal('show');
                });
            });
            document.getElementById('pdSdConfirmGo').addEventListener('click', function () {
                if (!pending) { return; }
                pending.setAttribute('data-ok', '1');
                pending.submit();
            });
        })();
    </script>
@endsection
