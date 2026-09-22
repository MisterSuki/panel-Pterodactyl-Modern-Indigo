@extends('layouts.admin')

@section('title')
    Shop settings
@endsection

@section('content-header')
    <h1>Shop settings<small>Open the shop and connect the payment providers.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.shop.offers') }}">Shop</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.shop.settings') }}" method="POST" autocomplete="off">
    <div class="row">
        <div class="col-xs-12">
            @include('admin.shop._nav')
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">The shop</h3></div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled))><i class="pd-switch-track"></i><span>The shop is open</span></label>
                    <p class="text-muted small"><span>When it is closed, the shop does not appear on the dashboard and nothing can be bought.</span></p>
                    <div class="row">
                        <div class="form-group col-xs-4">
                            <label for="currency">Currency</label>
                            <input type="text" id="currency" name="currency" class="form-control" maxlength="3" value="{{ old('currency', $currency) }}" placeholder="EUR" required>
                        </div>
                        <div class="form-group col-xs-4">
                            <label for="min_topup">Smallest top-up</label>
                            <input type="text" id="min_topup" name="min_topup" class="form-control" value="{{ old('min_topup', $minTopup) }}" required>
                        </div>
                        <div class="form-group col-xs-4">
                            <label for="max_topup">Biggest top-up</label>
                            <input type="text" id="max_topup" name="max_topup" class="form-control" value="{{ old('max_topup', $maxTopup) }}" required>
                        </div>
                    </div>
                    <p class="text-muted small"><span>Changing the currency does not convert the credit or the prices that already exist.</span></p>
                    @if ($errors->any())<div class="alert alert-danger" style="margin-bottom:0">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
                </div>
            </div>
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Stripe</h3></div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="stripe_enabled" value="1" @checked(old('stripe_enabled', $stripe['on']))><i class="pd-switch-track"></i><span>Accept payments with Stripe</span></label>
                    <div class="form-group">
                        <label for="stripe_secret">Secret key</label>
                        <input type="password" id="stripe_secret" name="stripe_secret" class="form-control" placeholder="{{ $stripe['secret'] ? '•••••••• (kept, type to replace)' : 'sk_live_...' }}" autocomplete="new-password">
                        @if ($stripe['secret'])<label class="small text-muted"><input type="checkbox" name="clear_stripe_secret" value="1"> <span>Remove it</span></label>@endif
                    </div>
                    <div class="form-group">
                        <label for="stripe_webhook_secret">Signing secret of the webhook</label>
                        <input type="password" id="stripe_webhook_secret" name="stripe_webhook_secret" class="form-control" placeholder="{{ $stripe['webhook'] ? '•••••••• (kept, type to replace)' : 'whsec_...' }}" autocomplete="new-password">
                        @if ($stripe['webhook'])<label class="small text-muted"><input type="checkbox" name="clear_stripe_webhook_secret" value="1"> <span>Remove it</span></label>@endif
                    </div>
                    <p class="text-muted small"><span>In Stripe, add a webhook to this address for the events checkout.session.completed and checkout.session.async_payment_succeeded:</span> <code>{{ $webhooks['stripe'] }}</code></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">PayPal</h3></div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="paypal_enabled" value="1" @checked(old('paypal_enabled', $paypal['on']))><i class="pd-switch-track"></i><span>Accept payments with PayPal</span></label>
                    <div class="form-group">
                        <label for="paypal_client_id">Client ID</label>
                        <input type="text" id="paypal_client_id" name="paypal_client_id" class="form-control" value="{{ old('paypal_client_id', $paypal['client']) }}" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="paypal_secret">Secret</label>
                        <input type="password" id="paypal_secret" name="paypal_secret" class="form-control" placeholder="{{ $paypal['secret'] ? '•••••••• (kept, type to replace)' : '' }}" autocomplete="new-password">
                        @if ($paypal['secret'])<label class="small text-muted"><input type="checkbox" name="clear_paypal_secret" value="1"> <span>Remove it</span></label>@endif
                    </div>
                    <label class="pd-switch"><input type="checkbox" name="paypal_sandbox" value="1" @checked(old('paypal_sandbox', $paypal['sandbox']))><i class="pd-switch-track"></i><span>Test mode (sandbox): no real money moves</span></label>
                </div>
            </div>
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">SumUp</h3></div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="sumup_enabled" value="1" @checked(old('sumup_enabled', $sumup['on']))><i class="pd-switch-track"></i><span>Accept payments with SumUp</span></label>
                    <div class="form-group">
                        <label for="sumup_api_key">API key</label>
                        <input type="password" id="sumup_api_key" name="sumup_api_key" class="form-control" placeholder="{{ $sumup['key'] ? '•••••••• (kept, type to replace)' : 'sup_sk_...' }}" autocomplete="new-password">
                        @if ($sumup['key'])<label class="small text-muted"><input type="checkbox" name="clear_sumup_api_key" value="1"> <span>Remove it</span></label>@endif
                    </div>
                    <div class="form-group">
                        <label for="sumup_merchant_code">Merchant code</label>
                        <input type="text" id="sumup_merchant_code" name="sumup_merchant_code" class="form-control" value="{{ old('sumup_merchant_code', $sumup['merchant']) }}" autocomplete="off">
                    </div>
                    <p class="text-muted small"><span>Optional: SumUp can also tell the panel when a payment is done, at this address:</span> <code>{{ $webhooks['sumup'] }}</code></p>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Resources on demand (billed monthly)</h3></div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="res_enabled" value="1" @checked(old('res_enabled', $resources['enabled']))><i class="pd-switch-track"></i><span>Let clients raise or lower the resources of a server they bought</span></label>
                    <p class="text-muted small"><span>Set a price per unit. A change made during the month is charged only for the days left, on an invoice made on the first of the next month and paid from the credit. Leave a price at 0 to keep that component fixed. The "max" is how many units a client may add on top of their offer.</span></p>
                    <div class="row">
                        @foreach ($resources['items'] as $res)
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="res_{{ $res['key'] }}_price">{{ $res['label'] }}</label>
                                    <div class="row">
                                        <div class="col-xs-7">
                                            <div class="input-group">
                                                <span class="input-group-addon">{{ $currency }}</span>
                                                <input type="text" id="res_{{ $res['key'] }}_price" name="res_{{ $res['key'] }}_price" class="form-control" value="{{ old('res_' . $res['key'] . '_price', $res['price']) }}" placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="col-xs-5">
                                            <input type="number" min="0" name="res_{{ $res['key'] }}_max" class="form-control" value="{{ old('res_' . $res['key'] . '_max', $res['max']) }}" placeholder="max" title="Maximum a client may add">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-group" style="max-width:220px">
                        <label for="res_due_days">Days to pay an invoice</label>
                        <input type="number" min="1" id="res_due_days" name="res_due_days" class="form-control" value="{{ old('res_due_days', $resources['dueDays']) }}">
                        <p class="text-muted small"><span>After this, an unpaid invoice suspends the servers (nothing is deleted). They come back when it is paid.</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="box box-primary">
        <div class="box-footer">{!! csrf_field() !!}<button type="submit" class="btn btn-success btn-sm pull-right"><span>Save the settings</span></button></div>
    </div>
</form>
@endsection
