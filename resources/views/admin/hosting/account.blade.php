@extends('layouts.admin')

@section('title')
    Hosting of {{ $account->user?->username }}
@endsection

@section('content-header')
    <h1>{{ $account->user?->username ?? '#' . $account->user_id }}<small>{{ $account->plan_name }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.hosting.clients') }}">Web hosting</a></li>
        <li class="active">{{ $account->user?->username }}</li>
    </ol>
@endsection

@section('content')
@php $canManage = auth()->user()->hasAdminPermission('hosting.manage'); @endphp
<div class="row">
    <div class="col-xs-12">
        @include('admin.hosting._nav')
        @if ($errors->any())<div class="alert alert-danger">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
    </div>
    <div class="col-md-8">
        @forelse ($account->sites as $site)
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $site->name }}</h3>
                    <div class="box-tools">
                        @if ($site->server) <a href="{{ route('admin.servers.view', $site->server_id) }}" class="btn btn-xs btn-default"><i class="fa fa-server"></i> <span>Server</span></a> @endif
                        @if ($canManage)
                            <button type="submit" form="pd-del-site-{{ $site->id }}" class="btn btn-xs btn-danger" onclick="return confirm('Delete this site with its server and its files? This cannot be undone.')"><i class="fa fa-trash"></i></button>
                        @endif
                    </div>
                </div>
                <div class="box-body">
                    <p style="margin-bottom:8px">
                        @if ($site->status === 'active')<span class="label label-success"><span>Running</span></span>@else<span class="label label-warning">{{ $site->status }}</span>@endif
                        @if ($site->php_version)<span class="label label-default">PHP {{ $site->php_version }}</span>@endif
                    </p>
                    @forelse ($site->domains as $domain)
                        <div>
                            <i class="fa fa-globe"></i> {{ $domain->domain }}@if ($domain->include_www) <small class="text-muted">+ www</small>@endif
                            @if ($domain->is_primary)<small class="text-muted">(<span>main</span>)</small>@endif
                            @if ($domain->status === 'verified')<span class="label label-success"><span>Served</span></span>@else<span class="label label-warning"><span>Waiting for its DNS</span></span>@endif
                        </div>
                    @empty
                        <p class="text-muted" style="margin:0"><span>No domain yet.</span></p>
                    @endforelse
                </div>
            </div>
            @if ($canManage)
                <form id="pd-del-site-{{ $site->id }}" action="{{ route('admin.hosting.sites.delete', $site->id) }}" method="POST">{!! csrf_field() !!}{!! method_field('DELETE') !!}</form>
            @endif
        @empty
            <div class="box box-primary"><div class="box-body text-center text-muted" style="padding:28px"><span>This client has no site yet.</span></div></div>
        @endforelse
    </div>
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Account</h3></div>
            <div class="box-body">
                <dl class="pd-details">
                    <dt>Client</dt><dd><a href="{{ route('admin.users.view', $account->user_id) }}">{{ $account->user?->username }}</a><small>{{ $account->user?->email }}</small></dd>
                    <dt>Plan</dt><dd>{{ $account->plan_name }}</dd>
                    <dt>Sites</dt><dd>{{ $account->sites->count() }} / {{ $account->max_sites }}</dd>
                    <dt>Domains per site</dt><dd>{{ $account->max_domains }}</dd>
                    <dt>State</dt><dd>@if ($account->status === 'active')<span class="label label-success"><span>Active</span></span>@else<span class="label label-danger"><span>Suspended</span></span>@endif</dd>
                </dl>
            </div>
            @if ($canManage)
                <div class="box-footer">
                    <form action="{{ route('admin.hosting.account.toggle', $account->id) }}" method="POST">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-sm {{ $account->status === 'active' ? 'btn-danger' : 'btn-success' }}"><span>{{ $account->status === 'active' ? 'Suspend the account' : 'Give it back' }}</span></button>
                    </form>
                </div>
            @endif
        </div>
        @if ($canManage && $account->sites->count() < $account->max_sites)
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Add a site</h3></div>
                <form action="{{ route('admin.hosting.account.sites', $account->id) }}" method="POST">
                    <div class="box-body">
                        <div class="form-group"><label for="site_name">Name</label><input type="text" id="site_name" name="site_name" class="form-control" maxlength="80" required></div>
                        <div class="form-group"><label for="domain">Domain</label><input type="text" id="domain" name="domain" class="form-control" placeholder="example.com"></div>
                    </div>
                    <div class="box-footer">{!! csrf_field() !!}<button type="submit" class="btn btn-primary btn-sm pull-right"><span>Make the site</span></button></div>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
