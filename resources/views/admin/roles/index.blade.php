@extends('layouts.admin')

@section('title')
    Staff Roles
@endsection

@section('content-header')
    <h1>Staff Roles<small>Choose what members of staff are allowed to do.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Staff Roles</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="callout callout-info">
            Administrators can do everything. Someone with a role can open the admin area but only reach the sections their role allows.
            Create a role, tick its permissions, then give it to people from the role page or from their user page.
        </div>
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Roles</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.roles.new') }}"><button type="button" class="btn btn-sm btn-primary">Create New</button></a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-center">Permissions</th>
                            <th class="text-center">People</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr class="align-middle">
                                <td><a href="{{ route('admin.roles.view', $role->id) }}">{{ $role->name }}</a></td>
                                <td>{{ $role->description ?: '—' }}</td>
                                <td class="text-center"><span class="label label-default">{{ count($role->permissions) }}</span></td>
                                <td class="text-center">{{ $role->users_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No role yet. Create one, for example "Moderator" or "Support".</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
