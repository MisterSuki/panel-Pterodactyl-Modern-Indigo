@extends('layouts.admin')

@section('title')
    Role: {{ $role->name }}
@endsection

@section('content-header')
    <h1>{{ $role->name }}<small>{{ $role->description ?: 'Staff role' }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.roles') }}">Staff Roles</a></li>
        <li class="active">{{ $role->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <form action="{{ route('admin.roles.view', $role->id) }}" method="POST">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Role Details</h3>
                </div>
                @include('admin.roles._form', ['catalog' => $catalog, 'selected' => $selected, 'role' => $role])
                <div class="box-footer">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">People With This Role</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        @forelse ($members as $member)
                            <tr class="align-middle">
                                <td>
                                    <a href="{{ route('admin.users.view', $member->id) }}">{{ $member->username }}</a><br />
                                    <small class="text-muted">{{ $member->email }}</small>
                                </td>
                                <td class="text-right">
                                    <form action="{{ route('admin.roles.members.remove', [$role->id, $member->id]) }}" method="POST" style="display:inline;">
                                        {!! csrf_field() !!}
                                        {!! method_field('DELETE') !!}
                                        <button type="submit" class="btn btn-xs btn-default" data-toggle="tooltip" title="Remove the role from this person"><i class="fa fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center text-muted">Nobody has this role yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.roles.members.add', $role->id) }}" method="POST">
                    <label class="control-label" for="member-user">Add someone</label>
                    <div class="input-group">
                        <input type="text" id="member-user" name="user" class="form-control" placeholder="Username or email address" autocomplete="off" />
                        <span class="input-group-btn">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-primary">Add</button>
                        </span>
                    </div>
                    <p class="text-muted no-margin"><small>They get the permissions of this role right away.</small></p>
                </form>
            </div>
        </div>
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">Delete Role</h3>
            </div>
            <div class="box-body">
                <p class="no-margin">People who have this role go back to being regular users. Nothing else is deleted.</p>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.roles.delete', $role->id) }}" method="POST">
                    {!! csrf_field() !!}
                    {!! method_field('DELETE') !!}
                    <button type="submit" class="btn btn-danger btn-sm pull-right">Delete Role</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    @include('admin.roles._scripts')
@endsection
