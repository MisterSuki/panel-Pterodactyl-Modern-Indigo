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
                                <td>
                                    <strong>{{ $order->offer_name }}</strong>
                                    @if (!$order->offer_id)<span class="label label-info"><span>Custom</span></span>@endif
                                    @if ($order->renewals > 0)<br><small class="text-muted">{{ $order->renewals }} <span>renewal(s)</span></small>@endif
                                </td>
                                <td><a href="{{ route('admin.users.view', $order->user_id) }}">{{ $order->user?->username ?? '#' . $order->user_id }}</a></td>
                                <td>@if ($order->offer_id){{ number_format($order->price_cents / 100, 2, '.', '') }} {{ $currency }} / {{ $order->duration_days }} <span>days</span>@else{{ number_format($order->resource_cents / 100, 2, '.', '') }} {{ $currency }} / <span>month</span>@endif</td>
                                <td><span class="label label-{{ $classes[$order->status] ?? 'default' }}"><span>{{ $labels[$order->status] ?? $order->status }}</span></span></td>
                                <td>{{ $order->expires_at ? $order->expires_at->format('Y-m-d H:i') : '—' }}</td>
                                <td>@if ($order->server)<a href="{{ route('admin.servers.view', $order->server_id) }}"><i class="fa fa-server"></i> {{ $order->server->uuidShort }}</a>@else — @endif</td>
                                @if ($canManage)
                                    <td class="text-right" style="white-space:nowrap">
                                        @if (in_array($order->status, ['active', 'expired']))
                                            <form action="{{ route('admin.shop.orders.action', $order->id) }}" method="POST" style="display:inline">{!! csrf_field() !!}<input type="hidden" name="action" value="renew"><button class="btn btn-xs btn-success" title="Renew / extend"><i class="fa fa-refresh"></i></button></form>
                                            <form action="{{ route('admin.shop.orders.action', $order->id) }}" method="POST" style="display:inline" class="pd-confirm">{!! csrf_field() !!}<input type="hidden" name="action" value="cancel"><button class="btn btn-xs btn-warning" title="Cancel"><i class="fa fa-ban"></i></button><span class="pd-cfm" style="display:none">Cancel this order? The server is suspended and no longer billed.</span></form>
                                            <form action="{{ route('admin.shop.orders.action', $order->id) }}" method="POST" style="display:inline" class="pd-confirm">{!! csrf_field() !!}<input type="hidden" name="action" value="refund"><button class="btn btn-xs btn-primary" title="Refund as credit"><i class="fa fa-money"></i></button><span class="pd-cfm" style="display:none">Refund the buyer as credit and cancel the order?</span></form>
                                        @endif
                                        <button type="submit" form="pd-del-order-{{ $order->id }}" class="btn btn-xs btn-danger" title="Delete the order and its server"><i class="fa fa-trash"></i></button>
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
        <form id="pd-del-order-{{ $order->id }}" action="{{ route('admin.shop.orders.delete', $order->id) }}" method="POST" class="pd-confirm" data-pd-danger>{!! csrf_field() !!}{!! method_field('DELETE') !!}<span class="pd-cfm" style="display:none">Delete this order AND its server? The server is removed from the node. This cannot be undone.</span></form>
    @endforeach

    <div id="pd-confirm-overlay" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(5,8,15,.65);backdrop-filter:blur(3px);align-items:center;justify-content:center;">
        <div style="width:100%;max-width:440px;margin:0 16px;background:#161c2b;border:1px solid rgba(255,255,255,.08);border-radius:16px;box-shadow:0 30px 80px rgba(0,0,0,.55);padding:24px;">
            <h3 style="margin:0 0 10px;font-size:18px;font-weight:700;color:#f3f4f6;"><span>Please confirm</span></h3>
            <p id="pd-confirm-msg" style="margin:0 0 22px;color:#9aa3b2;font-size:14px;line-height:1.6;"></p>
            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" id="pd-confirm-cancel" style="border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);color:#cbd5e1;border-radius:10px;padding:8px 16px;font-weight:600;cursor:pointer;"><span>Cancel</span></button>
                <button type="button" id="pd-confirm-ok" style="border:0;background:#6366f1;color:#fff;border-radius:10px;padding:8px 16px;font-weight:600;cursor:pointer;"><span>Confirm</span></button>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var overlay = document.getElementById('pd-confirm-overlay');
            if (!overlay) return;
            var msg = document.getElementById('pd-confirm-msg');
            var ok = document.getElementById('pd-confirm-ok');
            var cancel = document.getElementById('pd-confirm-cancel');
            var pending = null;
            function hide() { overlay.style.display = 'none'; pending = null; }
            cancel.addEventListener('click', hide);
            overlay.addEventListener('click', function (e) { if (e.target === overlay) hide(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.style.display !== 'none') hide(); });
            ok.addEventListener('click', function () { var f = pending; hide(); if (f) f.submit(); });
            document.querySelectorAll('form.pd-confirm').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var s = form.querySelector('.pd-cfm');
                    msg.textContent = s ? s.textContent.replace(/\s+/g, ' ').trim() : 'Are you sure?';
                    ok.style.background = form.hasAttribute('data-pd-danger') ? '#ef4444' : '#6366f1';
                    pending = form;
                    overlay.style.display = 'flex';
                });
            });
        })();
    </script>
@endif
@endsection
