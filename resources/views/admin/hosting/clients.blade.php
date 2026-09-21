@extends('layouts.admin')

@section('title')
    Web hosting
@endsection

@section('content-header')
    <h1>Web hosting<small>Your clients and their sites.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Web hosting</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        @include('admin.hosting._nav')
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Clients</h3>
                @if (auth()->user()->hasAdminPermission('hosting.manage'))
                    <div class="box-tools"><a href="{{ route('admin.hosting.clients.new') }}" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> <span>New client</span></a></div>
                @endif
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr><th>Client</th><th>Plan</th><th>Sites</th><th>State</th><th class="text-right">Since</th></tr>
                        @forelse ($accounts as $account)
                            <tr>
                                <td><a href="{{ route('admin.hosting.account', $account->id) }}"><strong>{{ $account->user?->username ?? '#' . $account->user_id }}</strong></a><br><small class="text-muted">{{ $account->user?->email }}</small></td>
                                <td>{{ $account->plan_name }}</td>
                                <td>{{ $account->sites_count }} / {{ $account->max_sites }}</td>
                                <td>@if ($account->status === 'active')<span class="label label-success"><span>Active</span></span>@else<span class="label label-danger"><span>Suspended</span></span>@endif</td>
                                <td class="text-right">{{ $account->created_at?->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted" style="padding:28px"><span>No client yet. Make a plan, then a first client.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($accounts->hasPages())
                <div class="box-footer with-border"><div class="col-md-12 text-center">{!! $accounts->render() !!}</div></div>
            @endif
        </div>
    </div>
</div>
@endsection
