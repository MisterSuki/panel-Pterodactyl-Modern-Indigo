@extends('layouts.admin')

@section('title')
    Nests
@endsection

@section('content-header')
    <h1>Nests<small>All nests currently available on this system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Nests</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="alert alert-danger">
            Eggs are a powerful feature of Pterodactyl Panel that allow for extreme flexibility and configuration. Please note that while powerful, modifying an egg wrongly can very easily brick your servers and cause more problems. Please avoid editing our default eggs — those provided by <code>support@pterodactyl.io</code> — unless you are absolutely sure of what you are doing.
        </div>
    </div>
</div>
@if(auth()->user()->hasAdminPermission('nests.manage'))
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary community-eggs">
            <div class="box-header with-border">
                <h3 class="box-title">Add an egg from the community</h3>
                <div class="box-tools">
                    <a href="https://eggs.pterodactyl.io/" target="_blank" rel="noopener noreferrer" class="small text-muted">eggs.pterodactyl.io <i class="fa fa-external-link"></i></a>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-sm-7">
                        <label class="control-label" for="communityEggSearch">Egg name</label>
                        <div class="ce-search">
                            <i class="fa fa-search"></i>
                            <input type="text" id="communityEggSearch" class="form-control" placeholder="Type a name: minecraft, rust, valheim, palworld, node.js..." autocomplete="off" spellcheck="false" />
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <label class="control-label" for="communityEggNest">Put it in this nest</label>
                        <select id="communityEggNest" class="form-control">
                            @foreach($nests as $nest)
                                <option value="{{ $nest->id }}" data-name="{{ $nest->name }}">{{ $nest->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="ce-status text-muted small" data-ce="status">Type at least 2 letters. The list comes from the eggs published on eggs.pterodactyl.io.</div>
                <div class="ce-results" data-ce="results"></div>
            </div>
        </div>
    </div>
</div>
@endif
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Configured Nests</h3>
                <div class="box-tools">
                    <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#importServiceOptionModal" role="button"><i class="fa fa-upload"></i> Import Egg</a>
                    <a href="{{ route('admin.nests.new') }}" class="btn btn-primary btn-sm">Create New</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-center">Eggs</th>
                        <th class="text-center">Servers</th>
                    </tr>
                    @foreach($nests as $nest)
                        <tr>
                            <td class="middle"><code>{{ $nest->id }}</code></td>
                            <td class="middle"><a href="{{ route('admin.nests.view', $nest->id) }}" data-toggle="tooltip" data-placement="right" title="{{ $nest->author }}">{{ $nest->name }}</a></td>
                            <td class="col-xs-6 middle">{{ $nest->description }}</td>
                            <td class="text-center middle">{{ $nest->eggs_count }}</td>
                            <td class="text-center middle">{{ $nest->servers_count }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" tabindex="-1" role="dialog" id="importServiceOptionModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Import an Egg</h4>
            </div>
            <form action="{{ route('admin.nests.egg.import') }}" enctype="multipart/form-data" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="control-label" for="pImportFile">Egg File <span class="field-required"></span></label>
                        <div>
                            <input id="pImportFile" type="file" name="import_file" class="form-control" accept="application/json" />
                            <p class="small text-muted">Select the <code>.json</code> file for the new egg that you wish to import.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label" for="pImportToNest">Associated Nest <span class="field-required"></span></label>
                        <div>
                            <select id="pImportToNest" name="import_to_nest">
                                @foreach($nests as $nest)
                                   <option value="{{ $nest->id }}">{{ $nest->name }} &lt;{{ $nest->author }}&gt;</option>
                                @endforeach
                            </select>
                            <p class="small text-muted">Select the nest that this egg will be associated with from the dropdown. If you wish to associate it with a new nest you will need to create that nest before continuing.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    {{ csrf_field() }}
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <style>
        .ce-search { position: relative; }
        .ce-search .fa { position: absolute; left: 12px; top: 11px; color: #8a94ad; pointer-events: none; }
        .ce-search input { padding-left: 34px; }
        .ce-status { min-height: 20px; margin: 10px 0 6px; }
        .ce-status.bad { color: #f87171; }
        .ce-item { display: flex; align-items: center; gap: 14px; padding: 10px 12px; margin-top: 8px; border-radius: 10px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06); transition: border-color 0.15s ease; }
        .ce-item:hover { border-color: rgba(99, 102, 241, 0.45); }
        .ce-item .ce-main { flex: 1; min-width: 0; }
        .ce-item .ce-name { font-weight: 600; }
        .ce-item .ce-desc { font-size: 12px; color: #8a94ad; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ce-item .ce-note { font-size: 12px; margin-top: 2px; }
        .ce-item .ce-note.ok { color: #4ade80; }
        .ce-item .ce-note.bad { color: #f87171; }
        .ce-badge { display: inline-block; margin-left: 8px; padding: 1px 8px; border-radius: 999px; font-size: 10px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; background: rgba(99, 102, 241, 0.18); color: #a5b4fc; vertical-align: 1px; }
        .ce-badge.applications { background: rgba(34, 211, 238, 0.15); color: #67e8f9; }
        .ce-badge.generic { background: rgba(250, 204, 21, 0.15); color: #fde047; }
    </style>
    <script>
        $(document).ready(function() {
            $('#pImportToNest').select2();
        });

        (function () {
            var $input = $('#communityEggSearch');
            if (!$input.length) { return; }

            var $nest = $('#communityEggNest');
            var $status = $('[data-ce="status"]');
            var $results = $('[data-ce="results"]');
            var searchUrl = '{{ route('admin.nests.community.search') }}';
            var importUrl = '{{ route('admin.nests.community.import') }}';
            var timer = null;
            var request = null;
            var nestChosenByHand = false;

            $nest.on('change', function () { nestChosenByHand = true; });

            function escapeHtml(value) {
                return $('<div>').text(value == null ? '' : String(value)).html();
            }

            function setStatus(text, bad) {
                $status.text(text).toggleClass('bad', !!bad);
            }

            // Picks the nest that has the game's name in its own name (a Minecraft egg goes to the Minecraft nest),
            // unless the choice was made by hand.
            function suggestNest(eggs) {
                if (nestChosenByHand || !eggs.length) { return; }
                var text = (eggs[0].name + ' ' + eggs[0].description).toLowerCase();
                var found = null;
                $nest.find('option').each(function () {
                    var name = String($(this).data('name') || '').toLowerCase();
                    if (name.length > 2 && text.indexOf(name) !== -1) { found = $(this).val(); return false; }
                });
                if (found !== null) { $nest.val(found); }
            }

            function render(eggs) {
                $results.empty();
                eggs.forEach(function (egg) {
                    var $row = $('<div class="ce-item"></div>').data('egg', egg);
                    $row.append(
                        '<div class="ce-main">' +
                            '<div class="ce-name">' + escapeHtml(egg.name) + '<span class="ce-badge ' + escapeHtml(egg.category) + '">' + escapeHtml(egg.category) + '</span></div>' +
                            '<div class="ce-desc" title="' + escapeHtml(egg.description) + '">' + escapeHtml(egg.description) + '</div>' +
                            '<div class="ce-note" data-ce="note"></div>' +
                        '</div>' +
                        '<div><button type="button" class="btn btn-success btn-sm" data-ce="add"><i class="fa fa-plus"></i> Add</button></div>'
                    );
                    $results.append($row);
                });
            }

            function search() {
                var query = $.trim($input.val());
                if (request) { request.abort(); request = null; }
                if (query.length < 2) {
                    $results.empty();
                    setStatus('Type at least 2 letters. The list comes from the eggs published on eggs.pterodactyl.io.');
                    return;
                }
                setStatus('Searching...');
                request = $.ajax({ method: 'GET', url: searchUrl, data: { q: query }, timeout: 40000 })
                    .done(function (response) {
                        var eggs = response.data || [];
                        render(eggs);
                        suggestNest(eggs);
                        setStatus(eggs.length ? eggs.length + (eggs.length === 1 ? ' egg found' : ' eggs found') : 'No egg with that name. Try another word.');
                    })
                    .fail(function (xhr, status) {
                        if (status === 'abort') { return; }
                        $results.empty();
                        setStatus((xhr.responseJSON && xhr.responseJSON.error) || 'The search failed. Try again in a moment.', true);
                    });
            }

            $input.on('input', function () {
                clearTimeout(timer);
                timer = setTimeout(search, 350);
            });

            function add($row, force) {
                var egg = $row.data('egg');
                var $button = $row.find('[data-ce="add"]');
                var $note = $row.find('[data-ce="note"]');
                var nestName = $nest.find('option:selected').text();

                $button.prop('disabled', true).html('<i class="fa fa-circle-o-notch fa-spin"></i> Adding');
                $note.removeClass('ok bad').text('');

                $.ajax({
                    method: 'POST',
                    url: importUrl,
                    data: { slug: egg.slug, nest_id: $nest.val(), force: force ? 1 : 0 },
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    timeout: 90000,
                }).done(function (response) {
                    $button.replaceWith('<a class="btn btn-default btn-sm" href="' + escapeHtml(response.url) + '">Open <i class="fa fa-arrow-right"></i></a>');
                    $note.addClass('ok').text('Added to ' + response.nest.name + '.');
                }).fail(function (xhr) {
                    var body = xhr.responseJSON || {};
                    $button.prop('disabled', false).html('<i class="fa fa-plus"></i> Add');
                    if (xhr.status === 409 && body.exists) {
                        if (window.confirm(body.error + '\n\nAdd it again anyway?')) { add($row, true); }
                        return;
                    }
                    if (xhr.status === 422 && body.errors) {
                        body.error = Object.keys(body.errors).map(function (k) { return body.errors[k][0]; }).join(' ');
                    }
                    $note.addClass('bad').text(body.error || 'Could not add this egg to ' + nestName + '. Try again.');
                });
            }

            $results.on('click', '[data-ce="add"]', function () {
                add($(this).closest('.ce-item'), false);
            });
        })();
    </script>
@endsection
