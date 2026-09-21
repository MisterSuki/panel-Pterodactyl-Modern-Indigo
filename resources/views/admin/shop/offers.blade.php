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
<div class="row">
    <div class="col-xs-12">
        @include('admin.shop._nav')
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Offers</h3>
                @if (auth()->user()->hasAdminPermission('shop.manage'))
                    <div class="box-tools"><a href="{{ route('admin.shop.offers.new') }}" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> <span>New offer</span></a></div>
                @endif
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
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
                                <td><strong>{{ $offer->name }}</strong>@if ($offer->web_plan_id) <span class="label label-info"><span>Web hosting</span></span>@endif<br><small class="text-muted">{{ $offer->egg?->name }} &middot; {{ $offer->location?->short }}</small></td>
                                <td>{{ number_format($offer->price_cents / 100, 2, '.', '') }} {{ $currency }}</td>
                                <td>{{ $offer->duration_days }} <span>days</span></td>
                                <td>{{ $offer->memory }} MB &middot; {{ $offer->disk }} MB &middot; {{ $offer->cpu ?: '∞' }}% CPU</td>
                                <td>{{ $offer->stock === null ? '∞' : $offer->stock }}</td>
                                <td>@if ($offer->enabled)<span class="label label-success"><span>On sale</span></span>@else<span class="label label-default"><span>Hidden</span></span>@endif</td>
                                <td class="text-right">
                                    @if (auth()->user()->hasAdminPermission('shop.manage'))
                                        <a href="{{ route('admin.shop.offers.edit', $offer->id) }}" class="btn btn-xs btn-primary"><i class="fa fa-wrench"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted" style="padding:28px"><span>No offer yet. Create the first one to start selling.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
