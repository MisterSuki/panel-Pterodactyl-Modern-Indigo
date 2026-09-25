@extends('layouts.admin')

@section('title')
    Shop credit
@endsection

@section('content-header')
    <h1>Credit and payments<small>The money that came in and the credit of the people.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.shop.offers') }}">Shop</a></li>
        <li class="active">Credit</li>
    </ol>
@endsection

@section('content')
@php
    $types = ['topup' => 'Money in', 'purchase' => 'Purchase', 'renewal' => 'Renewal', 'refund' => 'Paid back', 'adjust' => 'Correction'];
    $states = ['pending' => 'Waiting', 'paid' => 'Paid', 'failed' => 'Failed'];
    $money = fn (int $cents) => number_format($cents / 100, 2, '.', '') . ' ' . $currency;
@endphp
<div class="row">
    <div class="col-xs-12">
        @include('admin.shop._nav')
    </div>
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Movements of the credit</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr><th>Date</th><th>Person</th><th>What</th><th class="text-right">Amount</th><th class="text-right">Credit after</th></tr>
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->created_at?->format('Y-m-d H:i') }}</td>
                                <td><a href="{{ route('admin.users.view', $transaction->user_id) }}">{{ $transaction->user?->username ?? '#' . $transaction->user_id }}</a></td>
                                <td><span>{{ $types[$transaction->type] ?? $transaction->type }}</span>@if ($transaction->note)<br><small class="text-muted">{{ $transaction->note }}</small>@endif</td>
                                <td class="text-right"><strong class="{{ $transaction->amount_cents < 0 ? 'text-red' : 'text-green' }}">{{ $transaction->amount_cents > 0 ? '+' : '' }}{{ $money($transaction->amount_cents) }}</strong></td>
                                <td class="text-right">{{ $money($transaction->balance_after_cents) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted" style="padding:28px"><span>Nothing yet.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($transactions->hasPages())
                <div class="box-footer with-border"><div class="col-md-12 text-center">{!! $transactions->render() !!}</div></div>
            @endif
        </div>
    </div>
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Credit held for the people</h3></div>
            <div class="box-body"><h3 style="margin:0">{{ $money($outstanding) }}</h3><p class="text-muted small"><span>Money that people paid in and did not spend yet.</span></p></div>
        </div>
        @if (auth()->user()->hasAdminPermission('shop.manage'))
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Correct the credit of a person</h3></div>
            <form action="{{ route('admin.shop.credit.adjust') }}" method="POST">
                <div class="box-body">
                    <div class="form-group"><label for="user">Username or email</label><input type="text" id="user" name="user" class="form-control" value="{{ old('user') }}" required></div>
                    <div class="form-group"><label for="amount">Amount ({{ $currency }})</label><input type="text" id="amount" name="amount" class="form-control" value="{{ old('amount') }}" placeholder="10 or -5.50" required></div>
                    <div class="form-group"><label for="note">Reason</label><input type="text" id="note" name="note" class="form-control" maxlength="160" value="{{ old('note') }}" required></div>
                    @if ($errors->any())<div class="alert alert-danger" style="margin-bottom:0">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
                </div>
                <div class="box-footer">{!! csrf_field() !!}<button type="submit" class="btn btn-primary btn-sm pull-right"><span>Apply</span></button></div>
            </form>
        </div>
        @endif
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Latest payments</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td><small>{{ $payment->created_at?->format('m-d H:i') }}</small></td>
                                <td>{{ $payment->user?->username ?? '#' . $payment->user_id }}<br><small class="text-muted">{{ ucfirst($payment->provider) }}</small></td>
                                <td class="text-right">{{ $money($payment->amount_cents) }}<br><small class="text-muted"><span>{{ $states[$payment->status] ?? $payment->status }}</span></small></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted" style="padding:20px"><span>No payment yet.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
