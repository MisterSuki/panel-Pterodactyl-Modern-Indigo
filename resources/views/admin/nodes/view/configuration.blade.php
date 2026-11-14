@extends('layouts.admin')

@section('title')
    {{ $node->name }}: Configuration
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>Your daemon configuration file.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nodes') }}">Nodes</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">Configuration</li>
    </ol>
@endsection

@section('content')
@php
    $pdYaml = $node->getYamlConfiguration();
    // The same file, with the secret of the node hidden until asked (it gives full access to Wings).
    $pdYamlShown = preg_replace('/^(\s*token:\s*)(.+)$/m', '$1••••••••••••••••••••••••', $pdYaml);
    $pdInstaller = str_replace(' --update', '', app(\Pterodactyl\Services\Admin\ThemeVersionService::class)->updateCommand());
    $pdPanelUrl = rtrim((string) config('app.url'), '/');
    $pdInsecure = config('app.debug') ? ' --allow-insecure' : '';
    try {
        $pdPublicKey = app(\Pterodactyl\Services\Nodes\WingsInstaller::class)->publicKey();
    } catch (\Throwable $e) {
        $pdPublicKey = null;
    }
    $pdAuthorize = $pdPublicKey ? "mkdir -p ~/.ssh && echo '" . $pdPublicKey . "' >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys" : null;
