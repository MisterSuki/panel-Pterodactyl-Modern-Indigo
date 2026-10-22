@extends('layouts.admin')

@section('title')
    Announcements
@endsection

@section('content-header')
    <h1>Announcements<small>Tell every user about a maintenance, an update or news.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Announcements</li>
    </ol>
@endsection

@section('content')
@php
    $kinds = ['info' => ['Information', 'fa-info-circle', 'label-info'], 'maintenance' => ['Maintenance', 'fa-wrench', 'label-warning'], 'update' => ['Update', 'fa-cloud-download', 'label-primary'], 'warning' => ['Warning', 'fa-exclamation-triangle', 'label-warning'], 'success' => ['Good news', 'fa-check-circle', 'label-success']];
@endphp
<style>
    .pd-an-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 6px 0 0; }
    .pd-an-kinds { display: flex; flex-wrap: wrap; gap: 6px; }
    .pd-an-kinds label { position: relative; margin: 0; padding: 8px 14px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.12); cursor: pointer; font-weight: 500; color: rgba(235, 235, 240, 0.8); }
    .pd-an-kinds input { position: absolute; opacity: 0; pointer-events: none; }
    .pd-an-kinds label.is-active { background: rgba(255, 255, 255, 0.18); color: #fff; border-color: rgba(255, 255, 255, 0.3); }
    .pd-an-item { display: flex; gap: 12px; padding: 12px 0; border-top: 1px solid rgba(255, 255, 255, 0.08); }
    .pd-an-item:first-child { border-top: 0; }
    .pd-an-item .pd-an-body { flex: 1; min-width: 0; }
    .pd-an-item p { margin: 4px 0 0; color: rgba(235, 235, 240, 0.75); white-space: pre-line; }
    .pd-an-meta { font-size: 12px; color: rgba(235, 235, 240, 0.55); }
</style>

<div class="row">
    <div class="col-md-7">
        <form action="{{ route('admin.announcements') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">New announcement</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Kind</label>
                        <div class="pd-an-kinds" data-pd-kinds>
                            @foreach ($kinds as $value => [$label, $icon])
                                <label class="{{ old('kind', 'info') === $value ? 'is-active' : '' }}"><input type="radio" name="kind" value="{{ $value }}" @checked(old('kind', 'info') === $value)><i class="fa {{ $icon }}"></i> <span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pdAnTitle">Title</label>
                        <input type="text" id="pdAnTitle" name="title" class="form-control" maxlength="191" required value="{{ old('title') }}" placeholder="Maintenance tonight from 10 pm to 11 pm">
                    </div>
                    <div class="form-group">
                        <label for="pdAnBody">Message</label>
                        <textarea id="pdAnBody" name="body" class="form-control" rows="4" maxlength="2000">{{ old('body') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="pdAnUrl">Link <span class="text-muted">(optional)</span></label>
                        <input type="text" id="pdAnUrl" name="url" class="form-control" maxlength="500" value="{{ old('url') }}" placeholder="https://… or /shop">
                    </div>
                    <label class="pd-switch"><input type="checkbox" name="public" value="1" @checked(old('public'))><i class="pd-switch-track"></i><span>Also show it on the public status page</span></label>
                    <p class="pd-an-help"><span>Every user gets it in the bell of the panel, even the ones who join later (for 30 days).</span></p>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-paper-plane"></i> <span>Send to every user</span></button>
                </div>
            </div>
        </form>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Sent</h3></div>
            <div class="box-body">
                @forelse ($announcements as $announcement)
                    @php([$label, $icon, $badge] = $kinds[$announcement->kind] ?? $kinds['info'])
                    <div class="pd-an-item">
                        <div class="pd-an-body">
                            <span class="label {{ $badge }}"><i class="fa {{ $icon }}"></i> <span>{{ $label }}</span></span>
                            @if ($announcement->public)<span class="label label-default"><i class="fa fa-globe"></i> <span>Status page</span></span>@endif
                            <strong style="display: block; margin-top: 6px;">{{ $announcement->title }}</strong>
                            @if ($announcement->body)<p>{{ $announcement->body }}</p>@endif
                            <span class="pd-an-meta">{{ $announcement->created_at?->diffForHumans() }}</span>
                        </div>
                        <form action="{{ route('admin.announcements.delete', $announcement->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-xs btn-danger" title="Remove"><i class="fa fa-trash"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted text-center"><span>No announcement yet.</span></p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <form action="{{ route('admin.announcements.status') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Status page</h3>
                    @if ($statusEnabled)
                        <div class="box-tools"><a href="{{ route('status') }}" target="_blank" rel="noopener" class="btn btn-xs btn-default"><i class="fa fa-external-link"></i> <span>See the page</span></a></div>
                    @endif
                </div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="enabled" value="1" @checked($statusEnabled)><i class="pd-switch-track"></i><span>Open the public status page</span></label>
                    <p class="pd-an-help"><span>At</span> <code>{{ url('/status') }}</code><span>: anyone can see whether the panel and the nodes work, without an account.</span></p>
                    <div class="form-group" style="margin-top: 14px;">
                        <label for="pdStTitle">Title of the page</label>
                        <input type="text" id="pdStTitle" name="title" class="form-control" maxlength="80" value="{{ $statusTitle }}">
                    </div>
                    <label class="pd-switch"><input type="checkbox" name="show_nodes" value="1" @checked($statusNodes)><i class="pd-switch-track"></i><span>Show the nodes and their uptime</span></label>
                    <p class="pd-an-help"><span>Each node is checked every minute; the page shows the last 24 hours and the uptime of the week.</span></p>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-save"></i> <span>Save</span></button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        document.querySelectorAll('[data-pd-kinds] input').forEach(function (input) {
            input.addEventListener('change', function () {
                document.querySelectorAll('[data-pd-kinds] label').forEach(function (label) {
                    label.classList.toggle('is-active', label.querySelector('input').checked);
                });
            });
        });
    </script>
@endsection
