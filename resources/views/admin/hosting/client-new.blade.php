@extends('layouts.admin')

@section('title')
    New client
@endsection

@section('content-header')
    <h1>New client<small>A person, a plan and a first site.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.hosting.clients') }}">Web hosting</a></li>
        <li class="active">New client</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.hosting.clients.new') }}" method="POST" autocomplete="off">
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">The client</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="existing">Someone who already has an account</label>
                        <input type="text" id="existing" name="existing" class="form-control" value="{{ old('existing') }}" placeholder="Username or email">
                        <p class="text-muted small"><span>Fill this in to give the plan to an existing user. Leave it empty to make a new client below.</span></p>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-6"><label for="username">Username</label><input type="text" id="username" name="username" class="form-control" value="{{ old('username') }}"></div>
                        <div class="form-group col-xs-6"><label for="email">Email</label><input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}"></div>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-6"><label for="name_first">First name</label><input type="text" id="name_first" name="name_first" class="form-control" value="{{ old('name_first') }}"></div>
                        <div class="form-group col-xs-6"><label for="name_last">Last name</label><input type="text" id="name_last" name="name_last" class="form-control" value="{{ old('name_last') }}"></div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" autocomplete="new-password">
                        <p class="text-muted small"><span>Leave it empty: the client gets an email to choose their own password.</span></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Plan and first site</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="plan_id">Plan</label>
                        <select id="plan_id" name="plan_id" class="form-control" required>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected((int) old('plan_id') === $plan->id)>{{ $plan->name }} &middot; {{ $plan->max_sites }} site(s), {{ $plan->max_domains }} domain(s)</option>
                            @endforeach
                        </select>
                        @if ($plans->isEmpty())<p class="text-danger small"><span>There is no plan on offer: make one in the Plans tab first.</span></p>@endif
                    </div>
                    <div class="form-group">
                        <label for="site_name">Name of the site</label>
                        <input type="text" id="site_name" name="site_name" class="form-control" maxlength="80" value="{{ old('site_name') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="domain">Domain</label>
                        <input type="text" id="domain" name="domain" class="form-control" value="{{ old('domain') }}" placeholder="example.com">
                        <p class="text-muted small">
                            <span>Optional.</span>
                            @if ($baseDomain)<span>Without one, the site gets a name under</span> <code>{{ $baseDomain }}</code>.@else<span>Without one, the site has no domain until the client adds one.</span>@endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if ($errors->any())<div class="alert alert-danger">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
    <div class="box box-primary">
        <div class="box-footer">{!! csrf_field() !!}<button type="submit" class="btn btn-success btn-sm pull-right"><span>Make the client</span></button></div>
    </div>
</form>
@endsection