@endphp
<style>
    .pd-cfg-pre { position: relative; margin: 0; max-height: 520px; overflow: auto; font-size: 12.5px; line-height: 1.6; }
    .pd-cfg-pre .k { color: #93c5fd; }
    .pd-cfg-tools { display: flex; gap: 6px; }
    .pd-cfg-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 6px 0 0; }
    .pd-wg-step { display: flex; gap: 12px; padding: 14px 0; border-top: 1px solid rgba(255, 255, 255, 0.08); }
    .pd-wg-step:first-child { border-top: 0; padding-top: 0; }
    .pd-wg-num { flex-shrink: 0; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; background: rgba(255, 255, 255, 0.08); color: #e5e5e5; }
    .pd-wg-body { flex: 1; min-width: 0; }
    .pd-wg-body strong { display: block; margin-bottom: 2px; }
    .pd-wg-cmd { position: relative; display: flex; align-items: flex-start; gap: 8px; margin-top: 8px; padding: 10px 12px; border-radius: 8px; background: #0a0a0a; border: 1px solid rgba(255, 255, 255, 0.1); font-family: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; line-height: 1.55; color: #e5e5e5; }
    .pd-wg-cmd code { flex: 1; min-width: 0; background: none; padding: 0; color: inherit; white-space: pre-wrap; word-break: break-all; }
    .pd-wg-cmd .pd-secret { filter: blur(5px); cursor: pointer; transition: filter .15s; border-radius: 3px; }
    .pd-wg-cmd .pd-secret.is-shown { filter: none; }
    .pd-wg-cmd .btn { flex-shrink: 0; }
    .pd-wg-tabs { display: flex; gap: 4px; padding: 3px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 14px; }
    .pd-wg-tabs button { flex: 1; border: 0; background: none; padding: 6px 8px; border-radius: 6px; color: rgba(235, 235, 240, 0.65); font-weight: 500; font-size: 12.5px; }
    .pd-wg-tabs button.is-active { background: rgba(255, 255, 255, 0.1); color: #fff; }
    .pd-wg-warn { display: flex; gap: 8px; margin-top: 14px; padding: 10px 12px; border-radius: 8px; font-size: 12px; line-height: 1.5; color: #fcd34d; background: rgba(245, 165, 36, 0.08); border: 1px solid rgba(245, 165, 36, 0.25); }
    [data-pd-wg-result][hidden], [data-pd-wg-pane][hidden], [data-pd-wg-manual][hidden], [data-pd-auto-live][hidden], [data-pd-auto-for][hidden] { display: none !important; }
    .pd-auto-grid { display: grid; grid-template-columns: 1fr 90px; gap: 10px; }
    .pd-auto-methods { display: flex; gap: 6px; margin-bottom: 10px; }
    .pd-auto-methods label { flex: 1; margin: 0; padding: 8px 10px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.12); cursor: pointer; font-weight: 500; text-align: center; color: rgba(235, 235, 240, 0.75); }
    .pd-auto-methods label.is-active { border-color: rgba(62, 207, 142, 0.6); background: rgba(62, 207, 142, 0.08); color: #fff; }
    .pd-auto-methods input { display: none; }
    .pd-auto-state { display: flex; align-items: center; gap: 8px; margin: 14px 0 8px; font-weight: 600; }
    .pd-auto-state .dot { width: 8px; height: 8px; border-radius: 50%; background: #f5a524; animation: pdPulse 1.4s infinite; }
    .pd-auto-state.done .dot { background: #3ecf8e; animation: none; }
    .pd-auto-state.failed .dot { background: #e5484d; animation: none; }
    @keyframes pdPulse { 50% { opacity: .3; } }
    .pd-auto-term { margin: 0; height: 320px; overflow: auto; padding: 10px 12px; border-radius: 8px; background: #050505; border: 1px solid rgba(255, 255, 255, 0.1); color: #d4d4d4; font-family: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11.5px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
    .pd-auto-msg { font-size: 12.5px; margin: 8px 0 0; }
    .pd-auto-msg.failed { color: #fca5a5; } .pd-auto-msg.done { color: #86efac; }
</style>

<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">About</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">Settings</a></li>
                <li class="active"><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a></li>
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">Servers</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Configuration File</h3>
                <div class="box-tools pd-cfg-tools">
                    <button type="button" class="btn btn-xs btn-default" data-pd-cfg-reveal><i class="fa fa-eye"></i> <span>Show the token</span></button>
                    <button type="button" class="btn btn-xs btn-default" data-pd-cfg-copy><i class="fa fa-clipboard"></i> <span>Copy</span></button>
                    <button type="button" class="btn btn-xs btn-default" data-pd-cfg-download><i class="fa fa-download"></i> <span>Download</span></button>
                </div>
            </div>
            <div class="box-body">
                <pre class="pd-cfg-pre" data-pd-cfg-pre>{{ $pdYamlShown }}</pre>
            </div>
            <div class="box-footer">
                <p class="no-margin"><span>To set Wings up by hand, put this file in</span> <code>/etc/pterodactyl/config.yml</code> <span>on the machine of the node, then start it with</span> <code>systemctl enable --now wings</code>.</p>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-plug"></i> <span>Connect Wings</span></h3>
            </div>
            <div class="box-body">
                <div class="pd-wg-tabs">
                    <button type="button" class="is-active" data-pd-wg-tab="auto"><i class="fa fa-magic"></i> <span>Automatic</span></button>
                    <button type="button" data-pd-wg-tab="new"><span>New machine</span></button>
                    <button type="button" data-pd-wg-tab="existing"><span>Wings already installed</span></button>
                </div>

                <div data-pd-wg-pane="auto">
                    <p class="text-muted" style="margin-top: 0;"><span>The panel connects to the machine of this node with SSH, installs Docker and Wings with the configuration of this node, and starts it. You follow it live here.</span></p>
                    <form data-pd-auto-form autocomplete="off">
                        <div class="pd-auto-grid">
                            <div class="form-group">
                                <label for="pdAutoHost">Address of the machine</label>
                                <input type="text" id="pdAutoHost" name="host" class="form-control" required value="{{ $node->fqdn }}">
                            </div>
                            <div class="form-group">
                                <label for="pdAutoPort">SSH port</label>
                                <input type="number" id="pdAutoPort" name="port" class="form-control" required min="1" max="65535" value="22">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="pdAutoUser">User</label>
                            <input type="text" id="pdAutoUser" name="user" class="form-control" required value="root">
                        </div>
                        <div class="pd-auto-methods" data-pd-auto-methods>
                            <label class="is-active"><input type="radio" name="method" value="password" checked> <i class="fa fa-key"></i> <span>Password</span></label>
                            @if ($pdPublicKey)
                                <label><input type="radio" name="method" value="key"> <i class="fa fa-lock"></i> <span>Key of the panel</span></label>
                            @endif
                        </div>
                        <div data-pd-auto-for="password">
                            <div class="form-group" style="margin-bottom: 6px;">
                                <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="Password of the user">
                            </div>
                            <p class="pd-cfg-help" style="margin: 0 0 12px;"><span>Used once to connect, never saved.</span></p>
                        </div>
                        @if ($pdPublicKey)
                            <div data-pd-auto-for="key" hidden>
                                <p class="pd-cfg-help" style="margin: 0;"><span>Run this once on the machine, as the user above, to let the panel in without password:</span></p>
                                <div class="pd-wg-cmd" style="margin-bottom: 12px;"><code>{{ $pdAuthorize }}</code><button type="button" class="btn btn-xs btn-default" data-pd-copy-text="{{ $pdAuthorize }}" title="Copy"><i class="fa fa-clipboard"></i></button></div>
                            </div>
                        @endif
                        <button type="submit" class="btn btn-success btn-block" data-pd-auto-start><i class="fa fa-download"></i> <span>Install Wings automatically</span></button>
                    </form>

                    <div data-pd-auto-live hidden>
                        <div class="pd-auto-state" data-pd-auto-state><span class="dot"></span><span data-pd-auto-label>Installing…</span></div>
                        <pre class="pd-auto-term" data-pd-auto-term></pre>
                        <p class="pd-auto-msg" data-pd-auto-msg></p>
                    </div>
                </div>

                <div data-pd-wg-manual hidden>
                    <p class="text-muted" style="margin-top: 0;"><span>One command, run as root on the machine of this node, installs or configures Wings for it. Nothing to copy by hand.</span></p>
                    <button type="button" id="configTokenBtn" class="btn btn-success btn-block"><i class="fa fa-terminal"></i> <span>Get the command</span></button>
                </div>

                <div data-pd-wg-result hidden style="margin-top: 16px;">
                    <div data-pd-wg-pane="new" hidden>
                        <div class="pd-wg-step">
                            <span class="pd-wg-num">1</span>
                            <div class="pd-wg-body">
                                <strong>Run this as root on the machine</strong>
                                <span class="pd-cfg-help"><span>It installs Docker and Wings, writes the configuration of this node and starts Wings.</span></span>
                                <div class="pd-wg-cmd"><code data-pd-wg-cmd="new"></code><button type="button" class="btn btn-xs btn-default" data-pd-wg-copy="new" title="Copy"><i class="fa fa-clipboard"></i></button></div>
                            </div>
                        </div>
                        <div class="pd-wg-step">
                            <span class="pd-wg-num">2</span>
                            <div class="pd-wg-body">
                                <strong>Open the ports</strong>
                                <span class="pd-cfg-help"><span>8080 (Wings) and 2022 (SFTP), plus the ports of the game servers, on the firewall of the machine and of the host.</span></span>
                            </div>
                        </div>
                        <div class="pd-wg-step">
                            <span class="pd-wg-num">3</span>
                            <div class="pd-wg-body">
                                <strong>Check the heart</strong>
                                <span class="pd-cfg-help"><span>On the</span> <a href="{{ route('admin.nodes') }}">Nodes</a> <span>page, the heart of this node turns green once Wings answers.</span></span>
                            </div>
                        </div>
                    </div>

                    <div data-pd-wg-pane="existing" hidden>
                        <div class="pd-wg-step">
                            <span class="pd-wg-num">1</span>
                            <div class="pd-wg-body">
                                <strong>Run this as root on the machine</strong>
                                <span class="pd-cfg-help"><span>It writes the configuration of this node and restarts Wings.</span></span>
                                <div class="pd-wg-cmd"><code data-pd-wg-cmd="existing"></code><button type="button" class="btn btn-xs btn-default" data-pd-wg-copy="existing" title="Copy"><i class="fa fa-clipboard"></i></button></div>
                            </div>
                        </div>
                    </div>

                    <div class="pd-wg-warn">
                        <i class="fa fa-exclamation-triangle" style="margin-top: 2px;"></i>
                        <span><span>The token in the command gives access to the nodes through the API: it is blurred (click it to see it), never share it or a screenshot of it. It is kept in Application API, where it can be removed.</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    (function () {
        var yaml = @json($pdYaml);
        var yamlShown = @json($pdYamlShown);
        var pre = document.querySelector('[data-pd-cfg-pre]');
        var revealed = false;
        var commands = {};

        var copy = function (text, button) {
            var done = function () {
                var icon = button.querySelector('i');
                if (!icon) return;
                var before = icon.className;
                icon.className = 'fa fa-check';
                setTimeout(function () { icon.className = before; }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                var area = document.createElement('textarea');
                area.value = text;
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                document.body.removeChild(area);
                done();
            }
        };

        // The keys of the file in colour, the values as they are.
        var paint = function (text) {
            var escape = function (s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };
            return text.split('\n').map(function (line) {
                var match = line.match(/^(\s*-?\s*)([A-Za-z0-9_.-]+)(:)(.*)$/);
                return match ? escape(match[1]) + '<span class="k">' + escape(match[2]) + '</span>' + match[3] + escape(match[4]) : escape(line);
            }).join('\n');
        };
        pre.innerHTML = paint(yamlShown);

        document.querySelector('[data-pd-cfg-reveal]').addEventListener('click', function () {
            revealed = !revealed;
            pre.innerHTML = paint(revealed ? yaml : yamlShown);
            this.querySelector('i').className = revealed ? 'fa fa-eye-slash' : 'fa fa-eye';
        });
        document.querySelector('[data-pd-cfg-copy]').addEventListener('click', function () { copy(yaml, this); });
        document.querySelector('[data-pd-cfg-download]').addEventListener('click', function () {
            var link = document.createElement('a');
            link.href = URL.createObjectURL(new Blob([yaml], { type: 'text/yaml' }));
            link.download = 'config.yml';
            document.body.appendChild(link);
            link.click();
            setTimeout(function () { URL.revokeObjectURL(link.href); link.remove(); }, 1000);
        });

        // A command with the token blurred until it is clicked; the copy always has the real token.
        var show = function (name, before, token, after) {
            commands[name] = before + token + after;
            var code = document.querySelector('[data-pd-wg-cmd="' + name + '"]');
            code.textContent = '';
            code.appendChild(document.createTextNode(before));
            var secret = document.createElement('span');
            secret.className = 'pd-secret';
            secret.title = 'Click to show';
            secret.textContent = token;
            secret.addEventListener('click', function () { secret.classList.toggle('is-shown'); });
            code.appendChild(secret);
            code.appendChild(document.createTextNode(after));
        };

        document.querySelectorAll('[data-pd-wg-copy]').forEach(function (button) {
            button.addEventListener('click', function () { copy(commands[button.getAttribute('data-pd-wg-copy')] || '', button); });
        });
        document.querySelectorAll('[data-pd-wg-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var name = tab.getAttribute('data-pd-wg-tab');
                document.querySelectorAll('[data-pd-wg-tab]').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
                document.querySelectorAll('[data-pd-wg-pane]').forEach(function (pane) { pane.hidden = pane.getAttribute('data-pd-wg-pane') !== name; });
                document.querySelector('[data-pd-wg-manual]').hidden = name === 'auto';
                document.querySelector('[data-pd-wg-result]').hidden = name === 'auto' || !commands.new;
            });
        });

        document.querySelectorAll('[data-pd-copy-text]').forEach(function (button) {
            button.addEventListener('click', function () { copy(button.getAttribute('data-pd-copy-text'), button); });
        });

        // The automatic installation: start it, then follow its log until it is done.
        var form = document.querySelector('[data-pd-auto-form]');
        var live = document.querySelector('[data-pd-auto-live]');
        var term = document.querySelector('[data-pd-auto-term]');
        var stateBox = document.querySelector('[data-pd-auto-state]');
        var label = document.querySelector('[data-pd-auto-label]');
        var msg = document.querySelector('[data-pd-auto-msg]');
        var startButton = document.querySelector('[data-pd-auto-start]');
        var statusUrl = @json(route('admin.nodes.view.wings-install', $node->id));
        var timer = null;
        var texts = {
            running: @json('Installing…'),
            done: @json('Wings is installed and started.'),
            failed: @json('The installation failed.'),
            heart: @json('The heart of the node on the Nodes page turns green within a minute.'),
        };

        document.querySelectorAll('[data-pd-auto-methods] input').forEach(function (input) {
            input.addEventListener('change', function () {
                document.querySelectorAll('[data-pd-auto-methods] label').forEach(function (l) { l.classList.toggle('is-active', l.querySelector('input').checked); });
                document.querySelectorAll('[data-pd-auto-for]').forEach(function (block) { block.hidden = block.getAttribute('data-pd-auto-for') !== input.value; });
            });
        });

        var render = function (status) {
            if (!status || status.state === 'idle') return;
            live.hidden = false;
            var atBottom = term.scrollTop + term.clientHeight >= term.scrollHeight - 30;
            term.textContent = status.log || '';
            if (atBottom) term.scrollTop = term.scrollHeight;
            stateBox.className = 'pd-auto-state ' + status.state;
            label.textContent = texts[status.state] || status.state;
            msg.className = 'pd-auto-msg ' + status.state;
            msg.textContent = status.state === 'failed' ? (status.message || '') : (status.state === 'done' ? texts.heart : '');
            startButton.disabled = status.state === 'running';
            if (status.state === 'running') {
                clearTimeout(timer);
                timer = setTimeout(poll, 1500);
            }
        };
        var poll = function () {
            $.getJSON(statusUrl).done(render).fail(function () { timer = setTimeout(poll, 4000); });
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var data = $(form).serialize();
            startButton.disabled = true;
            $.ajax({ method: 'POST', url: statusUrl, data: data, headers: { 'X-CSRF-TOKEN': @json(csrf_token()) } })
                .done(function (status) {
                    form.querySelector('[name="password"]').value = '';
                    term.textContent = '';
                    render(status);
                })
                .fail(function (xhr) {
                    startButton.disabled = false;
                    var body = xhr.responseJSON || {};
                    var text = body.errors && body.errors[0] && body.errors[0].detail
                        ? body.errors[0].detail
                        : (body.message || @json('The installation could not be started.'));
                    live.hidden = false;
                    stateBox.className = 'pd-auto-state failed';
                    label.textContent = texts.failed;
                    msg.className = 'pd-auto-msg failed';
                    msg.textContent = text;
                });
        });

        poll();

        $('#configTokenBtn').on('click', function () {
            var button = $(this).prop('disabled', true);
            $.ajax({
                method: 'POST',
                url: @json(route('admin.nodes.view.configuration.token', $node->id)),
                headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            }).done(function (data) {
                var url = @json($pdPanelUrl);
                show('new', @json($pdInstaller) + ' --wings --yes --panel-url=' + url + ' --wings-token=', data.token, ' --node-id=' + data.node);
                show('existing', 'cd /etc/pterodactyl && wings configure --panel-url ' + url + ' --token ', data.token, ' --node ' + data.node + @json($pdInsecure) + ' && systemctl restart wings');
                document.querySelector('[data-pd-wg-result]').hidden = false;
                button.find('span').text(@json('Get the command again'));
            }).fail(function () {
                swal({ title: 'Error', text: 'Something went wrong creating your token.', type: 'error' });
            }).always(function () {
                button.prop('disabled', false);
            });
        });
    })();
    </script>
@endsection
