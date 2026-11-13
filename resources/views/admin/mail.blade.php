@extends('layouts.admin')

@section('title')
    Email
@endsection

@section('content-header')
    <h1>Email<small>Mailboxes on the domain of the websites, with a webmail.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Email</li>
    </ol>
@endsection

@section('content')
<style>
    .pd-mail-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 6px 0 0; }
    .pd-mail-steps { margin: 0; padding-left: 20px; }
    .pd-mail-steps li { margin-bottom: 8px; }
    .pd-mail-cmd { display: block; white-space: pre-wrap; word-break: break-all; padding: 10px 12px; margin-top: 8px; user-select: all; }
    .pd-mail-error { display: block; font-size: 12px; color: rgba(255, 170, 160, 0.9); margin-top: 4px; }
    .pd-mail-state td:first-child { width: 45%; color: rgba(235, 235, 240, 0.7); }
    .pd-step { display: flex; gap: 12px; padding: 12px 4px; border-bottom: 1px solid var(--pd-border, #1f1f1f); }
    .pd-step:last-child { border-bottom: 0; }
    .pd-step > .fa { font-size: 18px; margin-top: 1px; width: 18px; text-align: center; color: #707070; }
    .pd-step.is-done > .fa { color: #3ecf8e; }
    .pd-step.is-now > .fa { color: rgb(var(--pd-accent-400)); }
    .pd-step.is-wait > .fa { color: #f5a524; }
    .pd-step.is-todo { opacity: 0.55; }
    .pd-step.is-done .pd-step-text { display: none; }
    .pd-step.is-now { background: rgb(var(--pd-accent-500) / 0.06); border-radius: 8px; padding-left: 10px; padding-right: 10px; }
    .pd-step-title { margin: 0 0 4px; color: var(--pd-text, #ededed); font-weight: 600; }
    .pd-step-tag { display: inline-block; margin-left: 6px; padding: 0 8px; border-radius: 999px; border: 1px solid #2e2e2e; font-size: 11px; font-weight: 500; color: #a1a1a1; }
    .pd-step.is-done .pd-step-tag { color: #3ecf8e; border-color: #1c4733; }
    .pd-step.is-now .pd-step-tag { color: rgb(var(--pd-accent-300)); border-color: rgb(var(--pd-accent-700)); }
    .pd-step.is-wait .pd-step-tag { color: #f5a524; border-color: #4d3711; }
    .pd-step-text { margin: 0 0 4px; font-size: 13px; color: #a1a1a1; }
    .pd-mail-copy { display: flex; gap: 8px; align-items: flex-start; }
    .pd-mail-copy .pd-mail-cmd { flex: 1; margin-top: 0; }
</style>

<div class="row">
    <div class="col-md-7">
        <div class="box box-primary pd-mail-guide">
            <div class="box-header with-border"><h3 class="box-title">Setup</h3></div>
            <div class="box-body">
                @php
                    $icons = ['done' => 'fa-check-circle', 'now' => 'fa-arrow-circle-right', 'wait' => 'fa-clock-o', 'todo' => 'fa-circle-o'];
                    $labels = ['done' => 'Done', 'now' => 'To do now', 'wait' => 'In progress', 'todo' => 'Later'];
                    $command = $token ? 'bash <(curl -s https://raw.githubusercontent.com/' . config('pterodactyl.theme.repo') . '/main/install.sh) --mail --panel-url=' . $panelUrl . ' --mail-token=' . $token : null;
                @endphp

                {{-- 1. The names --}}
                <div class="pd-step is-{{ $steps['names'] }}">
                    <i class="fa {{ $icons[$steps['names']] }}"></i>
                    <div>
                        <p class="pd-step-title"><span>Choose the names of the mail server and of the webmail</span> <span class="pd-step-tag">{{ $labels[$steps['names']] }}</span></p>
                        <p class="pd-step-text">Fill them in the Settings box on the right, then Save.</p>
                    </div>
                </div>

                {{-- 2. The DNS --}}
                <div class="pd-step is-{{ $steps['dns'] }}">
                    <i class="fa {{ $icons[$steps['dns']] }}"></i>
                    <div>
                        <p class="pd-step-title"><span>Point both names to the machine</span> <span class="pd-step-tag">{{ $labels[$steps['dns']] }}</span></p>
                        <p class="pd-step-text">Where your domain is managed, add an A record for each name, to the IP of the machine that will run the mail server.</p>
                        @if ($hostname !== '' && $webmail !== '')
                            <p class="pd-step-text"><code>{{ $hostname }}</code> → {{ implode(', ', $dns[$hostname] ?? []) ?: '?' }} &nbsp; <code>{{ $webmail }}</code> → {{ implode(', ', $dns[$webmail] ?? []) ?: '?' }}</p>
                        @endif
                    </div>
                </div>

                {{-- 3. The install --}}
                <div class="pd-step is-{{ $steps['install'] }}">
                    <i class="fa {{ $icons[$steps['install']] }}"></i>
                    <div style="min-width:0;flex:1">
                        <p class="pd-step-title"><span>Install the mail server on the machine</span> <span class="pd-step-tag">{{ $labels[$steps['install']] }}</span></p>
                        @if ($steps['install'] === 'done')
                            <p class="pd-step-text">The agent is connected.</p>
                        @elseif ($agent !== null)
                            <p class="pd-step-text">The agent was installed but does not answer any more. On the machine, look at what it says:</p>
                            <code class="pd-mail-cmd">journalctl -u pterodactyl-mail-agent -n 50</code>
                        @else
                            <p class="pd-step-text">1. Click the button below: it makes the key of the agent and shows the command.</p>
                            <p class="pd-step-text">2. Connect to the machine in SSH as root (with PuTTY or the terminal), paste the command and press Enter.</p>
                            <p class="pd-step-text">The machine needs Docker and Nginx (already there if Wings and the panel run on it), and the ports 25, 465, 587 and 993 free.</p>
                            @if ($command)
                                <p class="pd-step-text" style="margin-top:10px"><strong>Your command</strong> <span class="pd-mail-help">(shown only now: copy it before leaving the page)</span></p>
                                <div class="pd-mail-copy">
                                    <code class="pd-mail-cmd" id="pdMailCommand">{{ $command }}</code>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('pdMailCommand').textContent.trim()).then(() => { this.textContent = '✓'; })">Copy</button>
                                </div>
                            @endif
                            <form action="{{ route('admin.mail.token') }}" method="POST" style="margin-top:10px">
                                {!! csrf_field() !!}
                                <button type="submit" class="btn {{ $command ? 'btn-default' : 'btn-primary' }} btn-sm" @if ($hostname === '') disabled @endif>
                                    {{ $command ? 'Make another key' : 'Show the install command' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- 4. The start --}}
                <div class="pd-step is-{{ $steps['start'] }}">
                    <i class="fa {{ $icons[$steps['start']] }}"></i>
                    <div>
                        <p class="pd-step-title"><span>The mail server starts</span> <span class="pd-step-tag">{{ $labels[$steps['start']] }}</span></p>
                        <p class="pd-step-text">The machine downloads the mail server and the webmail and asks for their certificates: a few minutes, nothing to do. This page shows it when it is done.</p>
                    </div>
                </div>

                {{-- 5. The reverse DNS --}}
                <div class="pd-step is-{{ $steps['ptr'] }}">
                    <i class="fa {{ $icons[$steps['ptr']] }}"></i>
                    <div>
                        <p class="pd-step-title"><span>Set the reverse DNS of the machine</span> <span class="pd-step-tag">{{ $labels[$steps['ptr']] }}</span></p>
                        <p class="pd-step-text">In the customer area of the host of the machine (often under Network, IP or Reverse DNS), set the reverse of the IP to the name of the mail server. Without it, Gmail and Outlook refuse the mail.</p>
                        @if ($ip !== '')
                            <p class="pd-step-text"><code>{{ $ip }}</code> → <span>now:</span> <code>{{ $ptr ?: '—' }}</code> &nbsp; <span>wanted:</span> <code>{{ $hostname }}</code></p>
                        @endif
                    </div>
                </div>

                {{-- 6. Port 25 --}}
                <div class="pd-step is-{{ $steps['port25'] }}">
                    <i class="fa {{ $icons[$steps['port25']] }}"></i>
                    <div>
                        <p class="pd-step-title"><span>Outgoing port 25 open</span> <span class="pd-step-tag">{{ $labels[$steps['port25']] }}</span></p>
                        @if ($steps['port25'] === 'now')
                            <p class="pd-step-text">The host of the machine blocks it: mail comes in but cannot go out. Ask their support to open outgoing port 25 (SMTP).</p>
                        @else
                            <p class="pd-step-text">Tested by the agent once it is installed. Many hosts block it at first.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">Mail server</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table pd-mail-state">
                    <tbody>
                        <tr>
                            <td>Agent</td>
                            <td>
                                @if ($agent === null)
                                    <span class="label label-default">Not installed</span>
                                @elseif ($agent['online'])
                                    <span class="label label-success">Connected</span>
                                    <small class="pd-mail-help">{{ $agent['version'] ?? '' }}</small>
                                @else
                                    <span class="label label-danger">Not answering</span>
                                @endif
                            </td>
                        </tr>
                        @if ($agent !== null)
                            <tr>
                                <td>SMTP and IMAP</td>
                                <td>
                                    @if (!empty($agent['running']))
                                        <span class="label label-success">Running</span>
                                    @else
                                        <span class="label label-warning">Stopped</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>Certificates</td>
                                <td>
                                    <span class="label {{ !empty($agent['certificate']) ? 'label-success' : 'label-warning' }}">{{ $hostname ?: 'mail' }}</span>
                                    <span class="label {{ !empty($agent['webmail_certificate']) ? 'label-success' : 'label-warning' }}">{{ $webmail ?: 'webmail' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td>Sending on port 25</td>
                                <td>
                                    @if (($agent['outbound_25'] ?? null) === true)
                                        <span class="label label-success">Open</span>
                                    @elseif (($agent['outbound_25'] ?? null) === false)
                                        <span class="label label-danger">Blocked</span>
                                        <span class="pd-mail-error">The host of the machine blocks outgoing port 25: mail can be received but not sent. Ask them to open it.</span>
                                    @else
                                        <span class="label label-default">Not tested yet</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td>Reverse DNS (PTR) of {{ $ip ?: 'the machine' }}</td>
                            <td>
                                @if ($ip === '')
                                    <span class="label label-default">Unknown</span>
                                @elseif ($ptr !== null && $ptr === $hostname)
                                    <span class="label label-success">{{ $ptr }}</span>
                                @else
                                    <span class="label label-warning">{{ $ptr ?: 'None' }}</span>
                                    <span class="pd-mail-error">Set it to <strong>{{ $hostname ?: 'the name of the mail server' }}</strong> at the host of the machine, or Gmail and Outlook will reject the mail.</span>
                                @endif
                            </td>
                        </tr>
                        @foreach (array_filter([$hostname, $webmail]) as $name)
                            <tr>
                                <td>DNS of {{ $name }}</td>
                                <td>
                                    @if (!empty($dns[$name]))
                                        <span class="label {{ $ip === '' || in_array($ip, $dns[$name], true) ? 'label-success' : 'label-warning' }}">{{ implode(', ', $dns[$name]) }}</span>
                                    @else
                                        <span class="label label-danger">No A record</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (!empty($agent['errors']))
                    <div class="box-body">
                        @foreach ($agent['errors'] as $error)
                            <span class="pd-mail-error">{{ $error }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">Domains with email</h3><span class="pull-right pd-mail-help">{{ $boxes }} mailboxes</span></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr><th>Domain</th><th>Site</th><th>Mailboxes</th><th>DKIM</th></tr>
                        @forelse ($domains as $domain)
                            <tr>
                                <td><code>{{ $domain->hostname }}</code></td>
                                <td>
                                    @if ($domain->server)
                                        <a href="{{ route('admin.servers.view', $domain->server_id) }}">{{ $domain->server->name }}</a>
                                    @endif
                                </td>
                                <td>{{ $domain->boxes_count }}</td>
                                <td>
                                    @if ($domain->dkim)
                                        <span class="label label-success">Ready</span>
                                    @else
                                        <span class="label label-default">Waiting for the agent</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">No site has email yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <form action="{{ route('admin.mail.settings') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box box-success">
                <div class="box-header with-border"><h3 class="box-title">Settings</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pdMailHost">Name of the mail server</label>
                        <input type="text" id="pdMailHost" name="hostname" class="form-control" required maxlength="191" value="{{ old('hostname', $hostname) }}" placeholder="mail.example.com">
                        <p class="pd-mail-help">The MX of every domain, and the IMAP and SMTP server in the apps. An A record must lead it to the machine of the mail server.</p>
                    </div>
                    <div class="form-group">
                        <label for="pdMailWebmail">Name of the webmail</label>
                        <input type="text" id="pdMailWebmail" name="webmail" class="form-control" required maxlength="191" value="{{ old('webmail', $webmail) }}" placeholder="webmail.example.com">
                        <p class="pd-mail-help">Also an A record to the same machine.</p>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-6">
                            <label for="pdMailBoxes">Mailboxes per site</label>
                            <input type="number" id="pdMailBoxes" name="max_boxes" class="form-control" min="1" max="100" required value="{{ old('max_boxes', $maxBoxes) }}">
                        </div>
                        <div class="form-group col-xs-6">
                            <label for="pdMailQuota">Size of a mailbox (MB)</label>
                            <input type="number" id="pdMailQuota" name="quota_mb" class="form-control" min="50" max="102400" required value="{{ old('quota_mb', $quotaMb) }}">
                        </div>
                    </div>
                </div>
                <div class="box-footer"><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
            </div>
        </form>

    </div>
</div>
@endsection
