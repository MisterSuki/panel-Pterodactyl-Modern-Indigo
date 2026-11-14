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
                    <div class="row" style="margin-top: 14px;">
                        <div class="col-sm-6 form-group">
                            <label for="pdFhQuota">Space of each user (MB)</label>
                            <input type="number" id="pdFhQuota" name="quota_mb" class="form-control" min="1" required value="{{ $config['quota_mb'] }}">
                        </div>
                        <div class="col-sm-6 form-group">
                            <label for="pdFhMax">Biggest file (MB)</label>
                            <input type="number" id="pdFhMax" name="max_mb" class="form-control" min="1" required value="{{ $config['max_mb'] }}">
                        </div>
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

        <form action="{{ route('admin.files.quota') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Space of one user</h3></div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-7 form-group">
                            <label for="pdFhUser">Email or username</label>
                            <input type="text" id="pdFhUser" name="user" class="form-control" required>
                        </div>
                        <div class="col-sm-5 form-group">
                            <label for="pdFhUserQuota">Space (MB)</label>
                            <input type="number" id="pdFhUserQuota" name="quota_mb" class="form-control" min="0" placeholder="Default">
                        </div>
                    </div>
                    <p class="pd-fh-help" style="margin-top: 0;"><span>Leave the space empty to give the user the default space again.</span></p>
                    @if (count($quotas) > 0)
                        <table class="table table-condensed" style="margin: 10px 0 0;">
                            @foreach ($quotas as $person)
                                <tr><td>{{ $person->username }}</td><td class="text-right"><code>{{ $pdSize($person->file_quota) }}</code></td></tr>
                            @endforeach
                        </table>
                    @endif
                </div>
                <div class="box-footer"><button type="submit" class="btn btn-default pull-right"><i class="fa fa-save"></i> <span>Save</span></button></div>
            </div>
        </form>

        @if (count($top) > 0)
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Who uses the most</h3></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table">
                        @foreach ($top as $row)
                            <tr>
                                <td>{{ $row->user?->username ?? '—' }}</td>
                                <td class="text-right">{{ $row->files }} <span>files</span></td>
                                <td class="text-right"><code>{{ $pdSize($row->total) }}</code></td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif
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
@endsection
