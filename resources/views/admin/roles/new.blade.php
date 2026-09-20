@extends('layouts.admin')

@section('title')
    New Staff Role
@endsection

@section('content-header')
    <h1>New Staff Role<small>Name it and tick what it can do.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.roles') }}">Staff Roles</a></li>
        <li class="active">New</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.roles.new') }}" method="POST">
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Role Details</h3>
                </div>
                @include('admin.roles._form', ['catalog' => $catalog, 'selected' => $selected])
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success btn-sm pull-right">Create Role</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    @include('admin.roles._scripts')
@endsection
