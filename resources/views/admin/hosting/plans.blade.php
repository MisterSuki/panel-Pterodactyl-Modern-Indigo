@extends('layouts.admin')

@section('title')
    Hosting plans
@endsection

@section('content-header')
    <h1>Plans<small>What a client can have.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.hosting.clients') }}">Web hosting</a></li>
        <li class="active">Plans</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        @include('admin.hosting._nav')
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Plans</h3>
                @if (auth()->user()->hasAdminPermission('hosting.manage'))
                    <div class="box-tools"><a href="{{ route('admin.hosting.plans.new') }}" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> <span>New plan</span></a></div>
                @endif
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr><th>Name</th><th>Sites</th><th>Domains per site</th><th>Each site</th><th>PHP</th><th>State</th><th></th></tr>
                        @forelse ($plans as $plan)
                            <tr>
                                <td><strong>{{ $plan->name }}</strong></td>
                                <td>{{ $plan->max_sites }}</td>
                                <td>{{ $plan->max_domains }}</td>
                                <td>{{ $plan->memory }} MB &middot; {{ $plan->disk }} MB &middot; {{ $plan->cpu ?: '∞' }}% CPU</td>
                                <td>{{ implode(', ', $plan->versions()) ?: '—' }}</td>
                                <td>@if ($plan->enabled)<span class="label label-success"><span>On offer</span></span>@else<span class="label label-default"><span>Hidden</span></span>@endif</td>
                                <td class="text-right">@if (auth()->user()->hasAdminPermission('hosting.manage'))<a href="{{ route('admin.hosting.plans.edit', $plan->id) }}" class="btn btn-xs btn-primary"><i class="fa fa-wrench"></i></a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted" style="padding:28px"><span>No plan yet. Make the first one to start.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
