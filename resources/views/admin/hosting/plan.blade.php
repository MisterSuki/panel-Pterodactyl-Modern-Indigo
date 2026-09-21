@extends('layouts.admin')

@section('title')
    {{ $plan->exists ? 'Plan: ' . $plan->name : 'New plan' }}
@endsection

@section('content-header')
    <h1>{{ $plan->exists ? $plan->name : 'New plan' }}<small>Limits and PHP versions.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.hosting.plans') }}">Plans</a></li>
        <li class="active">{{ $plan->exists ? $plan->name : 'New' }}</li>
    </ol>
@endsection

@section('content')
@php $f = fn (string $name, $default = null) => old($name, $default); @endphp
<form action="{{ $plan->exists ? route('admin.hosting.plans.edit', $plan->id) : route('admin.hosting.plans.new') }}" method="POST">
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">The plan</h3></div>
                <div class="box-body">
                    <div class="form-group"><label for="name">Name</label><input type="text" id="name" name="name" class="form-control" maxlength="80" value="{{ $f('name', $plan->name) }}" required></div>
                    <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" class="form-control" rows="3" maxlength="1000">{{ $f('description', $plan->description) }}</textarea></div>
                    <div class="row">
                        <div class="form-group col-xs-6"><label for="max_sites">Sites</label><input type="number" id="max_sites" name="max_sites" class="form-control" min="1" max="100" value="{{ $f('max_sites', $plan->max_sites) }}" required></div>
                        <div class="form-group col-xs-6"><label for="max_domains">Domains per site</label><input type="number" id="max_domains" name="max_domains" class="form-control" min="1" max="100" value="{{ $f('max_domains', $plan->max_domains) }}" required></div>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-6"><label for="position">Position</label><input type="number" id="position" name="position" class="form-control" min="0" value="{{ $f('position', $plan->position ?? 0) }}"></div>
                    </div>
                    <label class="pd-switch"><input type="checkbox" name="enabled" value="1" @checked($f('enabled', $plan->enabled))><i class="pd-switch-track"></i><span>On offer</span></label>
                </div>
            </div>
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Versions of PHP</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="versions">Versions and their image</label>
                        <textarea id="versions" name="versions" class="form-control" rows="5" placeholder="8.3=ghcr.io/parkervcp/yolks:php_8.3&#10;8.2=ghcr.io/parkervcp/yolks:php_8.2">{{ $f('versions', $versions) }}</textarea>
                        <p class="text-muted small"><span>One per line: the version, then the docker image that runs it. The client picks one of these for each site.</span></p>
                    </div>
                    <div class="form-group">
                        <label for="default_php">Version to start with</label>
                        <input type="text" id="default_php" name="default_php" class="form-control" maxlength="10" value="{{ $f('default_php', $plan->default_php) }}" placeholder="8.3">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Each site</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="form-group col-xs-6">
                            <label for="egg_id">Egg</label>
                            <select id="egg_id" name="egg_id" class="form-control" required>
                                @foreach ($eggs as $egg)<option value="{{ $egg->id }}" @selected((int) $f('egg_id', $plan->egg_id) === $egg->id)>{{ $egg->nest?->name }} &rsaquo; {{ $egg->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group col-xs-6">
                            <label for="location_id">Location</label>
                            <select id="location_id" name="location_id" class="form-control" required>
                                @foreach ($locations as $location)<option value="{{ $location->id }}" @selected((int) $f('location_id', $plan->location_id) === $location->id)>{{ $location->short }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <p class="text-muted small"><span>Choose an egg that runs a web server with PHP and answers on its port. The node and the port are chosen by the panel among the nodes of the location that have room.</span></p>
                    <div class="row">
                        <div class="form-group col-xs-4"><label for="memory">Memory (MB)</label><input type="number" id="memory" name="memory" class="form-control" min="64" value="{{ $f('memory', $plan->memory) }}" required></div>
                        <div class="form-group col-xs-4"><label for="disk">Disk (MB)</label><input type="number" id="disk" name="disk" class="form-control" min="64" value="{{ $f('disk', $plan->disk) }}" required></div>
                        <div class="form-group col-xs-4"><label for="cpu">CPU (%)</label><input type="number" id="cpu" name="cpu" class="form-control" min="0" value="{{ $f('cpu', $plan->cpu) }}" required><p class="text-muted small"><span>0 for no limit.</span></p></div>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-6"><label for="database_limit">Databases</label><input type="number" id="database_limit" name="database_limit" class="form-control" min="0" value="{{ $f('database_limit', $plan->database_limit ?? 0) }}"></div>
                        <div class="form-group col-xs-6"><label for="backup_limit">Backups</label><input type="number" id="backup_limit" name="backup_limit" class="form-control" min="0" value="{{ $f('backup_limit', $plan->backup_limit ?? 0) }}"></div>
                    </div>
                    <div class="form-group">
                        <label for="environment">Variables of the egg</label>
                        <textarea id="environment" name="environment" class="form-control" rows="4">{{ $f('environment', $environment) }}</textarea>
                        <p class="text-muted small"><span>One per line, NAME=value. The other variables of the egg take their default value.</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if ($errors->any())<div class="alert alert-danger">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
    <div class="box box-primary">
        <div class="box-footer">
            {!! csrf_field() !!}
            <button type="submit" class="btn btn-success btn-sm pull-right"><span>Save</span></button>
            @if ($plan->exists)<button type="submit" form="pd-delete-plan" class="btn btn-danger btn-sm pull-left" onclick="return confirm('Delete this plan? Accounts keep what they have.')"><i class="fa fa-trash"></i></button>@endif
        </div>
    </div>
</form>
@if ($plan->exists)
    <form id="pd-delete-plan" action="{{ route('admin.hosting.plans.delete', $plan->id) }}" method="POST">{!! csrf_field() !!}{!! method_field('DELETE') !!}</form>
@endif
@endsection
