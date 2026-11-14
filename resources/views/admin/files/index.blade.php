@extends('layouts.admin')

@section('title')
    File hosting
@endsection

@section('content-header')
    <h1>File hosting<small>Users keep pictures, videos and files on the panel, with a link to share them.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">File hosting</li>
    </ol>
@endsection

@section('content')
@php
    // A space in MB as a number and its unit: in GB when it is a whole number of GB.
    $pdAmount = function ($mb) {
        $mb = (float) $mb;

        return $mb >= 1024 && fmod($mb, 1024) === 0.0 ? [(int) ($mb / 1024), 'GB'] : [rtrim(rtrim(number_format($mb, 2, '.', ''), '0'), '.'), 'MB'];
    };
    $pdDefault = $pdAmount($config['quota_mb']);
    $pdSize = function ($bytes) {
        $bytes = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return ($unit === 'B' ? (int) $bytes : number_format($bytes, $bytes < 10 ? 1 : 0)) . ' ' . $unit;
            }
            $bytes /= 1024;
        }

        return '';
    };
@endphp
<style>
    .pd-fh-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
    .pd-fh-stat { border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08); background: rgb(17 17 17 / 0.88); padding: 14px 16px; }
    .pd-fh-stat p { margin: 0; font-size: 12px; color: rgba(235, 235, 240, 0.6); }
    .pd-fh-stat strong { display: block; margin-top: 6px; font-size: 20px; font-weight: 600; font-variant-numeric: tabular-nums; }
    .pd-fh-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 4px 0 0; }
    .pd-fh-name { max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; }
    @media (max-width: 900px) { .pd-fh-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<div class="pd-fh-stats">
    <div class="pd-fh-stat"><p>Files</p><strong>{{ number_format($stats['count']) }}</strong></div>
    <div class="pd-fh-stat"><p>Space used</p><strong>{{ $pdSize($stats['size']) }}</strong></div>
    <div class="pd-fh-stat"><p>Users with files</p><strong>{{ number_format($stats['users']) }}</strong></div>
    <div class="pd-fh-stat"><p>Free on the disk of the panel</p><strong>{{ $stats['free'] !== null ? $pdSize($stats['free']) : '—' }}</strong></div>
</div>

<div class="row">
    <div class="col-md-5">
        <form action="{{ route('admin.files') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Settings</h3></div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="enabled" value="1" @checked($config['enabled'])><i class="pd-switch-track"></i><span>Open the file hosting</span></label>
                    <p class="pd-fh-help"><span>Every user gets a "My files" page in the top bar. Off: the page and the links of the files stop working (the files are kept).</span></p>
                    <div class="form-group" style="margin-top: 14px;">
                        <label for="pdFhQuota">Space of each user</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="number" id="pdFhQuota" name="quota" class="form-control" min="0.001" step="any" required value="{{ $pdDefault[0] }}">
                            <select name="unit" class="form-control" style="width: 90px;">
                                <option value="GB" @selected($pdDefault[1] === 'GB')>GB</option>
                                <option value="MB" @selected($pdDefault[1] === 'MB')>MB</option>
                            </select>
                        </div>
                        <p class="pd-fh-help"><span>There is no limit for one file: a user may send a file as big as their space.</span></p>
                    </div>
                    <div class="form-group">
                        <label for="pdFhTypes">What can be sent</label>
                        <select id="pdFhTypes" name="types" class="form-control">
                            <option value="all" @selected($config['types'] === 'all')>Any file</option>
                            <option value="media" @selected($config['types'] === 'media')>Pictures, videos and sounds only</option>
                        </select>
                    </div>
                    <label class="pd-switch"><input type="checkbox" name="public" value="1" @checked($config['public'])><i class="pd-switch-track"></i><span>Allow public links</span></label>
                    <p class="pd-fh-help"><span>Anyone with the link of a public file can open it (to share a picture on Discord...). Off: only the owner can open their files.</span></p>
                    <p class="pd-fh-help" style="margin-top: 10px;"><span>Pictures, videos and sounds are shown in the browser; every other file is downloaded, so nothing in a file can run on the panel. Files are sent in pieces of 8 MB: the upload limit of nginx does not matter.</span></p>
                </div>
                <div class="box-footer"><button type="submit" class="btn btn-primary pull-right"><i class="fa fa-save"></i> <span>Save</span></button></div>
            </div>
        </form>
    </div>

    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Files</h3>
                <div class="box-tools">
                    <form method="GET" action="{{ route('admin.files') }}" style="display: flex; gap: 6px;">
                        <input type="text" name="search" class="form-control input-sm" value="{{ $search }}" placeholder="Name, user..." style="width: 180px;">
                        <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-search"></i></button>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr><th>Name</th><th>User</th><th class="text-right">Size</th><th class="text-center">Views</th><th>Sent</th><th></th></tr>
                    @forelse ($files as $file)
                        <tr>
                            <td>
                                <a href="{{ url('/f/' . $file->token) }}" target="_blank" rel="noopener" class="pd-fh-name" title="{{ $file->name }}">{{ $file->name }}</a>
                                @if (!$file->public)<span class="label label-default"><i class="fa fa-lock"></i></span>@endif
                            </td>
                            <td>{{ $file->user?->username ?? '—' }}</td>
                            <td class="text-right"><code>{{ $pdSize($file->size) }}</code></td>
                            <td class="text-center">{{ $file->views }}</td>
                            <td><small>{{ $file->created_at?->diffForHumans() }}</small></td>
                            <td class="text-right">
                                <form action="{{ route('admin.files.delete', $file->id) }}" method="POST" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Remove this file?">
                                    {!! csrf_field() !!}
                                    <button type="submit" class="btn btn-xs btn-danger" title="Remove"><i class="fa fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted"><span>No file yet.</span></td></tr>
                    @endforelse
                </table>
            </div>
            @if ($files->hasPages())
                <div class="box-footer">{{ $files->links() }}</div>
            @endif
        </div>
    </div>
</div>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-users"></i> <span>Limits of each user</span></h3>
        <div class="box-tools">
            <form method="GET" action="{{ route('admin.files') }}" style="display: flex; gap: 6px;">
                @if ($search !== '')<input type="hidden" name="search" value="{{ $search }}">@endif
                <input type="text" name="users" class="form-control input-sm" value="{{ $userSearch }}" placeholder="Username or email..." style="width: 200px;">
                <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-search"></i></button>
            </form>
        </div>
    </div>
    <div class="box-body" style="padding-bottom: 0;">
        <p class="pd-fh-help" style="margin: 0 0 10px;"><span>Leave the space empty for the default value</span> ({{ $pdDefault[0] }} {{ $pdDefault[1] }}). <span>A user with their own space has it shown in the field.</span></p>
    </div>
    <div class="box-body table-responsive no-padding">
        <table class="table table-hover" style="margin: 0;">
            <tr><th>User</th><th class="text-right">Used</th><th style="width: 260px;">Space</th><th style="width: 110px;"></th></tr>
            @foreach ($people as $person)
                <tr>
                    <td>
                        <strong>{{ $person->username }}</strong><br>
                        <small class="text-muted">{{ $person->email }}</small>
                    </td>
                    <td class="text-right" style="vertical-align: middle;">
                        <code>{{ $pdSize($person->used) }}</code><br>
                        <small class="text-muted">{{ $person->files }} <span>files</span></small>
                    </td>
                    <td style="vertical-align: middle;">
                        @php
                            $pdOwn = $person->file_quota !== null ? $pdAmount((int) $person->file_quota / 1048576) : null;
                        @endphp
                        <div style="display: flex; gap: 6px;">
                            <input form="pdFhLimits{{ $person->id }}" type="number" name="quota" class="form-control input-sm" min="0" step="any" placeholder="{{ $pdDefault[0] }}" value="{{ $pdOwn ? $pdOwn[0] : '' }}">
                            <select form="pdFhLimits{{ $person->id }}" name="unit" class="form-control input-sm" style="width: 72px;">
                                <option value="GB" @selected(($pdOwn ? $pdOwn[1] : $pdDefault[1]) === 'GB')>GB</option>
                                <option value="MB" @selected(($pdOwn ? $pdOwn[1] : $pdDefault[1]) === 'MB')>MB</option>
                            </select>
                        </div>
                    </td>
                    <td class="text-right" style="vertical-align: middle;">
                        <form id="pdFhLimits{{ $person->id }}" action="{{ route('admin.files.limits', $person->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-save"></i> <span>Save</span></button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
    @if ($people->hasPages())
        <div class="box-footer">{{ $people->links() }}</div>
    @endif
</div>
@endsection

