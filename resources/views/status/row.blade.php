{{-- One node of the status page (a node of the panel or a Proxmox node of the VPS): its state, its uptime, its bars. --}}
@php
    $rowStatus = $item['maintenance'] ? 'warn' : ($item['online'] === null ? 'none' : ($item['online'] ? 'ok' : 'down'));
    $rowBars = count($item['bars']);
@endphp
<div class="node">
    <div class="node-head">
        <span class="dot {{ $rowStatus }}"></span>
        <span class="node-name">{{ $item['name'] }}</span>
        @if ($item['location'])<span class="tag">{{ $item['location'] }}</span>@endif
        <span class="state-text {{ $rowStatus }}">
            @if ($rowStatus === 'warn')
                <span>Maintenance</span>
            @elseif ($rowStatus === 'ok')
                <span>Online</span>
            @elseif ($rowStatus === 'down')
                <span>Offline</span>
            @else
                <span>Not watched yet</span>
            @endif
        </span>
        @if ($item['uptime'] !== null)
            <span class="uptime">{{ number_format($item['uptime'], 2) }} % <span>over 7 days</span></span>
        @endif
    </div>
    <div class="bars">
        @foreach ($item['bars'] as $index => $bar)
            <span class="{{ $bar ?? 'none' }}" data-label="{{ $slotLabel($index, $rowBars) }}"></span>
        @endforeach
    </div>
    <div class="legend"><span>24 hours ago</span><span>Now</span></div>
</div>
