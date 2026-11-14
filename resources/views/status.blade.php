@php
    $name = config('app.name', 'Pterodactyl');
    $logo = app(\Pterodactyl\Services\Helpers\Appearance::class)->logoUrl();
    $viewer = Auth::user();
    $vpsCount = $viewer ? $viewer->vpsCount() : 0;
    $shopOpen = app(\Pterodactyl\Services\Shop\ShopSettings::class)->enabled();
    $isStaff = $viewer && $viewer->isStaff();
    $timezone = config('app.timezone');

    // The figures at the top: nodes online, average availability over 7 days, announcements.
    $everything = collect($nodes)->merge($vps);
    $watched = $everything->filter(fn ($node) => $node['online'] !== null);
    $onlineCount = $watched->filter(fn ($node) => $node['online'])->count();
    $uptimes = $everything->pluck('uptime')->filter(fn ($value) => $value !== null);
    $activeMaintenance = $maintenances['active']->first();
    $describe = function ($maintenance) use ($timezone) {
        $start = $maintenance->starts_at->copy()->timezone($timezone);
        $end = $maintenance->ends_at->copy()->timezone($timezone);

        return $start->format('d/m H:i') . ' → ' . ($end->isSameDay($start) ? $end->format('H:i') : $end->format('d/m H:i'));
    };
    $averageUptime = $uptimes->isEmpty() ? null : $uptimes->avg();

    // Each bar is half an hour; the last one is the current half hour.
    $slot = 1800;
    $lastSlot = intdiv(time(), $slot);
    $slotLabel = function (int $index, int $count) use ($slot, $lastSlot, $timezone) {
        $start = ($lastSlot - ($count - 1 - $index)) * $slot;

        return \Carbon\Carbon::createFromTimestamp($start)->timezone($timezone)->format('H:i')
            . ' – ' . \Carbon\Carbon::createFromTimestamp($start + $slot)->timezone($timezone)->format('H:i');
    };

    $kinds = [
        'maintenance' => ['Maintenance', 'warn'],
        'update' => ['Panel update', 'accent'],
        'danger' => ['Incident', 'down'],
        'warning' => ['Notice', 'warn'],
        'success' => ['Resolved', 'ok'],
        'info' => ['Information', 'muted'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>{{ $title }} &ndash; Status</title>
    <meta name="theme-color" content="#0a0a0a">
    @if ($logo)
        <link rel="icon" href="{{ $logo }}">
    @else
        <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
    @endif
    <script>window.PterodactylSiteLocale = @json(\Pterodactyl\Services\Helpers\Locales::default());</script>
    <script>window.PterodactylDictionaryVersion = @json(\Pterodactyl\Services\Helpers\Locales::dictionaryVersion());</script>
    <script src="/js/translator.js?v={{ @filemtime(public_path('js/translator.js')) }}"></script>
    @include('partials.appearance')
    <style>
        @font-face { font-family: 'Geist'; font-style: normal; font-weight: 100 900; font-display: swap; src: url('/fonts/geist/geist-latin-wght-normal.woff2') format('woff2'); }
        @font-face { font-family: 'Geist Mono'; font-style: normal; font-weight: 100 900; font-display: swap; src: url('/fonts/geist/geist-mono-latin-wght-normal.woff2') format('woff2'); }
        :root {
            --text: #ededed; --muted: #a1a1a1; --faint: #737373; --line: rgba(255, 255, 255, 0.08);
            --card: rgb(17 17 17 / 0.88); --ok: #3ecf8e; --warn: #f5a524; --down: #e5484d; --info: #3b82f6;
            --accent: rgb(var(--pd-accent-500, 99 102 241)); --accent-soft: rgb(var(--pd-accent-400, 129 140 248));
        }
        * { box-sizing: border-box; }
        html { background: #0a0a0a; }
        body { margin: 0; min-height: 100vh; color: var(--text); font-family: Geist, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; font-size: 14px; }
        body::before { content: ''; position: fixed; inset: 0; z-index: -1; pointer-events: none; background: radial-gradient(60% 40% at 50% -5%, rgb(var(--pd-accent-500, 99 102 241) / 0.12), transparent 70%); }
        a { color: inherit; }

        /* The top bar, like the one of the panel. */
        .bar { position: sticky; top: 0; z-index: 10; background: rgba(10, 10, 10, 0.72); border-bottom: 1px solid rgba(255, 255, 255, 0.07); -webkit-backdrop-filter: saturate(160%) blur(14px); backdrop-filter: saturate(160%) blur(14px); }
        .bar-inner { max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 56px; display: flex; align-items: center; gap: 24px; }
        .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; font-weight: 600; font-size: 15px; letter-spacing: -0.01em; min-width: 0; }
        .brand span:last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mark { width: 24px; height: 24px; border-radius: 6px; flex-shrink: 0; background: linear-gradient(135deg, rgb(var(--pd-accent-300, 165 180 252)) 0%, rgb(var(--pd-accent-600, 79 70 229)) 100%); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35); }
        img.mark { background: none; box-shadow: none; object-fit: contain; }
        .links { display: flex; align-items: center; gap: 2px; padding: 4px; border-radius: 8px; background: rgba(255, 255, 255, 0.025); border: 1px solid rgba(255, 255, 255, 0.06); }
        .links a { display: flex; align-items: center; gap: 8px; height: 28px; padding: 0 12px; border-radius: 6px; font-size: 14px; font-weight: 500; text-decoration: none; color: var(--muted); white-space: nowrap; transition: color .15s, background-color .15s; }
        .links a svg { width: 14px; height: 14px; color: var(--faint); transition: color .15s; }
        .links a:hover { color: #f5f5f5; background: rgba(255, 255, 255, 0.05); }
        .links a:hover svg { color: #d4d4d4; }
        .links a.active { color: #fafafa; background: rgba(255, 255, 255, 0.09); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06), 0 1px 2px rgba(0, 0, 0, 0.4); }
        .links a.active svg { color: var(--accent-soft); }
        .spacer { flex: 1; }
        .account { display: flex; align-items: center; gap: 8px; height: 32px; padding: 0 12px 0 4px; border-radius: 999px; border: 1px solid #262626; text-decoration: none; font-size: 14px; font-weight: 500; color: #e5e5e5; transition: border-color .15s, background-color .15s; }
        .account:hover { border-color: #404040; background: #171717; }
        .initial { display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .login { display: inline-flex; align-items: center; height: 32px; padding: 0 14px; border-radius: 6px; background: var(--accent); color: #fff; text-decoration: none; font-weight: 500; }
        .login:hover { filter: brightness(1.1); }

        /* The page. */
        .wrap { width: min(880px, 100% - 32px); margin: 0 auto; padding: 40px 0 48px; }
        .head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 30px; font-weight: 600; letter-spacing: -0.03em; }
        .sub { margin: 6px 0 0; color: var(--muted); }
        .live { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; color: var(--muted); border: 1px solid var(--line); border-radius: 999px; padding: 4px 10px; }
        .live i { width: 6px; height: 6px; border-radius: 50%; background: var(--ok); animation: pulse 2s infinite; }
        @keyframes pulse { 50% { opacity: .35; } }
        .card { position: relative; border-radius: 12px; background: var(--card); border: 1px solid var(--line); padding: 20px; margin-bottom: 16px; }
        .hero { display: flex; align-items: center; gap: 16px; padding: 22px 24px; overflow: hidden; }
        .hero::after { content: ''; position: absolute; inset: 0; pointer-events: none; background: radial-gradient(50% 120% at 0% 50%, var(--glow), transparent 70%); opacity: .14; }
        .hero.ok { --glow: var(--ok); } .hero.maintenance { --glow: var(--warn); } .hero.down { --glow: var(--down); }
        .hero-icon { position: relative; flex-shrink: 0; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; }
        .hero.ok .hero-icon { background: rgb(62 207 142 / .14); color: var(--ok); }
        .hero.maintenance .hero-icon { background: rgb(245 165 36 / .14); color: var(--warn); }
        .hero.down .hero-icon { background: rgb(229 72 77 / .14); color: var(--down); }
        .hero-icon svg { width: 22px; height: 22px; }
        .hero-title { position: relative; margin: 0; font-size: 19px; font-weight: 600; letter-spacing: -0.02em; }
        .hero-sub { position: relative; margin: 3px 0 0; color: var(--muted); }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .stat { border-radius: 12px; background: var(--card); border: 1px solid var(--line); padding: 14px 16px; min-width: 0; }
        .stat p { margin: 0; font-size: 12px; color: var(--muted); }
        .stat strong { display: block; margin-top: 6px; font-size: 20px; font-weight: 600; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
        h2 { display: flex; align-items: center; justify-content: space-between; margin: 0 0 16px; font-size: 14px; font-weight: 600; }
        h2 small { font-size: 12px; font-weight: 400; color: var(--muted); }
        .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .dot.ok { background: var(--ok); box-shadow: 0 0 0 3px rgb(62 207 142 / .15); }
        .dot.warn { background: var(--warn); box-shadow: 0 0 0 3px rgb(245 165 36 / .15); }
        .dot.down { background: var(--down); box-shadow: 0 0 0 3px rgb(229 72 77 / .15); }
        .dot.none { background: #525252; }
        .node { padding: 16px 0; border-top: 1px solid var(--line); }
        .node:first-of-type { border-top: 0; padding-top: 0; }
        .node:last-child { padding-bottom: 0; }
        .node-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .node-name { font-weight: 600; }
        .tag { font-size: 12px; color: var(--muted); border: 1px solid var(--line); border-radius: 999px; padding: 1px 8px; }
        .state-text { font-size: 12px; }
        .state-text.ok { color: var(--ok); } .state-text.warn { color: var(--warn); } .state-text.down { color: var(--down); } .state-text.none { color: var(--faint); }
        .uptime { margin-left: auto; font-family: 'Geist Mono', ui-monospace, monospace; font-size: 13px; color: var(--text); }
        .uptime span { font-family: Geist, sans-serif; color: var(--muted); font-size: 12px; }
        .bars { display: flex; gap: 3px; margin-top: 12px; height: 32px; }
        .bars span { position: relative; flex: 1; border-radius: 3px; transition: transform .1s, filter .1s; }
        .bars span:hover { transform: scaleY(1.12); filter: brightness(1.2); }
        .bars .up { background: var(--ok); } .bars .partial { background: var(--warn); } .bars .down { background: var(--down); } .bars .none { background: #262626; } .bars .maintenance { background: var(--info); }
        .bars span::after { content: attr(data-label); position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 11px; font-family: 'Geist Mono', ui-monospace, monospace; padding: 4px 8px; border-radius: 6px; background: #262626; border: 1px solid #404040; color: #e5e5e5; opacity: 0; pointer-events: none; transition: opacity .1s; z-index: 2; }
        .bars span:hover::after { opacity: 1; }
        .legend { display: flex; justify-content: space-between; font-size: 11px; color: var(--faint); margin-top: 8px; }
        .key { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: var(--muted); margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--line); }
        .key span { display: inline-flex; align-items: center; gap: 6px; }
        .key i { width: 10px; height: 10px; border-radius: 3px; }
        .ann { padding: 14px 0; border-top: 1px solid var(--line); }
        .ann:first-of-type { border-top: 0; padding-top: 0; }
        .ann:last-child { padding-bottom: 0; }
        .ann-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ann-head strong { font-weight: 600; }
        .badge { font-size: 11px; font-weight: 500; border-radius: 999px; padding: 2px 8px; border: 1px solid; }
        .badge.warn { color: var(--warn); border-color: rgb(245 165 36 / .35); background: rgb(245 165 36 / .08); }
        .badge.down { color: var(--down); border-color: rgb(229 72 77 / .35); background: rgb(229 72 77 / .08); }
        .badge.ok { color: var(--ok); border-color: rgb(62 207 142 / .35); background: rgb(62 207 142 / .08); }
        .badge.accent { color: var(--accent-soft); border-color: rgb(var(--pd-accent-500, 99 102 241) / .35); background: rgb(var(--pd-accent-500, 99 102 241) / .08); }
        .badge.muted { color: var(--muted); border-color: #404040; }
        .ann time { margin-left: auto; font-size: 12px; color: var(--faint); font-variant-numeric: tabular-nums; }
        .ann p { margin: 6px 0 0; color: #d4d4d4; white-space: pre-line; line-height: 1.55; }
        .empty { margin: 0; color: var(--muted); }
        .mt { border-radius: 12px; padding: 16px 18px; margin-bottom: 12px; border: 1px solid; }
        .mt.now { border-color: rgb(245 165 36 / .35); background: linear-gradient(180deg, rgb(245 165 36 / .09), rgb(245 165 36 / .03)), var(--card); }
        .mt.soon { border-color: rgb(59 130 246 / .35); background: linear-gradient(180deg, rgb(59 130 246 / .08), rgb(59 130 246 / .02)), var(--card); }
        .mt-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .mt-head strong { font-weight: 600; font-size: 15px; }
        .mt-when { margin-left: auto; font-family: 'Geist Mono', ui-monospace, monospace; font-size: 12px; color: var(--muted); }
        .mt p { margin: 8px 0 0; color: #d4d4d4; white-space: pre-line; line-height: 1.55; }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .chip { font-size: 12px; color: var(--muted); border: 1px solid var(--line); border-radius: 999px; padding: 2px 9px; background: rgba(255, 255, 255, .02); }
        .badge.info { color: #93c5fd; border-color: rgb(59 130 246 / .35); background: rgb(59 130 246 / .08); }
        .panel-row { display: flex; align-items: center; gap: 10px; }
        .past { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid var(--line); }
        .past:first-of-type { border-top: 0; padding-top: 0; }
        .past:last-child { padding-bottom: 0; }
        .past span:last-child { margin-left: auto; font-family: 'Geist Mono', ui-monospace, monospace; font-size: 12px; color: var(--faint); }
        footer { text-align: center; color: var(--faint); font-size: 12px; margin-top: 28px; }
        footer a { text-decoration: none; }
        footer a:hover { color: var(--text); }

        @media (max-width: 760px) {
            .bar-inner { flex-wrap: wrap; height: auto; padding: 10px 16px; gap: 10px 12px; }
            .links { order: 3; width: 100%; overflow-x: auto; scrollbar-width: none; }
            .account .name { display: none; }
            .account { padding-right: 4px; }
            .stats { gap: 8px; }
            .stat { padding: 10px 12px; }
            .stat p { font-size: 11px; }
            .stat strong { font-size: 16px; }
            .wrap { padding-top: 28px; }
            .ann time, .mt-when { margin-left: 0; width: 100%; }
        }
    </style>
</head>
<body>
<header class="bar">
    <div class="bar-inner">
        <a class="brand" href="{{ url('/') }}">
            @if ($logo)
                <img class="mark" src="{{ $logo }}" alt="">
            @else
                <span class="mark" aria-hidden="true"></span>
            @endif
            <span>{{ $name }}</span>
        </a>
        <nav class="links">
            @if ($viewer)
                <a href="{{ url('/') }}">
                    <svg viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M480 160H32c-17.7 0-32-14.3-32-32V64c0-17.7 14.3-32 32-32h448c17.7 0 32 14.3 32 32v64c0 17.7-14.3 32-32 32zm-48-88c-13.3 0-24 10.7-24 24s10.7 24 24 24 24-10.7 24-24-10.7-24-24-24zm-64 0c-13.3 0-24 10.7-24 24s10.7 24 24 24 24-10.7 24-24-10.7-24-24-24zm112 248H32c-17.7 0-32-14.3-32-32v-64c0-17.7 14.3-32 32-32h448c17.7 0 32 14.3 32 32v64c0 17.7-14.3 32-32 32zm-48-88c-13.3 0-24 10.7-24 24s10.7 24 24 24 24-10.7 24-24-10.7-24-24-24zm-64 0c-13.3 0-24 10.7-24 24s10.7 24 24 24 24-10.7 24-24-10.7-24-24-24zm112 248H32c-17.7 0-32-14.3-32-32v-64c0-17.7 14.3-32 32-32h448c17.7 0 32 14.3 32 32v64c0 17.7-14.3 32-32 32zm-48-88c-13.3 0-24 10.7-24 24s10.7 24 24 24 24-10.7 24-24-10.7-24-24-24zm-64 0c-13.3 0-24 10.7-24 24s10.7 24 24 24 24-10.7 24-24-10.7-24-24-24z"/></svg>
                    <span>Servers</span>
                </a>
                @if ($vpsCount > 0)
                    <a href="{{ url('/vps') }}">
                        <svg viewBox="0 0 576 512" fill="currentColor" aria-hidden="true"><path d="M528 0H48C21.5 0 0 21.5 0 48v320c0 26.5 21.5 48 48 48h192l-16 48h-72c-13.3 0-24 10.7-24 24s10.7 24 24 24h272c13.3 0 24-10.7 24-24s-10.7-24-24-24h-72l-16-48h192c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zm-16 352H64V64h448v288z"/></svg>
                        <span>VPS</span>
                    </a>
                @endif
                @if ($shopOpen)
                    <a href="{{ url('/shop') }}">
                        <svg viewBox="0 0 616 512" fill="currentColor" aria-hidden="true"><path d="M602 118.6L537.1 15C531.3 5.7 521 0 510 0H106C95 0 84.7 5.7 78.9 15L14 118.6c-33.5 53.5-3.8 127.9 58.8 136.4 4.5.6 9.1.9 13.7.9 29.6 0 55.8-13 73.8-33.1 18 20.1 44.3 33.1 73.8 33.1 29.6 0 55.8-13 73.8-33.1 18 20.1 44.3 33.1 73.8 33.1 29.6 0 55.8-13 73.8-33.1 18.1 20.1 44.3 33.1 73.8 33.1 4.7 0 9.2-.3 13.7-.9 62.8-8.4 92.6-82.8 59-136.4zM529.5 288c-10 0-19.9-1.5-29.5-3.8V384H116v-99.8c-9.6 2.2-19.5 3.8-29.5 3.8-6 0-12.1-.4-18-1.2-5.6-.8-11.1-2.1-16.4-3.6V480c0 17.7 14.3 32 32 32h448c17.7 0 32-14.3 32-32V283.2c-5.4 1.6-10.8 2.9-16.4 3.6-6.1.8-12.1 1.2-18.2 1.2z"/></svg>
                        <span>Shop</span>
                    </a>
                @endif
            @endif
            <a href="{{ url('/status') }}" class="active" aria-current="page">
                <svg viewBox="0 0 640 512" fill="currentColor" aria-hidden="true"><path d="M216 288h-48c-8.8 0-16 7.2-16 16v192c0 8.8 7.2 16 16 16h48c8.8 0 16-7.2 16-16V304c0-8.8-7.2-16-16-16zM88 384H40c-8.8 0-16 7.2-16 16v96c0 8.8 7.2 16 16 16h48c8.8 0 16-7.2 16-16v-96c0-8.8-7.2-16-16-16zm256-192h-48c-8.8 0-16 7.2-16 16v288c0 8.8 7.2 16 16 16h48c8.8 0 16-7.2 16-16V208c0-8.8-7.2-16-16-16zm128-96h-48c-8.8 0-16 7.2-16 16v384c0 8.8 7.2 16 16 16h48c8.8 0 16-7.2 16-16V112c0-8.8-7.2-16-16-16zM600 0h-48c-8.8 0-16 7.2-16 16v480c0 8.8 7.2 16 16 16h48c8.8 0 16-7.2 16-16V16c0-8.8-7.2-16-16-16z"/></svg>
                <span>Status</span>
            </a>
            @if ($isStaff)
                <a href="{{ url('/admin') }}">
                    <svg viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M466.5 83.7l-192-80a48.15 48.15 0 0 0-36.9 0l-192 80C27.7 91.1 16 108.6 16 128c0 198.5 114.5 335.7 221.5 380.3 11.8 4.9 25.1 4.9 36.9 0C360.1 472.6 496 349.3 496 128c0-19.4-11.7-36.9-29.5-44.3zM256.1 446.3l-.1-381 175.9 73.3c-3.3 151.4-82.1 261.1-175.8 307.7z"/></svg>
                    <span>Admin</span>
                </a>
            @endif
        </nav>
        <div class="spacer"></div>
        @if ($viewer)
            <a class="account" href="{{ url('/account') }}" title="{{ $viewer->email }}">
                <span class="initial">{{ mb_substr($viewer->username, 0, 1) }}</span>
                <span class="name">{{ $viewer->username }}</span>
            </a>
        @else
            <a class="login" href="{{ url('/auth/login') }}"><span>Sign in</span></a>
        @endif
    </div>
</header>

<main class="wrap">
    <div class="head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="sub"><span>Status of the services.</span> <span>Updated</span> {{ $checked_at->timezone($timezone)->format('H:i') }}</p>
        </div>
        <span class="live"><i></i><span>Refreshed every minute</span></span>
    </div>

    <section class="card hero {{ $state }}">
        <span class="hero-icon">
            @if ($state === 'ok')
                <svg viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M173.9 439.4l-166.4-166.4c-10-10-10-26.2 0-36.2l36.2-36.2c10-10 26.2-10 36.2 0L192 312.7 432.1 72.6c10-10 26.2-10 36.2 0l36.2 36.2c10 10 10 26.2 0 36.2l-294.4 294.4c-10 10-26.2 10-36.2 0z"/></svg>
            @elseif ($state === 'maintenance')
                <svg viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M507.7 109.1c-2.2-9-13.5-12.1-20.1-5.5l-74.4 74.4-67.9-11.3-11.3-67.9 74.4-74.4c6.6-6.6 3.4-17.9-5.7-20.2-47.4-11.7-99.6.9-136.6 37.9-39.6 39.6-50.5 97.1-34.1 147.2L18.7 402.8c-25 25-25 65.5 0 90.5s65.5 25 90.5 0l213.2-213.2c50.1 16.7 107.5 5.7 147.4-34.2 37.1-37.1 49.7-89.3 37.9-136.8zM64 472c-13.2 0-24-10.8-24-24 0-13.3 10.7-24 24-24s24 10.7 24 24c0 13.2-10.7 24-24 24z"/></svg>
            @else
                <svg viewBox="0 0 576 512" fill="currentColor" aria-hidden="true"><path d="M569.5 440C588 472 564.8 512 527.9 512H48.1c-36.9 0-60-40.1-41.6-72L246.4 24c18.5-32 64.7-32 83.2 0l239.9 416zM288 354c-25.4 0-46 20.6-46 46s20.6 46 46 46 46-20.6 46-46-20.6-46-46-46zm-43.7-165.3l7.4 136c.3 6.4 5.6 11.3 12 11.3h48.5c6.4 0 11.6-5 12-11.3l7.4-136c.4-6.9-5.1-12.7-12-12.7h-63.4c-6.9 0-12.4 5.8-12 12.7z"/></svg>
            @endif
        </span>
        <div>
            @if ($state === 'ok')
                <p class="hero-title">All systems are working</p>
                <p class="hero-sub">No problem is known at the moment.</p>
            @elseif ($state === 'maintenance')
                <p class="hero-title">Maintenance in progress</p>
                @if ($activeMaintenance)
                    <p class="hero-sub">{{ $activeMaintenance->title }} &middot; <span>until</span> {{ $activeMaintenance->ends_at->copy()->timezone($timezone)->format('H:i') }}</p>
                @else
                    <p class="hero-sub">Some services may be unavailable for a few minutes.</p>
                @endif
            @else
                <p class="hero-title">Some services are disrupted</p>
                <p class="hero-sub">The team knows about it and is working on it.</p>
            @endif
        </div>
    </section>

    @foreach ($maintenances['active'] as $maintenance)
        <section class="mt now">
            <div class="mt-head">
                <span class="badge warn">In progress</span>
                <strong>{{ $maintenance->title }}</strong>
                <span class="mt-when">{{ $describe($maintenance) }}</span>
            </div>
            @if ($maintenance->body)<p>{{ $maintenance->body }}</p>@endif
            <div class="chips">
                @foreach ((array) $maintenance->components as $key)
                    <span class="chip">{{ $components[$key] ?? $key }}</span>
                @endforeach
            </div>
        </section>
    @endforeach
    @foreach ($maintenances['upcoming'] as $maintenance)
        <section class="mt soon">
            <div class="mt-head">
                <span class="badge info">Planned maintenance</span>
                <strong>{{ $maintenance->title }}</strong>
                <span class="mt-when">{{ $describe($maintenance) }}</span>
            </div>
            @if ($maintenance->body)<p>{{ $maintenance->body }}</p>@endif
            <div class="chips">
                @foreach ((array) $maintenance->components as $key)
                    <span class="chip">{{ $components[$key] ?? $key }}</span>
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($everything->isNotEmpty())
        <div class="stats">
            <div class="stat">
                <p>Nodes online</p>
                <strong>{{ $onlineCount }} / {{ $watched->count() ?: $everything->count() }}</strong>
            </div>
            <div class="stat">
                <p>Availability over 7 days</p>
                <strong>{{ $averageUptime === null ? '—' : number_format($averageUptime, 2) . ' %' }}</strong>
            </div>
            <div class="stat">
                <p>Announcements</p>
                <strong>{{ count($announcements) }}</strong>
            </div>
        </div>

    @endif

    <section class="card">
        <h2><span>Panel</span></h2>
        <div class="panel-row">
            @if ($panel['maintenance'])
                <span class="dot warn"></span><span class="node-name">{{ $name }}</span><span class="state-text warn"><span>Maintenance</span></span>
            @else
                <span class="dot ok"></span><span class="node-name">{{ $name }}</span><span class="state-text ok"><span>Online</span></span>
            @endif
        </div>
    </section>

    @if (count($nodes) > 0)
        <section class="card">
            <h2><span>Game servers</span> <small>Last 24 hours</small></h2>
            @foreach ($nodes as $item)
                @include('status.row', ['item' => $item, 'slotLabel' => $slotLabel])
            @endforeach
        </section>
    @endif

    @if (count($vps) > 0)
        <section class="card">
            <h2><span>VPS</span> <small>Last 24 hours</small></h2>
            @foreach ($vps as $item)
                @include('status.row', ['item' => $item, 'slotLabel' => $slotLabel])
            @endforeach
        </section>
    @endif

    @if ($everything->isNotEmpty())
        <div class="key" style="border-top: 0; margin: -4px 0 16px; padding: 0 4px;">
            <span><i style="background: var(--ok)"></i><span>Online</span></span>
            <span><i style="background: var(--warn)"></i><span>Cut for a moment</span></span>
            <span><i style="background: var(--down)"></i><span>Offline</span></span>
            <span><i style="background: var(--info)"></i><span>Maintenance</span></span>
            <span><i style="background: #262626"></i><span>No data</span></span>
        </div>
    @endif

    <section class="card">
        <h2><span>Announcements</span></h2>
        @forelse ($announcements as $announcement)
            @php
                $kind = $kinds[$announcement->kind] ?? $kinds['info'];
            @endphp
            <article class="ann">
                <div class="ann-head">
                    <span class="badge {{ $kind[1] }}">{{ $kind[0] }}</span>
                    <strong>{{ $announcement->title }}</strong>
                    <time>{{ $announcement->created_at?->timezone($timezone)->format('d/m/Y H:i') }}</time>
                </div>
                @if ($announcement->body)<p>{{ $announcement->body }}</p>@endif
            </article>
        @empty
            <p class="empty">No announcement at the moment.</p>
        @endforelse
    </section>

    @if (count($maintenances['recent']) > 0)
        <section class="card">
            <h2><span>Recent maintenances</span> <small>Last 7 days</small></h2>
            @foreach ($maintenances['recent'] as $maintenance)
                <div class="past">
                    <span class="dot none"></span>
                    <span>{{ $maintenance->title }}</span>
                    <span>{{ $describe($maintenance) }}</span>
                </div>
            @endforeach
        </section>
    @endif

    <footer><a href="{{ url('/') }}">{{ $name }}</a> &middot; {{ date('Y') }}</footer>
</main>
</body>
</html>
