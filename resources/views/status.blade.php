<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>{{ $title }} &ndash; Status</title>
    <meta name="theme-color" content="#05060f">
    <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
    <script>window.PterodactylSiteLocale = @json(\Pterodactyl\Services\Helpers\Locales::default());</script>
    <script>window.PterodactylDictionaryVersion = @json(\Pterodactyl\Services\Helpers\Locales::dictionaryVersion());</script>
    <script src="/js/translator.js?v={{ @filemtime(public_path('js/translator.js')) }}"></script>
    @include('partials.appearance')
    <style>
        :root { --accent: var(--pd-accent, #0a84ff); --text: #f5f5f7; --muted: rgba(235, 235, 240, 0.6); --line: rgba(255, 255, 255, 0.12); }
        * { box-sizing: border-box; }
        html { background: #070816; }
        body { margin: 0; min-height: 100vh; color: var(--text); font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
        html:not([data-pd-background]) body::before, html[data-pd-background="liquid"] body::before {
            content: ''; position: fixed; inset: 0; z-index: -2; pointer-events: none;
            background: radial-gradient(55% 45% at 12% 8%, rgba(10, 132, 255, 0.45), transparent 70%), radial-gradient(45% 40% at 88% 12%, rgba(191, 90, 242, 0.35), transparent 70%), linear-gradient(160deg, #0b1030 0%, #160c2c 48%, #05060f 100%);
        }
        .wrap { width: min(860px, 100% - 32px); margin: 0 auto; padding: 48px 0 40px; }
        h1 { margin: 0 0 6px; font-size: clamp(28px, 5vw, 40px); letter-spacing: -0.03em; }
        .sub { color: var(--muted); margin: 0 0 28px; font-size: 14px; }
        .card { border-radius: 24px; border: 1px solid var(--line); background: linear-gradient(180deg, rgba(255,255,255,.1), rgba(255,255,255,.04)), rgba(10, 10, 18, 0.45); box-shadow: inset 0 1px 0 rgba(255,255,255,.25), 0 20px 50px -24px rgba(0,0,0,.8); backdrop-filter: saturate(180%) blur(22px); -webkit-backdrop-filter: saturate(180%) blur(22px); padding: 20px 22px; margin-bottom: 18px; }
        .state { display: flex; align-items: center; gap: 14px; font-size: 20px; font-weight: 700; }
        .dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 5px rgba(255,255,255,.06); }
        .ok { background: #30d158; } .warn { background: #ff9f0a; } .down { background: #ff453a; } .none { background: rgba(255,255,255,.18); }
        .node { padding: 14px 0; border-top: 1px solid rgba(255,255,255,.08); }
        .node:first-child { border-top: 0; padding-top: 0; }
        .node-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .node-name { font-weight: 600; }
        .tag { font-size: 12px; color: var(--muted); }
        .uptime { margin-left: auto; font-size: 13px; color: var(--muted); font-variant-numeric: tabular-nums; }
        .bars { display: flex; gap: 2px; margin-top: 10px; height: 26px; }
        .bars span { flex: 1; border-radius: 3px; }
        .bars .up { background: #30d158; } .bars .partial { background: #ff9f0a; } .bars .down { background: #ff453a; } .bars .none { background: rgba(255,255,255,.1); }
        .legend { display: flex; justify-content: space-between; font-size: 11px; color: var(--muted); margin-top: 6px; }
        .ann { padding: 12px 0; border-top: 1px solid rgba(255,255,255,.08); }
        .ann:first-child { border-top: 0; padding-top: 0; }
        .ann strong { display: block; }
        .ann p { margin: 4px 0 0; color: rgba(235,235,240,.8); white-space: pre-line; font-size: 14px; }
        .ann small { color: var(--muted); font-size: 12px; }
        h2 { font-size: 15px; margin: 0 0 14px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); }
        footer { text-align: center; color: var(--muted); font-size: 12px; margin-top: 26px; }
        footer a { color: inherit; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $title }}</h1>
    <p class="sub"><span>Status of the services.</span> <span>Updated</span> {{ $checked_at->format('H:i') }}</p>

    <div class="card">
        <div class="state">
            @if ($state === 'ok')
                <span class="dot ok"></span><span>All systems are working</span>
            @elseif ($state === 'maintenance')
                <span class="dot warn"></span><span>Maintenance in progress</span>
            @else
                <span class="dot down"></span><span>Some services are disrupted</span>
            @endif
        </div>
    </div>

    @if (count($announcements) > 0)
        <div class="card">
            <h2>Announcements</h2>
            @foreach ($announcements as $announcement)
                <div class="ann">
                    <strong>{{ $announcement->title }}</strong>
                    @if ($announcement->body)<p>{{ $announcement->body }}</p>@endif
                    <small>{{ $announcement->created_at?->format('d/m/Y H:i') }}</small>
                </div>
            @endforeach
        </div>
    @endif

    @if (count($nodes) > 0)
        <div class="card">
            <h2>Nodes</h2>
            @foreach ($nodes as $node)
                <div class="node">
                    <div class="node-head">
                        @if ($node['maintenance'])
                            <span class="dot warn"></span>
                        @elseif ($node['online'] === null)
                            <span class="dot none"></span>
                        @else
                            <span class="dot {{ $node['online'] ? 'ok' : 'down' }}"></span>
                        @endif
                        <span class="node-name">{{ $node['name'] }}</span>
                        @if ($node['location'])<span class="tag">{{ $node['location'] }}</span>@endif
                        @if ($node['maintenance'])<span class="tag"><span>Maintenance</span></span>@endif
                        @if ($node['uptime'] !== null)
                            <span class="uptime">{{ number_format($node['uptime'], 2) }} % <span>over 7 days</span></span>
                        @endif
                    </div>
                    <div class="bars">
                        @foreach ($node['bars'] as $bar)
                            <span class="{{ $bar ?? 'none' }}"></span>
                        @endforeach
                    </div>
                    <div class="legend"><span>24 hours ago</span><span>Now</span></div>
                </div>
            @endforeach
        </div>
    @endif

    <footer><a href="{{ url('/') }}">{{ config('app.name', 'Pterodactyl') }}</a></footer>
</div>
</body>
</html>
