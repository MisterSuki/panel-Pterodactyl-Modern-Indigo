@extends('layouts.admin')

@section('title')
    Web hosting settings
@endsection

@section('content-header')
    <h1>Web hosting settings<small>The web server and the domains.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.hosting.clients') }}">Web hosting</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        @include('admin.hosting._nav')
        @if ($errors->any())<div class="alert alert-danger">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
    </div>
    <div class="col-md-6">
        <form action="{{ route('admin.hosting.settings') }}" method="POST" autocomplete="off">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">The hosting</h3></div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled))><i class="pd-switch-track"></i><span>The web hosting is on</span></label>
                    <p class="text-muted small"><span>Off, clients do not see their sites and no domain is served.</span></p>
                    <div class="form-group">
                        <label for="server_ips">IP addresses of the web server</label>
                        <input type="text" id="server_ips" name="server_ips" class="form-control" value="{{ old('server_ips', $serverIps) }}" placeholder="203.0.113.5, 2001:db8::5">
                        <p class="text-muted small"><span>A domain is only served once its DNS leads to one of these addresses, so nobody can take a name that is not theirs. Clients are told to point their domain here. Leave it empty to serve every domain at once (you then take the responsibility).</span></p>
                    </div>
                    <div class="form-group">
                        <label for="base_domain">Domain of the hosting</label>
                        <input type="text" id="base_domain" name="base_domain" class="form-control" value="{{ old('base_domain', $baseDomain) }}" placeholder="hosting.example.com">
                        <p class="text-muted small"><span>Optional. It is the domain under which sites can get a free name (site.hosting.example.com). It needs a wildcard DNS record (*.hosting.example.com) that leads to the web server.</span></p>
                    </div>
                    <label class="pd-switch"><input type="checkbox" name="auto_subdomain" value="1" @checked(old('auto_subdomain', $autoSubdomain))><i class="pd-switch-track"></i><span>Give every site a free name under the domain of the hosting</span></label>
                    <p class="text-muted small"><span>On, a buyer can pick a name under the domain of the hosting instead of bringing their own domain, and a site made without a domain gets one. Off, every site needs a domain of its own.</span></p>
                </div>
                <div class="box-footer">{!! csrf_field() !!}<button type="submit" class="btn btn-success btn-sm pull-right"><span>Save</span></button></div>
            </div>
        </form>
    </div>
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">The web server (Caddy)</h3></div>
            <div class="box-body">
                @if ($token)
                    <div class="alert alert-success">
                        <strong><span>Your new token. Copy it now: it is not shown again.</span></strong>
                        <pre style="margin:8px 0 0;user-select:all">{{ $token }}</pre>
                    </div>
                @endif
                <p><span>Caddy fetches the list of domains here, with a token:</span></p>
                <pre style="user-select:all">{{ $configUrl }}</pre>
                <p class="text-muted small">
                    @if ($hasToken)<span>A token exists. Making a new one stops the old one.</span>@else<span>No token yet.</span>@endif
                </p>
                <form action="{{ route('admin.hosting.settings.token') }}" method="POST" onsubmit="return confirm('Make a new token? The web server stops working until it has the new one.')">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-key"></i> <span>Make a new token</span></button>
                </form>
                <hr>
                <p><strong><span>Installation on the web server</span></strong></p>
                <ol class="small text-muted" style="padding-left:18px">
                    <li><span>Install Caddy and add this line to its main file (/etc/caddy/Caddyfile):</span> <code>import /etc/caddy/panel-sites.caddy</code></li>
                    <li><span>Copy the script docs/hosting/panel-proxy-sync.sh from the panel to the server, and run it every minute (a systemd timer or cron) with the address above and the token.</span></li>
                    <li><span>Open ports 80 and 443 on the web server. Caddy makes and renews the HTTPS certificate of every domain by itself.</span></li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
