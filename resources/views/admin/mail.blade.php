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
</style>

<div class="row">
    <div class="col-md-7">
        <div class="box box-primary">
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

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">Install the mail server</h3></div>
            <div class="box-body">
                <ol class="pd-mail-steps">
                    <li>Save the two names above, and point both to the machine with an <strong>A</strong> record.</li>
                    <li>At the host of the machine, set its reverse DNS (PTR) to the name of the mail server, and check that outgoing port 25 is open.</li>
                    <li>Make a key below, then run the command as root on the machine. It needs Docker, Nginx and the ports 25, 465, 587 and 993.</li>
                    <li>The client finds an <strong>Email</strong> tab on their website, once their own domain works.</li>
                </ol>
                @if ($token)
                    <p class="pd-mail-help" style="margin-top:12px">The key is shown only now. Run:</p>
                    <code class="pd-mail-cmd">bash &lt;(curl -s https://raw.githubusercontent.com/{{ config('pterodactyl.theme.repo') }}/main/install.sh) --mail --panel-url={{ $panelUrl }} --mail-token={{ $token }}</code>
                @endif
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.mail.token') }}" method="POST" style="display:inline">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-default btn-sm" @if ($hostname === '') disabled @endif>
                        {{ $hasToken ? 'Make a new key (the old one stops working)' : 'Make a key' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
