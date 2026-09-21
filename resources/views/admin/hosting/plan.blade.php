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
                    <p class="text-muted small"><span>The images that the egg runs. Tick the ones a client can choose, and give each the name the client sees (8.3, 8.2...). The others are left out.</span></p>
                    <div id="pd-images"></div>
                    <p class="text-muted small" id="pd-images-none" style="display:none;margin-bottom:0"><span>This egg has no image: sites will not be made.</span></p>
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
                                @if ($suggested->isNotEmpty())
                                    <optgroup label="{{ __('Suggested for web hosting') }}">
                                        @foreach ($suggested as $egg)<option value="{{ $egg->id }}" @selected((int) $f('egg_id', $plan->egg_id) === $egg->id)>{{ $egg->nest?->name }} &rsaquo; {{ $egg->name }}</option>@endforeach
                                    </optgroup>
                                @endif
                                @foreach ($others as $nest => $group)
                                    <optgroup label="{{ $nest }}">
                                        @foreach ($group as $egg)<option value="{{ $egg->id }}" @selected((int) $f('egg_id', $plan->egg_id) === $egg->id)>{{ $egg->name }}</option>@endforeach
                                    </optgroup>
                                @endforeach
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

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var images = @json($images);
            var chosen = @json((object) $chosen);
            var chosenDefault = @json($chosenDefault);
            var select = document.getElementById('egg_id');
            var box = document.getElementById('pd-images');
            var none = document.getElementById('pd-images-none');
            var first = true;

            function el(tag, className, text) {
                var node = document.createElement(tag);
                if (className) { node.className = className; }
                if (text !== undefined) { node.textContent = text; }
                return node;
            }

            function render() {
                var egg = images[select.value] || {};
                var keys = Object.keys(egg);
                box.textContent = '';
                none.style.display = keys.length === 0 ? '' : 'none';
                keys.forEach(function (name, index) {
                    var image = egg[name];
                    // The versions the plan already has are shown as they are, but only for the egg it was saved with.
                    var known = first && Object.prototype.hasOwnProperty.call(chosen, image);
                    var row = el('div', 'pd-slot');

                    var use = el('label', 'pd-switch');
                    var check = el('input');
                    check.type = 'checkbox';
                    check.name = 'img[' + index + '][use]';
                    check.value = '1';
                    check.checked = first ? known : true;
                    use.appendChild(check);
                    use.appendChild(el('i', 'pd-switch-track'));
                    use.appendChild(el('span', null, name));
                    row.appendChild(use);

                    var hidden = el('input');
                    hidden.type = 'hidden';
                    hidden.name = 'img[' + index + '][image]';
                    hidden.value = image;
                    row.appendChild(hidden);

                    var line = el('div', 'row');
                    var left = el('div', 'form-group col-xs-8');
                    var input = el('input', 'form-control');
                    input.type = 'text';
                    input.name = 'img[' + index + '][label]';
                    input.maxLength = 20;
                    input.placeholder = name;
                    input.value = known ? chosen[image] : name;
                    input.setAttribute('aria-label', 'Name of the version');
                    left.appendChild(input);
                    line.appendChild(left);

                    var right = el('div', 'form-group col-xs-4');
                    var radio = el('label', 'small');
                    var r = el('input');
                    r.type = 'radio';
                    r.name = 'default_index';
                    r.value = String(index);
                    r.checked = known && chosenDefault !== null && chosen[image] === chosenDefault;
                    radio.appendChild(r);
                    radio.appendChild(document.createTextNode(' '));
                    radio.appendChild(el('span', null, 'Start with this one'));
                    right.appendChild(radio);
                    line.appendChild(right);
                    row.appendChild(line);

                    row.appendChild(el('p', 'text-muted small', image));
                    box.appendChild(row);
                });
                first = false;
            }

            select.addEventListener('change', render);
            render();
        })();
    </script>
@endsection
