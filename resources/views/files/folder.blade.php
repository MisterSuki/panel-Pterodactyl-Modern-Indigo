@php
    $name = config('app.name', 'Pterodactyl');
    $logo = app(\Pterodactyl\Services\Helpers\Appearance::class)->logoUrl();
    $here = end($crumbs)['name'];
    // 1536 -> "1.5 KiB", like the panel.
    $human = function (int $bytes): string {
        if ($bytes < 1) {
            return '0 B';
        }
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $i = min(count($units) - 1, (int) floor(log($bytes, 1024)));

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 2) . ' ' . $units[$i];
    };
    $link = fn (string $path) => '/d/' . $token . ($path === '' ? '' : '?p=' . rawurlencode($path));
    $icons = [
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/>',
        'audio' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
        'other' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
    ];
    $svg = fn (string $icon) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$icon] . '</svg>';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>{{ $here }} &ndash; {{ $name }}</title>
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
        :root { --text: #ededed; --muted: #a1a1a1; --faint: #737373; --line: rgba(255, 255, 255, 0.08); --card: rgb(17 17 17 / 0.88); --accent: rgb(var(--pd-accent-500, 99 102 241)); }
        * { box-sizing: border-box; }
        html { background: #0a0a0a; }
        body { margin: 0; min-height: 100vh; color: var(--text); font-family: Geist, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; font-size: 14px; }
        body::before { content: ''; position: fixed; inset: 0; z-index: -1; pointer-events: none; background: radial-gradient(60% 40% at 50% -5%, rgb(var(--pd-accent-500, 99 102 241) / 0.12), transparent 70%); }
        a { color: inherit; text-decoration: none; }
        svg { width: 16px; height: 16px; flex-shrink: 0; }
        .bar { border-bottom: 1px solid rgba(255, 255, 255, 0.07); background: rgba(10, 10, 10, 0.72); }
        .bar-inner { max-width: 1200px; margin: 0 auto; padding: 0 16px; height: 56px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 15px; }
        .mark { width: 24px; height: 24px; border-radius: 6px; background: linear-gradient(135deg, rgb(var(--pd-accent-300, 165 180 252)), rgb(var(--pd-accent-600, 79 70 229))); }
        img.mark { background: none; object-fit: contain; }
        .wrap { width: min(1200px, 100% - 32px); margin: 0 auto; padding: 32px 0 48px; }
        .crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; color: var(--muted); font-size: 13px; }
        .crumbs a:hover { color: var(--text); }
        .crumbs .sep { color: var(--faint); }
        h1 { margin: 10px 0 0; font-size: 28px; font-weight: 600; letter-spacing: -0.03em; display: flex; align-items: center; gap: 10px; word-break: break-word; }
        h1 svg { width: 26px; height: 26px; color: #fbbf24; }
        .sub { margin: 6px 0 24px; color: var(--muted); }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .card { display: flex; flex-direction: column; border-radius: 12px; background: var(--card); border: 1px solid var(--line); overflow: hidden; transition: border-color .15s, transform .15s; min-width: 0; }
        .card:hover { border-color: rgba(255, 255, 255, 0.18); transform: translateY(-1px); }
        .thumb { position: relative; display: flex; align-items: center; justify-content: center; aspect-ratio: 16 / 10; background: #111; color: var(--faint); overflow: hidden; }
        .thumb img, .thumb video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .thumb svg { width: 34px; height: 34px; }
        .thumb .badge { position: absolute; left: 8px; bottom: 8px; display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: rgba(0, 0, 0, .65); color: #fff; }
        .thumb .badge svg { width: 14px; height: 14px; }
        .meta { display: flex; align-items: center; gap: 8px; padding: 10px 12px; min-width: 0; }
        .meta .text { flex: 1; min-width: 0; }
        .meta strong { display: block; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .meta small { color: var(--muted); font-size: 12px; }
        .dl { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 8px; color: var(--muted); border: 1px solid var(--line); flex-shrink: 0; }
        .dl:hover { color: #fff; background: var(--accent); border-color: transparent; }
        .folder { flex-direction: row; align-items: center; gap: 12px; padding: 14px; }
        .folder > svg { width: 28px; height: 28px; color: #fbbf24; }
        .folder .text { min-width: 0; }
        .folder strong { display: block; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .folder small { color: var(--muted); font-size: 12px; }
        .folders { margin-bottom: 12px; }
        .empty { padding: 48px 0; text-align: center; color: var(--muted); }
        .foot { margin-top: 32px; text-align: center; color: var(--faint); font-size: 12px; }
    </style>
</head>
<body>
    <div class="bar">
        <div class="bar-inner">
            @if ($logo)
                <img class="mark" src="{{ $logo }}" alt="">
            @else
                <span class="mark"></span>
            @endif
            <span>{{ $name }}</span>
        </div>
    </div>
    <main class="wrap">
        @if (count($crumbs) > 1)
            <nav class="crumbs">
                @foreach ($crumbs as $crumb)
                    @if (!$loop->last)
                        <a href="{{ $link($crumb['path']) }}">{{ $crumb['name'] }}</a><span class="sep">/</span>
                    @endif
                @endforeach
            </nav>
        @endif
        <h1>{!! $svg('folder') !!}<span>{{ $here }}</span></h1>
        <p class="sub">
            <span>Shared folder</span>
            @if ($owner)
                &middot; <span>Shared by</span> {{ $owner }}
            @endif
            &middot; {{ $total['count'] }} <span>{{ $total['count'] === 1 ? 'file' : 'files' }}</span> &middot; {{ $human($total['size']) }}
        </p>

        @if ($folders->isNotEmpty())
            <div class="grid folders">
                @foreach ($folders as $folder)
                    <a class="card folder" href="{{ $link($folder['path']) }}">
                        {!! $svg('folder') !!}
                        <span class="text">
                            <strong>{{ $folder['name'] }}</strong>
                            <small>{{ $folder['count'] }} <span>{{ $folder['count'] === 1 ? 'file' : 'files' }}</span> &middot; {{ $human($folder['size']) }}</small>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($files->isNotEmpty())
            <div class="grid">
                @foreach ($files as $file)
                    <div class="card">
                        <a class="thumb" href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $file['name'] }}">
                            @if ($file['inline'] && $file['kind'] === 'image')
                                <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" loading="lazy">
                            @elseif ($file['inline'] && $file['kind'] === 'video')
                                <video src="{{ $file['url'] }}#t=0.5" preload="metadata" muted playsinline></video>
                                <span class="badge">{!! $svg('video') !!}</span>
                            @else
                                {!! $svg(in_array($file['kind'], ['video', 'audio', 'image'], true) ? $file['kind'] : 'other') !!}
                            @endif
                        </a>
                        <div class="meta">
                            <span class="text">
                                <strong title="{{ $file['name'] }}">{{ $file['name'] }}</strong>
                                <small>{{ $human($file['size']) }}</small>
                            </span>
                            <a class="dl" href="{{ $file['url'] }}?dl=1" title="Download" aria-label="Download">{!! $svg('download') !!}</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($folders->isEmpty())
            <p class="empty">This folder is empty.</p>
        @endif

        <p class="foot">{{ $name }}</p>
    </main>
</body>
</html>
