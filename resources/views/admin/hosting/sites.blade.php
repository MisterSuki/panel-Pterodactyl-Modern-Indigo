@extends('layouts.admin')

@section('title')
    Sites
@endsection

@section('content-header')
    <h1>Sites<small>Every site of the web hosting.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.hosting.clients') }}">Web hosting</a></li>
        <li class="active">Sites</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        @include('admin.hosting._nav')
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Sites</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr><th>Site</th><th>Client</th><th>Domains</th><th>PHP</th><th>State</th><th></th></tr>
                        @forelse ($sites as $site)
                            <tr>
                                <td><strong>{{ $site->name }}</strong></td>
                                <td><a href="{{ route('admin.hosting.account', $site->account_id) }}">{{ $site->account?->user?->username ?? '—' }}</a></td>
                                <td>
                                    @forelse ($site->domains as $domain)
                                        <div>{{ $domain->domain }} @if ($domain->status !== 'verified')<span class="label label-warning"><span>Waiting for its DNS</span></span>@endif</div>
                                    @empty —
                                    @endforelse
                                </td>
                                <td>{{ $site->php_version ?? '—' }}</td>
                                <td>@if ($site->status === 'active')<span class="label label-success"><span>Running</span></span>@else<span class="label label-warning">{{ $site->status }}</span>@endif</td>
                                <td class="text-right">@if ($site->server_id)<a href="{{ route('admin.servers.view', $site->server_id) }}" class="btn btn-xs btn-default"><i class="fa fa-server"></i></a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted" style="padding:28px"><span>No site yet.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($sites->hasPages())
                <div class="box-footer with-border"><div class="col-md-12 text-center">{!! $sites->render() !!}</div></div>
            @endif
        </div>
    </div>
</div>
@endsection
