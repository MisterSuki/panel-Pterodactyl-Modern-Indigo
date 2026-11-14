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
                    <li>Create a website with the form below (or sell it with an offer in the shop).</li>
                    <li>The client finds a <strong>Website</strong> tab: the kind of site (PHP, Node.js, Python), domains, HTTPS and CMS.</li>
                </ol>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.web-hosting.eggs') }}" method="POST">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success btn-sm">{{ $eggsInstalled ? 'Update the eggs' : 'Install the eggs' }}</button>
                </form>
            </div>
        </div>

        @if ($eggsInstalled)
            <form action="{{ route('admin.web-hosting.create') }}" method="POST">
                {!! csrf_field() !!}
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plus"></i> <span>Create a website</span></h3></div>
                    <div class="box-body">
                        <p class="pd-web-help" style="margin: 0 0 12px;"><span>No egg to choose: the client picks the kind of their site (PHP / HTML, Node.js, Python) in their panel, and can change it later. The node and the port are found alone.</span></p>
                        <div class="form-group">
                            <label for="pdSiteName">Name of the site</label>
                            <input type="text" id="pdSiteName" name="name" class="form-control" maxlength="60" required value="{{ old('name') }}" placeholder="monsite.fr">
                        </div>
                        <div class="form-group">
                            <label for="pdSiteUser">Owner (email or username)</label>
                            <input type="text" id="pdSiteUser" name="user" class="form-control" required value="{{ old('user') }}" list="pdSiteUsers">
                            <datalist id="pdSiteUsers">
                                @foreach (\Pterodactyl\Models\User::query()->orderBy('username')->limit(500)->get(['username', 'email']) as $person)
                                    <option value="{{ $person->email }}">{{ $person->username }}</option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="row">
                            <div class="col-xs-6 form-group">
                                <label for="pdSiteMemory">Memory</label>
                                <div style="display: flex; gap: 6px;">
                                    <input type="number" id="pdSiteMemory" name="memory" class="form-control" min="0.125" step="any" required value="{{ old('memory', 1) }}">
                                    <select name="memory_unit" class="form-control" style="width: 70px;"><option value="GB">GB</option><option value="MB" @selected(old('memory_unit') === 'MB')>MB</option></select>
                                </div>
                            </div>
                            <div class="col-xs-6 form-group">
                                <label for="pdSiteDisk">Disk</label>
                                <div style="display: flex; gap: 6px;">
                                    <input type="number" id="pdSiteDisk" name="disk" class="form-control" min="0.125" step="any" required value="{{ old('disk', 5) }}">
                                    <select name="disk_unit" class="form-control" style="width: 70px;"><option value="GB">GB</option><option value="MB" @selected(old('disk_unit') === 'MB')>MB</option></select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-6 form-group">
                                <label for="pdSiteCpu">CPU (%)</label>
                                <input type="number" id="pdSiteCpu" name="cpu" class="form-control" min="0" required value="{{ old('cpu', 100) }}">
                                <p class="pd-web-help">100 = <span>one core</span>, 0 = <span>no limit</span></p>
                            </div>
                            <div class="col-xs-6 form-group">
                                <label for="pdSiteNode">Node</label>
                                <select id="pdSiteNode" name="node_id" class="form-control">
                                    <option value="">Automatic</option>
                                    @foreach ($nodes as $entry)
                                        <option value="{{ $entry['node']->id }}" @selected((string) old('node_id') === (string) $entry['node']->id)>{{ $entry['node']->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-plus"></i> <span>Create the website</span></button>
                    </div>
                </div>
            </form>
        @endif

        <form action="{{ route('admin.web-hosting.settings') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">Settings</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pdWebMax">Domains per site</label>
                        <input type="number" id="pdWebMax" name="max_domains" class="form-control" min="1" max="50" required value="{{ old('max_domains', $maxDomains) }}">
                        <p class="pd-web-help">Own domains and free subdomains together. The www. of a domain comes with it and does not count.</p>
                    </div>
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
