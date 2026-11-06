@extends('layouts.admin')

@section('title')
    Web Hosting
@endsection

@section('content-header')
    <h1>Web Hosting<small>Websites with their domain and HTTPS, without a port in the address.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Web Hosting</li>
    </ol>
@endsection

@section('content')
<style>
    .pd-web-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 6px 0 0; }
    .pd-web-steps { margin: 0; padding-left: 20px; }
    .pd-web-steps li { margin-bottom: 6px; }
    .pd-web-error { display: block; font-size: 12px; color: rgba(255, 170, 160, 0.9); }
</style>

<div class="row">
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Nodes</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr><th>Node</th><th>Web agent</th><th>Version</th></tr>
                        @foreach ($nodes as $row)
                            <tr>
                                <td><a href="{{ route('admin.nodes.view', $row['node']->id) }}">{{ $row['node']->name }}</a></td>
                                <td>
                                    @if ($row['agent'] === null)
                                        <span class="label label-default">Not installed</span>
                                    @elseif ($row['agent']['online'])
                                        <span class="label label-success">Connected</span>
                                    @else
                                        <span class="label label-danger">Not answering</span>
                                    @endif
                                </td>
                                <td>{{ $row['agent']['version'] ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <p class="pd-web-help">The panel updates the machine it runs on. On any other node, run this as root:</p>
                <code>bash &lt;(curl -s https://raw.githubusercontent.com/{{ config('pterodactyl.theme.repo') }}/main/install.sh) --web</code>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">Domains</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr><th>Domain</th><th>Site</th><th>DNS</th><th>HTTPS</th></tr>
                        @forelse ($domains as $domain)
                            <tr>
                                <td><code>{{ $domain->hostname }}</code></td>
                                <td><a href="{{ route('admin.servers.view', $domain->server_id) }}">{{ $domain->server->name }}</a></td>
                                <td>
                                    @if ($domain->ready())
                                        <span class="label label-success">OK</span>
                                    @else
                                        <span class="label label-warning">Waiting</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($domain->ssl_status === 'active')
                                        <span class="label label-success">Active</span>
                                    @elseif ($domain->ssl_status === 'failed')
                                        <span class="label label-danger">Failed</span>
                                        <span class="pd-web-error">{{ $domain->ssl_error }}</span>
                                    @else
                                        <span class="label label-default">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">No website has a domain yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title">Start selling websites</h3></div>
            <div class="box-body">
                <ol class="pd-web-steps">
                    <li>Install the eggs, with the button below. They are in the <strong>Web Hosting</strong> nest.</li>
                    <li>Add a database host, so a CMS can have its database.</li>
                    <li>Create a server from one of the eggs, with at least 1 database, or an offer in the shop.</li>
                    <li>The client finds a <strong>Website</strong> tab: domains, HTTPS and CMS.</li>
                </ol>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.web-hosting.eggs') }}" method="POST">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success btn-sm">{{ $eggsInstalled ? 'Update the eggs' : 'Install the eggs' }}</button>
                </form>
            </div>
        </div>

        <form action="{{ route('admin.web-hosting.settings') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">Certificates</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pdWebEmail">Let's Encrypt email</label>
                        <input type="email" id="pdWebEmail" name="email" class="form-control" maxlength="191" value="{{ old('email', $email) }}" placeholder="you@example.com">
                        <p class="pd-web-help">Let's Encrypt writes here if a certificate has a problem. Optional.</p>
                    </div>
                </div>
                <div class="box-footer"><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
            </div>
        </form>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">Good to know</h3></div>
            <div class="box-body">
                <p>Once a site has a domain, its port is closed to the outside: visitors only reach it through the domain.</p>
                <p style="margin-bottom:0">Email is not included: use a mail service for the mailboxes of the clients.</p>
            </div>
        </div>
    </div>
</div>
@endsection
