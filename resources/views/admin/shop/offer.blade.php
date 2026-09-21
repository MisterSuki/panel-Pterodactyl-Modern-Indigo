@extends('layouts.admin')

@section('title')
    {{ $offer->exists ? 'Offer: ' . $offer->name : 'New offer' }}
@endsection

@section('content-header')
    <h1>{{ $offer->exists ? $offer->name : 'New offer' }}<small>What the buyer gets.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.shop.offers') }}">Shop</a></li>
        <li class="active">{{ $offer->exists ? $offer->name : 'New' }}</li>
    </ol>
@endsection

@section('content')
@php $field = fn (string $name, $default = null) => old($name, $default); @endphp
<form action="{{ $offer->exists ? route('admin.shop.offers.edit', $offer->id) : route('admin.shop.offers.new') }}" method="POST">
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">The offer</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control" maxlength="80" value="{{ $field('name', $offer->name) }}" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" maxlength="1000">{{ $field('description', $offer->description) }}</textarea>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-6">
                            <label for="price">Price ({{ $currency }})</label>
                            <input type="text" id="price" name="price" class="form-control" value="{{ $field('price', $price) }}" placeholder="9.90" required>
                        </div>
                        <div class="form-group col-xs-6">
                            <label for="duration_days">Paid for (days)</label>
                            <input type="number" id="duration_days" name="duration_days" class="form-control" min="1" max="365" value="{{ $field('duration_days', $offer->duration_days) }}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-6">
                            <label for="stock">Stock</label>
                            <input type="number" id="stock" name="stock" class="form-control" min="0" value="{{ $field('stock', $offer->stock) }}" placeholder="Unlimited">
                            <p class="text-muted small"><span>Leave empty for no limit.</span></p>
                        </div>
                        <div class="form-group col-xs-6">
                            <label for="position">Position</label>
                            <input type="number" id="position" name="position" class="form-control" min="0" value="{{ $field('position', $offer->position ?? 0) }}">
                        </div>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="enabled" value="1" @checked($field('enabled', $offer->enabled))> <span>On sale</span></label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">The server that is made</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="form-group col-xs-6">
                            <label for="egg_id">Egg</label>
                            <select id="egg_id" name="egg_id" class="form-control" required>
                                @foreach ($eggs as $egg)
                                    <option value="{{ $egg->id }}" @selected((int) $field('egg_id', $offer->egg_id) === $egg->id)>{{ $egg->nest?->name }} &rsaquo; {{ $egg->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-xs-6">
                            <label for="location_id">Location</label>
                            <select id="location_id" name="location_id" class="form-control" required>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((int) $field('location_id', $offer->location_id) === $location->id)>{{ $location->short }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p class="text-muted small"><span>The node and the port are chosen by the panel among the nodes of the location that still have room.</span></p>
                    <div class="row">
                        <div class="form-group col-xs-4">
                            <label for="memory">Memory (MB)</label>
                            <input type="number" id="memory" name="memory" class="form-control" min="64" value="{{ $field('memory', $offer->memory) }}" required>
                        </div>
                        <div class="form-group col-xs-4">
                            <label for="disk">Disk (MB)</label>
                            <input type="number" id="disk" name="disk" class="form-control" min="64" value="{{ $field('disk', $offer->disk) }}" required>
                        </div>
                        <div class="form-group col-xs-4">
                            <label for="cpu">CPU (%)</label>
                            <input type="number" id="cpu" name="cpu" class="form-control" min="0" value="{{ $field('cpu', $offer->cpu) }}" required>
                            <p class="text-muted small"><span>0 for no limit.</span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-4">
                            <label for="database_limit">Databases</label>
                            <input type="number" id="database_limit" name="database_limit" class="form-control" min="0" value="{{ $field('database_limit', $offer->database_limit ?? 0) }}">
                        </div>
                        <div class="form-group col-xs-4">
                            <label for="allocation_limit">Extra ports</label>
                            <input type="number" id="allocation_limit" name="allocation_limit" class="form-control" min="0" value="{{ $field('allocation_limit', $offer->allocation_limit ?? 0) }}">
                        </div>
                        <div class="form-group col-xs-4">
                            <label for="backup_limit">Backups</label>
                            <input type="number" id="backup_limit" name="backup_limit" class="form-control" min="0" value="{{ $field('backup_limit', $offer->backup_limit ?? 0) }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="environment">Variables of the egg</label>
                        <textarea id="environment" name="environment" class="form-control" rows="4" placeholder="SERVER_NAME=My server">{{ $field('environment', $environment) }}</textarea>
                        <p class="text-muted small"><span>One per line, NAME=value. The other variables of the egg take their default value.</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>
    @endif
    <div class="box box-primary">
        <div class="box-footer">
            {!! csrf_field() !!}
            <button type="submit" class="btn btn-success btn-sm pull-right"><span>Save</span></button>
            @if ($offer->exists)
                <button type="submit" form="pd-delete-offer" class="btn btn-danger btn-sm pull-left" onclick="return confirm('Delete this offer? What was bought stays as it is.')"><i class="fa fa-trash"></i></button>
            @endif
        </div>
    </div>
</form>
@if ($offer->exists)
    <form id="pd-delete-offer" action="{{ route('admin.shop.offers.delete', $offer->id) }}" method="POST">{!! csrf_field() !!}{!! method_field('DELETE') !!}</form>
@endif
@endsection
