@extends('layouts.admin')

@section('title')
    Shop orders
@endsection

@section('content-header')
    <h1>Orders<small>What people bought.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.shop.offers') }}">Shop</a></li>
        <li class="active">Orders</li>
    </ol>
@endsection

@section('content')
@php
    $labels = ['active' => 'Paid', 'expired' => 'Ran out', 'provisioning' => 'Being made', 'failed' => 'Not delivered', 'cancelled' => 'Cancelled'];
    $classes = ['active' => 'success', 'expired' => 'warning', 'provisioning' => 'info', 'failed' => 'danger', 'cancelled' => 'default'];
    $canManage = auth()->user()->hasAdminPermission('shop.manage');
@endphp
<div class="row">
    <div class="col-xs-12">
        @include('admin.shop._nav')
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Orders</h3>
                <div class="box-tools">
                    <form action="{{ route('admin.shop.orders') }}" method="GET" class="form-inline">
                        <select name="status" class="form-control input-sm" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach ($labels as $key => $label)
                                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>#</th>
                            <th>Offer</th>
                            <th>Buyer</th>
                            <th>Price</th>
                            <th>State</th>
                            <th>Paid until</th>
                            <th>Server</th>
                            @if ($canManage)<th class="text-right">Actions</th>@endif
                        </tr>
                        @forelse ($orders as $order)
                            <tr>
                                <td><code>{{ $order->id }}</code></td>
                                <td><strong>{{ $order->offer_name }}</strong>@if (!$order->offer_id) <span class="label label-info"><span>Custom</span></span>@endif@if ($order->renewals > 0)<br><small class="text-muted">{{ $order->renewals }} <span>renewal(s)</span></small>@endif</td>
                                <td><a href="{{ route('admin.users.view', $order->user_id) }}">{{ $order->user?->username ?? '#' . $order->user_id }}</a></td>
                                <td>@if ($order->offer_id){{ number_format($order->price_cents / 100, 2, '.', '') }} {{ $currency }} / {{ $order->duration_days }} <span>days</span>@else{{ number_format($order->resource_cents / 100, 2, '.', '') }} {{ $currency }} / <span>month</span>@endif</td>
                                <td><span class="label label-{{ $classes[$order->status] ?? 'default' }}"><span>{{ $labels[$order->status] ?? $order->status }}</span></span></td>
                                <td>{{ $order->expires_at ? $order->expires_at->format('Y-m-d H:i') : '—' }}</td>
                                <td>@if ($order->server)<a href="{{ route('admin.servers.view', $order->server_id) }}"><i class="fa fa-server"></i> {{ $order->server->uuidShort }}</a>@else — @endif</td>
                                @if ($canManage)
                                    <td class="text-right" style="white-space:nowrap">
                                        @if (in_array($order->status, ['active', 'expired']))
                                            <form action="{{ route('admin.shop.orders.action', $order->id) }}" method="POST" style="display:inline">{!! csrf_field() !!}<input type="hidden" name="action" value="renew"><button class="btn btn-xs btn-success" title="Renew / extend"><i class="fa fa-refresh"></i></button></form>
                                            <form action="{{ route('admin.shop.orders.action', $order->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Cancel this order? The server is suspended and no longer billed.')">{!! csrf_field() !!}<input type="hidden" name="action" value="cancel"><button class="btn btn-xs btn-warning" title="Cancel"><i class="fa fa-ban"></i></button></form>
                                            <form action="{{ route('admin.shop.orders.action', $order->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Refund the buyer as credit and cancel the order?')">{!! csrf_field() !!}<input type="hidden" name="action" value="refund"><button class="btn btn-xs btn-primary" title="Refund as credit"><i class="fa fa-money"></i></button></form>
                                        @endif
                                        <button type="submit" form="pd-del-order-{{ $order->id }}" class="btn btn-xs btn-danger" title="Delete the order and its server" onclick="return confirm('Delete this order AND its server? The server is removed from the node. This cannot be undone.')"><i class="fa fa-trash"></i></button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="text-center text-muted" style="padding:28px"><span>No order here.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="box-footer with-border"><div class="col-md-12 text-center">{!! $orders->render() !!}</div></div>
            @endif
        </div>
    </div>
</div>
@if ($canManage)
    @foreach ($orders as $order)
        <form id="pd-del-order-{{ $order->id }}" action="{{ route('admin.shop.orders.delete', $order->id) }}" method="POST">{!! csrf_field() !!}{!! method_field('DELETE') !!}</form>
    @endforeach
@endif
@endsection
