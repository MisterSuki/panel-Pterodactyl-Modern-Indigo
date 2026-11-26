@extends('layouts.admin')

@section('title')
    Shop
@endsection

@section('content-header')
    <h1>Shop<small>What you sell.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Shop</li>
    </ol>
@endsection

@section('content')
@php $canManage = auth()->user()->hasAdminPermission('shop.manage'); @endphp
<style>
    .pd-offers-check { width: 34px; }
    .pd-offers-check input { width: 16px; height: 16px; cursor: pointer; vertical-align: middle; }
    .pd-offers-bar { display: none; align-items: center; flex-wrap: wrap; gap: 8px; padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(99,102,241,0.12); }
    .pd-offers-bar.visible { display: flex; }
    .pd-offers-bar strong { margin-right: 6px; }
    .pd-offers-bar label { margin: 0 0 0 auto; font-weight: normal; font-size: 12px; opacity: .85; cursor: pointer; }
    tr.pd-selected td { background: rgba(99,102,241,0.10); }
</style>
<div class="row">
    <div class="col-xs-12">
        @include('admin.shop._nav')
        <form id="pdOffersForm" action="{{ route('admin.shop.offers.bulk') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Offers</h3>
                    @if ($canManage)
                        <div class="box-tools"><a href="{{ route('admin.shop.offers.new') }}" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> <span>New offer</span></a></div>
                    @endif
                </div>
                @if ($canManage)
                    {{-- Shown when offers are ticked: what to do with all of them. --}}
                    <div class="pd-offers-bar" id="pdOffersBar">
                        <strong><span id="pdOffersCount">0</span> <span>selected</span></strong>
                        <button type="submit" name="action" value="enable" class="btn btn-xs btn-success"><i class="fa fa-eye"></i> <span>Put on sale</span></button>
                        <button type="submit" name="action" value="disable" class="btn btn-xs btn-default"><i class="fa fa-eye-slash"></i> <span>Hide</span></button>
                        <button type="submit" name="action" value="feature" class="btn btn-xs btn-primary"><i class="fa fa-fire"></i> <span>Popular</span></button>
                        <button type="submit" name="action" value="unfeature" class="btn btn-xs btn-default"><span>Not popular</span></button>
                        <button type="submit" name="action" value="delete" class="btn btn-xs btn-danger" id="pdOffersDelete"><i class="fa fa-trash"></i> <span>Delete</span></button>
                        <label><input type="checkbox" name="clean_categories" value="1" checked> <span>Also delete the categories left empty</span></label>
                        <span id="pdOffersConfirm" hidden>Delete the selected offers? What was already bought stays as it is.</span>
                    </div>
                @endif
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                @if ($canManage && $offers->isNotEmpty())
                                    <th class="pd-offers-check"><input type="checkbox" id="pdOffersAll" title="Select all" aria-label="Select all"></th>
                                @endif
                                <th>Name</th>
                                <th>Price</th>
                                <th>Duration</th>
                                <th>Server</th>
                                <th>Stock</th>
                                <th>State</th>
                                <th></th>
                            </tr>
                            @forelse ($offers as $offer)
                                <tr>
                                    @if ($canManage)
                                        <td class="pd-offers-check"><input type="checkbox" name="offers[]" value="{{ $offer->id }}" class="pd-offer-box" aria-label="Select"></td>
                                    @endif
                                    <td>
                                        <strong>{{ $offer->name }}</strong>
                                        @if ($offer->featured)<span class="label label-primary" style="margin-left:4px"><i class="fa fa-fire"></i> <span>Popular</span></span>@endif
                                        <br><small class="text-muted">{{ $offer->egg?->name }} &middot; {{ $offer->location?->short }}@if ($offer->category) &middot; {{ $offer->category->name }}@endif</small>
                                    </td>
                                    <td>{{ number_format($offer->price_cents / 100, 2, '.', '') }} {{ $currency }}</td>
                                    <td>{{ $offer->duration_days }} <span>days</span></td>
                                    <td>{{ $offer->memory }} MB &middot; {{ $offer->disk }} MB &middot; {{ $offer->cpu ?: '∞' }}% CPU</td>
                                    <td>{{ $offer->stock === null ? '∞' : $offer->stock }}</td>
                                    <td>@if ($offer->enabled)<span class="label label-success"><span>On sale</span></span>@else<span class="label label-default"><span>Hidden</span></span>@endif</td>
                                    <td class="text-right">
                                        @if ($canManage)
                                            <a href="{{ route('admin.shop.offers.edit', $offer->id) }}" class="btn btn-xs btn-primary"><i class="fa fa-wrench"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted" style="padding:28px"><span>No offer yet. Create the first one to start selling, or use the offer generator.</span></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var form = document.getElementById('pdOffersForm');
            var all = document.getElementById('pdOffersAll');
            var bar = document.getElementById('pdOffersBar');
            var count = document.getElementById('pdOffersCount');
            if (!form || !bar) return;
            var boxes = function () { return Array.prototype.slice.call(form.querySelectorAll('.pd-offer-box')); };
            var refresh = function () {
                var list = boxes();
                var ticked = list.filter(function (b) { return b.checked; }).length;
                count.textContent = ticked;
                bar.classList.toggle('visible', ticked > 0);
                if (all) {
                    all.checked = ticked > 0 && ticked === list.length;
                    all.indeterminate = ticked > 0 && ticked < list.length;
                }
                list.forEach(function (b) { b.closest('tr').classList.toggle('pd-selected', b.checked); });
            };
            if (all) all.addEventListener('change', function () { boxes().forEach(function (b) { b.checked = all.checked; }); refresh(); });
            form.addEventListener('change', function (e) { if (e.target.classList && e.target.classList.contains('pd-offer-box')) refresh(); });
            // A click on a row (not on a link or a button) ticks it.
            form.addEventListener('click', function (e) {
                var row = e.target.closest('tr');
                if (!row || e.target.closest('a, button, input, label')) return;
                var box = row.querySelector('.pd-offer-box');
                if (box) { box.checked = !box.checked; refresh(); }
            });
            var del = document.getElementById('pdOffersDelete');
            if (del) del.addEventListener('click', function (e) {
                var n = boxes().filter(function (b) { return b.checked; }).length;
                var text = document.getElementById('pdOffersConfirm');
                if (!window.confirm((text ? text.textContent : 'Delete?') + ' (' + n + ')')) e.preventDefault();
            });
            refresh();
        })();
    </script>
@endsection
