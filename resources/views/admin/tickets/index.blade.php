@extends('layouts.admin')

@section('title')
    Support Tickets
@endsection

@section('content-header')
    <h1>Support Tickets<small>The requests of your users.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Tickets</li>
    </ol>
@endsection

@section('content')
@php
    $tabs = ['active' => 'To handle', 'open' => 'Waiting for the staff', 'answered' => 'Answered', 'closed' => 'Closed', 'all' => 'All'];
    $statusLabels = ['open' => 'Waiting for the staff', 'answered' => 'Answered', 'closed' => 'Closed'];
    $priorityLabels = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
@endphp
<div class="row">
    <div class="col-xs-12">
        <div class="pd-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.tickets', array_filter(['status' => $key, 'q' => request('q'), 'mine' => request('mine')])) }}" class="{{ $status === $key ? 'active' : '' }}">
                    <span>{{ $label }}</span>
                    @if (isset($counts[$key]))<b>{{ $counts[$key] }}</b>@endif
                </a>
            @endforeach
        </div>
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Tickets</h3>
                <div class="box-tools search01">
                    <form action="{{ route('admin.tickets') }}" method="GET">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <div class="input-group input-group-sm">
                            <input type="text" name="q" class="form-control pull-right" value="{{ request('q') }}" placeholder="Search a ticket or a user">
                            <div class="input-group-btn">
                                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i></button>
                                <a href="{{ route('admin.tickets', array_filter(['status' => $status, 'mine' => request('mine') ? null : 1])) }}" class="btn btn-sm {{ request('mine') ? 'btn-primary' : 'btn-default' }}" style="margin-left:-1px;border-radius:0 3px 3px 0"><span>Mine</span></a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>#</th>
                            <th>Subject</th>
                            <th>User</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Handled by</th>
                            <th class="text-right">Last activity</th>
                        </tr>
                        @forelse ($tickets as $ticket)
                            <tr>
                                <td><code>{{ $ticket->id }}</code></td>
                                <td>
                                    <a href="{{ route('admin.tickets.view', $ticket->id) }}"><strong>{{ $ticket->subject }}</strong></a>
                                    @if ($ticket->server)<small class="text-muted"><i class="fa fa-server"></i> {{ $ticket->server->name }}</small>@endif
                                </td>
                                <td><a href="{{ route('admin.users.view', $ticket->user_id) }}">{{ $ticket->user->username }}</a></td>
                                <td><span class="pd-pill pd-prio-{{ $ticket->priority }}"><span>{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span></span></td>
                                <td><span class="pd-pill pd-status-{{ $ticket->status }}"><span>{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span></span></td>
                                <td>{{ $ticket->assignee?->username ?? '—' }}</td>
                                <td class="text-right"><time title="{{ $ticket->last_message_at }}">{{ ($ticket->last_message_at ?? $ticket->created_at)->diffForHumans() }}</time></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted" style="padding:28px"><span>No ticket here.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($tickets->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $tickets->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
