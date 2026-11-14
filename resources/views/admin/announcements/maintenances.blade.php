{{-- The maintenances of the status page: plan one (or start one now), and the list with "End now". --}}
@php
    $pdTz = config('app.timezone');
    $pdStart = now()->timezone($pdTz)->addMinutes(5)->startOfMinute();
    $pdOldComponents = (array) old('components', []);
@endphp
<style>
    .pd-mt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 6px; }
    .pd-mt-grid label { display: flex; align-items: center; gap: 8px; margin: 0; padding: 8px 10px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); cursor: pointer; font-weight: 500; }
    .pd-mt-grid label:hover { border-color: rgba(255, 255, 255, 0.2); }
    .pd-mt-grid input { margin: 0; }
    .pd-mt-dates { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .pd-mt-item { display: flex; gap: 12px; align-items: flex-start; padding: 12px 0; border-top: 1px solid rgba(255, 255, 255, 0.08); }
    .pd-mt-item:first-child { border-top: 0; padding-top: 0; }
    .pd-mt-item .pd-mt-body { flex: 1; min-width: 0; }
    .pd-mt-item p { margin: 4px 0 0; color: rgba(235, 235, 240, 0.75); white-space: pre-line; }
    .pd-mt-when { font-size: 12px; color: rgba(235, 235, 240, 0.6); margin-top: 4px; font-variant-numeric: tabular-nums; }
    .pd-mt-chip { display: inline-block; font-size: 11px; padding: 1px 8px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.15); color: rgba(235, 235, 240, 0.8); margin: 4px 4px 0 0; }
    .pd-mt-actions { display: flex; gap: 6px; flex-shrink: 0; }
    @media (max-width: 600px) { .pd-mt-dates { grid-template-columns: 1fr; } }
</style>

<form action="{{ route('admin.announcements.maintenances') }}" method="POST">
    {!! csrf_field() !!}
    <div class="box box-warning">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-wrench"></i> <span>Maintenances</span></h3></div>
        <div class="box-body">
            <p class="pd-an-help" style="margin: 0 0 14px;"><span>Plan a maintenance or start one now: the status page shows it, and what it concerns shows "Maintenance" instead of a failure. Its time does not count against the uptime.</span></p>
            <div class="form-group">
                <label for="pdMtTitle">Title</label>
                <input type="text" id="pdMtTitle" name="title" class="form-control" maxlength="191" required value="{{ old('title') }}" placeholder="Update of the node game">
            </div>
            <div class="form-group">
                <label for="pdMtBody">Message <span class="text-muted">(optional)</span></label>
                <textarea id="pdMtBody" name="body" class="form-control" rows="3" maxlength="2000" placeholder="The game servers restart once or twice.">{{ old('body') }}</textarea>
            </div>
            <div class="pd-mt-dates">
                <div class="form-group">
                    <label for="pdMtStart">Start of the maintenance</label>
                    <input type="datetime-local" id="pdMtStart" name="starts_at" class="form-control" required value="{{ old('starts_at', $pdStart->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="form-group">
                    <label for="pdMtEnd">End of the maintenance</label>
                    <input type="datetime-local" id="pdMtEnd" name="ends_at" class="form-control" required value="{{ old('ends_at', $pdStart->copy()->addHour()->format('Y-m-d\TH:i')) }}">
                </div>
            </div>
            <div class="form-group">
                <label>Concerns</label>
                <div class="pd-mt-grid">
                    @foreach ($components as $key => $label)
                        <label><input type="checkbox" name="components[]" value="{{ $key }}" @checked(in_array($key, $pdOldComponents, true))> <span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </div>
            <label class="pd-switch"><input type="checkbox" name="notify" value="1" @checked(old('notify', true))><i class="pd-switch-track"></i><span>Tell every user in the bell</span></label>
            <p class="pd-an-help"><span>Times are in the timezone of the panel</span> ({{ $pdTz }}). <span>To start now, leave the start as it is or put the current time.</span></p>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-warning pull-right"><i class="fa fa-wrench"></i> <span>Save the maintenance</span></button>
        </div>
    </div>
</form>

@if (count($maintenances) > 0)
    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Planned and recent maintenances</h3></div>
        <div class="box-body">
            @foreach ($maintenances as $maintenance)
                <div class="pd-mt-item">
                    <div class="pd-mt-body">
                        @if ($maintenance->isActive())
                            <span class="label label-warning"><i class="fa fa-wrench"></i> <span>In progress</span></span>
                        @elseif ($maintenance->isUpcoming())
                            <span class="label label-info"><i class="fa fa-clock-o"></i> <span>Planned</span></span>
                        @else
                            <span class="label label-default"><i class="fa fa-check"></i> <span>Done</span></span>
                        @endif
                        <strong style="display: block; margin-top: 6px;">{{ $maintenance->title }}</strong>
                        @if ($maintenance->body)<p>{{ $maintenance->body }}</p>@endif
                        <div class="pd-mt-when">{{ $maintenance->starts_at->timezone($pdTz)->format('d/m/Y H:i') }} → {{ $maintenance->ends_at->timezone($pdTz)->format('d/m/Y H:i') }}</div>
                        <div>
                            @foreach ((array) $maintenance->components as $key)
                                <span class="pd-mt-chip">{{ $components[$key] ?? $key }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="pd-mt-actions">
                        @if ($maintenance->isActive() || $maintenance->isUpcoming())
                            <form action="{{ route('admin.announcements.maintenances.end', $maintenance->id) }}" method="POST">
                                {!! csrf_field() !!}
                                <button type="submit" class="btn btn-xs btn-success">
                                    @if ($maintenance->isActive())
                                        <i class="fa fa-check"></i> <span>End now</span>
                                    @else
                                        <i class="fa fa-times"></i> <span>Cancel</span>
                                    @endif
                                </button>
                            </form>
                        @endif
                        <form action="{{ route('admin.announcements.maintenances.delete', $maintenance->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-xs btn-danger" title="Remove"><i class="fa fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
