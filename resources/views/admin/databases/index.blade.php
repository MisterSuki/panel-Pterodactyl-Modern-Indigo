@extends('layouts.admin')

@section('title')
    Database Hosts
@endsection

@section('content-header')
    <h1>Database Hosts<small>Database hosts that servers can have databases created on.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Database Hosts</li>
    </ol>
@endsection

@section('content')
@php
    try {
        $pdPublicKey = app(\Pterodactyl\Services\Databases\Hosts\DatabaseHostInstaller::class)->publicKey();
    } catch (\Throwable $e) {
        $pdPublicKey = null;
    }
    $pdAuthorize = $pdPublicKey ? "mkdir -p ~/.ssh && echo '" . $pdPublicKey . "' >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys" : null;
@endphp
<style>
    .pd-db-grid { display: grid; grid-template-columns: 1fr 90px; gap: 10px; }
    .pd-db-methods { display: flex; gap: 6px; margin-bottom: 10px; }
    .pd-db-methods label { flex: 1; margin: 0; padding: 8px 10px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.12); cursor: pointer; font-weight: 500; text-align: center; color: rgba(235, 235, 240, 0.75); }
    .pd-db-methods label.is-active { border-color: rgba(62, 207, 142, 0.6); background: rgba(62, 207, 142, 0.08); color: #fff; }
    .pd-db-methods input { display: none; }
    .pd-db-cmd { display: flex; gap: 8px; align-items: flex-start; margin: 6px 0 12px; padding: 10px 12px; border-radius: 8px; background: #0a0a0a; border: 1px solid rgba(255, 255, 255, 0.1); font-family: 'Geist Mono', ui-monospace, monospace; font-size: 11.5px; color: #e5e5e5; }
    .pd-db-cmd code { flex: 1; min-width: 0; background: none; padding: 0; color: inherit; white-space: pre-wrap; word-break: break-all; }
    .pd-db-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 4px 0 0; }
    .pd-db-state { display: flex; align-items: center; gap: 8px; margin: 4px 0 8px; font-weight: 600; }
    .pd-db-state .dot { width: 8px; height: 8px; border-radius: 50%; background: #f5a524; animation: pdDbPulse 1.4s infinite; }
    .pd-db-state.done .dot { background: #3ecf8e; animation: none; }
    .pd-db-state.failed .dot { background: #e5484d; animation: none; }
    @keyframes pdDbPulse { 50% { opacity: .3; } }
    .pd-db-term { margin: 0; height: 260px; overflow: auto; padding: 10px 12px; border-radius: 8px; background: #050505; border: 1px solid rgba(255, 255, 255, 0.1); color: #d4d4d4; font-family: 'Geist Mono', ui-monospace, monospace; font-size: 11.5px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
    .pd-db-msg { font-size: 12.5px; margin: 8px 0 0; } .pd-db-msg.failed { color: #fca5a5; } .pd-db-msg.done { color: #86efac; }
    [data-pd-db-for][hidden], [data-pd-db-live][hidden], [data-pd-db-advanced][hidden], [data-pd-db-reload][hidden] { display: none !important; }
</style>
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Host List</h3>
                <div class="box-tools">
                    <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#autoHostModal"><i class="fa fa-magic"></i> <span>Create automatically</span></button>
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newHostModal">Create New</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Host</th>
                            <th>Port</th>
                            <th>Username</th>
                            <th class="text-center">Databases</th>
                            <th class="text-center">Node</th>
                        </tr>
                        @foreach ($hosts as $host)
                            <tr>
                                <td><code>{{ $host->id }}</code></td>
                                <td><a href="{{ route('admin.databases.view', $host->id) }}">{{ $host->name }}</a></td>
                                <td><code>{{ $host->host }}</code></td>
                                <td><code>{{ $host->port }}</code></td>
                                <td>{{ $host->username }}</td>
                                <td class="text-center">{{ $host->databases_count }}</td>
                                <td class="text-center">
                                    @if(! is_null($host->node))
                                        <a href="{{ route('admin.nodes.view', $host->node->id) }}">{{ $host->node->name }}</a>
                                    @else
                                        <span class="label label-default">None</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="autoHostModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form data-pd-db-form autocomplete="off">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-magic"></i> <span>Create a database host automatically</span></h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted" style="margin-top: 0;"><span>The panel connects to the machine with SSH, installs MariaDB if needed, lets it listen on the network, creates a user able to create the databases of the servers (with a random password) and saves the host here.</span></p>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="pdDbName">Name</label>
                            <input type="text" id="pdDbName" name="name" class="form-control" required maxlength="60" value="MariaDB">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="pdDbNode">Linked Node</label>
                            <select id="pdDbNode" name="node_id" class="form-control" data-pd-db-node>
                                <option value="">None</option>
                                @foreach($locations as $location)
                                    <optgroup label="{{ $location->short }}">
                                        @foreach($location->nodes as $node)
                                            <option value="{{ $node->id }}" data-fqdn="{{ $node->fqdn }}">{{ $node->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="pd-db-grid">
                        <div class="form-group">
                            <label for="pdDbHost">Address of the machine</label>
                            <input type="text" id="pdDbHost" name="host" class="form-control" required placeholder="203.0.113.42">
                        </div>
                        <div class="form-group">
                            <label for="pdDbPort">SSH port</label>
                            <input type="number" id="pdDbPort" name="port" class="form-control" required min="1" max="65535" value="22">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pdDbUser">User</label>
                        <input type="text" id="pdDbUser" name="user" class="form-control" required value="root">
                    </div>
                    <div class="pd-db-methods" data-pd-db-methods>
                        <label class="is-active"><input type="radio" name="method" value="password" checked> <i class="fa fa-key"></i> <span>Password</span></label>
                        @if ($pdPublicKey)
                            <label><input type="radio" name="method" value="key"> <i class="fa fa-lock"></i> <span>Key of the panel</span></label>
                        @endif
                    </div>
                    <div data-pd-db-for="password">
                        <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="Password of the user">
                        <p class="pd-db-help" style="margin-bottom: 12px;"><span>Used once to connect, never saved.</span></p>
                    </div>
                    @if ($pdPublicKey)
                        <div data-pd-db-for="key" hidden>
                            <p class="pd-db-help" style="margin: 0;"><span>Run this once on the machine, as the user above, to let the panel in without password:</span></p>
                            <div class="pd-db-cmd"><code>{{ $pdAuthorize }}</code><button type="button" class="btn btn-xs btn-default" data-pd-db-copy="{{ $pdAuthorize }}" title="Copy"><i class="fa fa-clipboard"></i></button></div>
                        </div>
                    @endif
                    <p style="margin: 0 0 10px;"><a href="#" data-pd-db-toggle-advanced><i class="fa fa-sliders"></i> <span>More options</span></a></p>
                    <div class="row" data-pd-db-advanced hidden>
                        <div class="col-md-6 form-group">
                            <label for="pdDbDbUser">Database user</label>
                            <input type="text" id="pdDbDbUser" name="db_user" class="form-control" required value="pterodactyl_db" pattern="[a-z][a-z0-9_]{2,31}">
                            <p class="pd-db-help"><span>Made on the machine, with a random password.</span></p>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="pdDbDbHost">Address of MySQL for the panel</label>
                            <input type="text" id="pdDbDbHost" name="db_host" class="form-control" placeholder="The address of the machine">
                            <p class="pd-db-help"><span>Only if the panel reaches it at another address (a private IP...).</span></p>
                        </div>
                    </div>

                    <div data-pd-db-live hidden>
                        <div class="pd-db-state" data-pd-db-state><span class="dot"></span><span data-pd-db-label>Installing…</span></div>
                        <pre class="pd-db-term" data-pd-db-term></pre>
                        <p class="pd-db-msg" data-pd-db-msg></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <p class="text-muted small text-left"><span>Port 3306 must be open on the firewall of the host so the panel and the game servers can reach MariaDB.</span></p>
                    <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-default btn-sm" data-pd-db-reload hidden><i class="fa fa-refresh"></i> <span>See the host</span></button>
                    <button type="submit" class="btn btn-success btn-sm" data-pd-db-start><i class="fa fa-magic"></i> <span>Create automatically</span></button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="newHostModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.databases') }}" method="POST">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Create New Database Host</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="pName" class="form-label">Name</label>
                        <input type="text" name="name" id="pName" class="form-control" />
                        <p class="text-muted small">A short identifier used to distinguish this location from others. Must be between 1 and 60 characters, for example, <code>us.nyc.lvl3</code>.</p>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="pHost" class="form-label">Host</label>
                            <input type="text" name="host" id="pHost" class="form-control" />
                            <p class="text-muted small">The IP address or FQDN that should be used when attempting to connect to this MySQL host <em>from the panel</em> to add new databases.</p>
                        </div>
                        <div class="col-md-6">
                            <label for="pPort" class="form-label">Port</label>
                            <input type="text" name="port" id="pPort" class="form-control" value="3306"/>
                            <p class="text-muted small">The port that MySQL is running on for this host.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="pUsername" class="form-label">Username</label>
                            <input type="text" name="username" id="pUsername" class="form-control" />
                            <p class="text-muted small">The username of an account that has enough permissions to create new users and databases on the system.</p>
                        </div>
                        <div class="col-md-6">
                            <label for="pPassword" class="form-label">Password</label>
                            <input type="password" name="password" id="pPassword" class="form-control" />
                            <p class="text-muted small">The password to the account defined.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pNodeId" class="form-label">Linked Node</label>
                        <select name="node_id" id="pNodeId" class="form-control">
                            <option value="">None</option>
                            @foreach($locations as $location)
                                <optgroup label="{{ $location->short }}">
                                    @foreach($location->nodes as $node)
                                        <option value="{{ $node->id }}">{{ $node->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="text-muted small">This setting does nothing other than default to this database host when adding a database to a server on the selected node.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <p class="text-danger small text-left">The account defined for this database host <strong>must</strong> have the <code>WITH GRANT OPTION</code> permission. If the defined account does not have this permission requests to create databases <em>will</em> fail. <strong>Do not use the same account details for MySQL that you have defined for this panel.</strong></p>
                    {!! csrf_field() !!}
                    <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('#pNodeId').select2();

        (function () {
            var form = document.querySelector('[data-pd-db-form]');
            if (!form) return;
            var url = @json(route('admin.databases.auto'));
            var live = form.querySelector('[data-pd-db-live]');
            var term = form.querySelector('[data-pd-db-term]');
            var stateBox = form.querySelector('[data-pd-db-state]');
            var label = form.querySelector('[data-pd-db-label]');
            var msg = form.querySelector('[data-pd-db-msg]');
            var start = form.querySelector('[data-pd-db-start]');
            var reload = form.querySelector('[data-pd-db-reload]');
            var timer = null;
            var texts = {
                running: @json('Installing…'),
                done: @json('The database host is ready.'),
                failed: @json('The creation failed.'),
            };

            // Picking a node fills the address of the machine with the one of the node.
            form.querySelector('[data-pd-db-node]').addEventListener('change', function () {
                var option = this.options[this.selectedIndex];
                var fqdn = option ? option.getAttribute('data-fqdn') : '';
                if (fqdn) form.querySelector('[name="host"]').value = fqdn;
                if (option && option.value && form.querySelector('[name="name"]').value === 'MariaDB') {
                    form.querySelector('[name="name"]').value = 'MariaDB ' + option.textContent.trim();
                }
            });
            form.querySelectorAll('[data-pd-db-methods] input').forEach(function (input) {
                input.addEventListener('change', function () {
                    form.querySelectorAll('[data-pd-db-methods] label').forEach(function (l) { l.classList.toggle('is-active', l.querySelector('input').checked); });
                    form.querySelectorAll('[data-pd-db-for]').forEach(function (b) { b.hidden = b.getAttribute('data-pd-db-for') !== input.value; });
                });
            });
            form.querySelector('[data-pd-db-toggle-advanced]').addEventListener('click', function (event) {
                event.preventDefault();
                var box = form.querySelector('[data-pd-db-advanced]');
                box.hidden = !box.hidden;
            });
            form.querySelectorAll('[data-pd-db-copy]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var text = button.getAttribute('data-pd-db-copy');
                    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text); }
                    button.querySelector('i').className = 'fa fa-check';
                    setTimeout(function () { button.querySelector('i').className = 'fa fa-clipboard'; }, 1500);
                });
            });
            reload.addEventListener('click', function () { window.location.reload(); });

            var render = function (status) {
                if (!status || status.state === 'idle') return;
                live.hidden = false;
                var atBottom = term.scrollTop + term.clientHeight >= term.scrollHeight - 30;
                term.textContent = status.log || '';
                if (atBottom) term.scrollTop = term.scrollHeight;
                stateBox.className = 'pd-db-state ' + status.state;
                label.textContent = texts[status.state] || status.state;
                msg.className = 'pd-db-msg ' + status.state;
                msg.textContent = status.state === 'failed' ? (status.message || '') : '';
                start.disabled = status.state === 'running';
                reload.hidden = status.state !== 'done';
                if (status.state === 'running') {
                    clearTimeout(timer);
                    timer = setTimeout(poll, 1500);
                }
            };
            var poll = function () {
                $.getJSON(url).done(render).fail(function () { timer = setTimeout(poll, 4000); });
            };

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                start.disabled = true;
                $.ajax({ method: 'POST', url: url, data: $(form).serialize(), headers: { 'X-CSRF-TOKEN': @json(csrf_token()) } })
                    .done(function (status) {
                        form.querySelector('[name="password"]').value = '';
                        term.textContent = '';
                        render(status);
                    })
                    .fail(function (xhr) {
                        start.disabled = false;
                        var body = xhr.responseJSON || {};
                        var text = body.errors && body.errors[0] && body.errors[0].detail ? body.errors[0].detail : (body.message || @json('The creation could not be started.'));
                        live.hidden = false;
                        stateBox.className = 'pd-db-state failed';
                        label.textContent = texts.failed;
                        msg.className = 'pd-db-msg failed';
                        msg.textContent = text;
                    });
            });

            // A creation already running (the page was reloaded): show it.
            $.getJSON(url).done(function (status) {
                if (status && status.state === 'running') {
                    $('#autoHostModal').modal('show');
                    render(status);
                }
            });
        })();
    </script>
@endsection
