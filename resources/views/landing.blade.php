<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Pterodactyl') }} &ndash; {{ $content['title'] }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($content['subtitle'], 200) }}">
    <meta property="og:title" content="{{ config('app.name', 'Pterodactyl') }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($content['subtitle'], 200) }}">
    <meta name="theme-color" content="#0b1020">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
    <link rel="shortcut icon" href="/favicons/favicon.ico">

    <script>window.PterodactylSiteLocale = @json(\Pterodactyl\Services\Helpers\Locales::default());</script>
    <script>window.PterodactylDictionaryVersion = @json(\Pterodactyl\Services\Helpers\Locales::dictionaryVersion());</script>
    <script src="/js/translator.js?v={{ @filemtime(public_path('js/translator.js')) }}"></script>

    <style>
        :root { --bg: #0b1020; --card: #121a2f; --line: rgba(255,255,255,.08); --text: #e2e8f0; --muted: #94a3b8; --accent: #6366f1; --accent2: #818cf8; --cyan: #22d3ee; }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: 'IBM Plex Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; -webkit-font-smoothing: antialiased; }
        a { color: inherit; }
        .ld-glow { position: fixed; inset: 0; pointer-events: none; background: radial-gradient(700px circle at 15% -5%, rgba(99,102,241,.28), transparent 60%), radial-gradient(600px circle at 90% 10%, rgba(34,211,238,.14), transparent 60%); }
        .ld-wrap { position: relative; width: min(1100px, 100% - 40px); margin: 0 auto; }
        .ld-nav { position: relative; display: flex; align-items: center; justify-content: space-between; padding: 22px 0; }
        .ld-brand { font-size: 22px; font-weight: 800; text-decoration: none; letter-spacing: .01em; background: linear-gradient(90deg, #a5b4fc, #67e8f9); -webkit-background-clip: text; background-clip: text; color: transparent; text-shadow: 0 3px 0 rgba(99,102,241,.25); }
        .ld-btn { display: inline-flex; align-items: center; justify-content: center; padding: 11px 22px; border-radius: 12px; border: 1px solid var(--line); background: rgba(255,255,255,.05); color: var(--text); font-weight: 600; font-size: 15px; text-decoration: none; transition: transform .15s ease, background .15s ease, box-shadow .15s ease; }
        .ld-btn:hover { background: rgba(255,255,255,.1); transform: translateY(-1px); }
        .ld-btn--primary { background: linear-gradient(135deg, var(--accent), #4f46e5); border-color: rgba(129,140,248,.6); color: #fff; box-shadow: 0 10px 30px -10px rgba(99,102,241,.8); }
        .ld-btn--primary:hover { background: linear-gradient(135deg, #7477f5, #5b52ee); }
        .ld-hero { position: relative; padding: 90px 0 70px; text-align: center; }
        .ld-hero h1 { margin: 0 auto; max-width: 820px; font-size: clamp(34px, 6vw, 62px); line-height: 1.08; font-weight: 800; letter-spacing: -.02em; background: linear-gradient(180deg, #fff, #a5b4fc); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .ld-hero p { margin: 22px auto 0; max-width: 640px; font-size: clamp(16px, 2.2vw, 20px); color: var(--muted); white-space: pre-line; }
        .ld-cta { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 34px; }
        .ld-stats { position: relative; display: flex; flex-wrap: wrap; gap: 16px; justify-content: center; margin-top: 10px; }
        .ld-stat { min-width: 190px; padding: 18px 26px; border-radius: 16px; border: 1px solid var(--line); background: rgba(255,255,255,.04); text-align: center; }
        .ld-stat strong { display: block; font-size: 38px; line-height: 1.1; font-weight: 800; background: linear-gradient(180deg, #fff, #a5b4fc); -webkit-background-clip: text; background-clip: text; color: transparent; font-variant-numeric: tabular-nums; }
        .ld-stat span { color: var(--muted); font-size: 14px; }
        .ld-section { position: relative; padding: 50px 0; }
        .ld-section h2 { margin: 0 0 30px; text-align: center; font-size: clamp(24px, 3.4vw, 34px); font-weight: 700; letter-spacing: -.01em; }
        .ld-grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .ld-card { padding: 24px; border-radius: 18px; border: 1px solid var(--line); background: linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.015)), var(--card); box-shadow: 0 18px 40px -26px rgba(0,0,0,.9); transition: transform .2s ease, border-color .2s ease; }
        .ld-card:hover { transform: translateY(-3px); border-color: rgba(129,140,248,.45); }
        .ld-card h3 { margin: 14px 0 6px; font-size: 18px; }
        .ld-card p { margin: 0; color: var(--muted); font-size: 15px; white-space: pre-line; }
        .ld-icon-box { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 14px; color: #c7d2fe; background: rgba(99,102,241,.18); border: 1px solid rgba(129,140,248,.3); }
        .ld-about { max-width: 760px; margin: 0 auto; text-align: center; color: var(--muted); font-size: 17px; white-space: pre-line; }
        .ld-price { margin: 8px 0 2px; font-size: 30px; font-weight: 800; color: #c7d2fe; }
        .ld-price small { font-size: 14px; font-weight: 500; color: var(--muted); }
        .ld-specs { margin: 14px 0 20px; padding: 0; list-style: none; color: var(--muted); font-size: 14px; }
        .ld-specs li { padding: 3px 0; }
        .ld-soldout { color: #fca5a5; font-weight: 600; }
        .ld-footer { position: relative; margin-top: 40px; padding: 30px 0 40px; border-top: 1px solid var(--line); text-align: center; color: var(--muted); font-size: 14px; }
        .ld-footer p { margin: 4px 0; white-space: pre-line; }
        @media (max-width: 560px) { .ld-hero { padding: 60px 0 40px; } }
    </style>
</head>
<body>
<div class="ld-glow"></div>
<div class="ld-wrap">
    <nav class="ld-nav">
        <a class="ld-brand" href="/welcome">{{ config('app.name', 'Pterodactyl') }}</a>
        @if ($signedIn)
            <a class="ld-btn" href="/"><span>Dashboard</span></a>
        @else
            <a class="ld-btn" href="/auth/login"><span>Log in</span></a>
        @endif
    </nav>

    <header class="ld-hero">
        <h1>{{ $content['title'] }}</h1>
        @if ($content['subtitle'] !== '')<p>{{ $content['subtitle'] }}</p>@endif
        <div class="ld-cta">
            @if ($content['cta_primary_text'] !== '' && $content['cta_primary_link'] !== '')
                <a class="ld-btn ld-btn--primary" href="{{ $content['cta_primary_link'] }}">{{ $content['cta_primary_text'] }}</a>
            @endif
            @if ($content['cta_secondary_text'] !== '' && $content['cta_secondary_link'] !== '' && (!str_starts_with($content['cta_secondary_link'], '/auth/register') || $registration))
                <a class="ld-btn" href="{{ $content['cta_secondary_link'] }}">{{ $content['cta_secondary_text'] }}</a>
            @endif
        </div>
    </header>

    @if ($stats !== null && ($stats['servers'] > 0 || ($stats['sites'] ?? 0) > 0))
        <div class="ld-stats" id="stats">
            @if ($stats['servers'] > 0)
                <div class="ld-stat"><strong>{{ number_format($stats['servers'], 0, ',', ' ') }}</strong><span>{{ trans_choice('landing.servers_hosted', $stats['servers']) }}</span></div>
            @endif
            @if (($stats['sites'] ?? 0) > 0)
                <div class="ld-stat"><strong>{{ number_format($stats['sites'], 0, ',', ' ') }}</strong><span>{{ trans_choice('landing.sites_hosted', $stats['sites']) }}</span></div>
            @endif
        </div>
    @endif

    @if (count($content['features']) > 0)
        <section class="ld-section" id="features">
            @if ($content['features_title'] !== '')<h2>{{ $content['features_title'] }}</h2>@endif
            <div class="ld-grid">
                @foreach ($content['features'] as $feature)
                    <div class="ld-card">
                        <span class="ld-icon-box">@include('landing.icon', ['name' => $feature['icon']])</span>
                        <h3>{{ $feature['title'] }}</h3>
                        @if ($feature['text'] !== '')<p>{{ $feature['text'] }}</p>@endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if (count($offers) > 0)
        <section class="ld-section" id="offers">
            @if ($content['offers_title'] !== '')<h2>{{ $content['offers_title'] }}</h2>@endif
            <div class="ld-grid">
                @foreach ($offers as $offer)
                    <div class="ld-card">
                        <h3 style="margin-top:0">{{ $offer['name'] }}</h3>
                        <div class="ld-price">{{ $offer['price'] }} <small>/ {{ $offer['days'] }} <span>days</span></small></div>
                        @if ($offer['description'])<p>{{ $offer['description'] }}</p>@endif
                        <ul class="ld-specs">
                            <li>{{ number_format($offer['memory'] / 1024, 1) }} GB <span>of memory</span></li>
                            <li>{{ number_format($offer['disk'] / 1024, 1) }} GB <span>of disk</span></li>
                            <li>@if ($offer['cpu'] > 0){{ $offer['cpu'] }}% <span>of CPU</span>@else<span>CPU not limited</span>@endif</li>
                        </ul>
                        @if ($offer['available'])
                            <a class="ld-btn ld-btn--primary" href="{{ $signedIn ? '/shop' : ($registration ? '/auth/register' : '/auth/login') }}"><span>Order</span></a>
                        @else
                            <span class="ld-soldout"><span>Sold out</span></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($content['about_text'] !== '')
        <section class="ld-section" id="about">
            @if ($content['about_title'] !== '')<h2>{{ $content['about_title'] }}</h2>@endif
            <div class="ld-about">{{ $content['about_text'] }}</div>
        </section>
    @endif

    <footer class="ld-footer">
        @if ($content['footer_text'] !== '')<p>{{ $content['footer_text'] }}</p>@endif
        @php $copyright = \Pterodactyl\Services\Helpers\Copyright::custom(); @endphp
        <p>{{ $copyright['text'] ?? (config('app.name', 'Pterodactyl') . ' © ' . date('Y')) }}</p>
    </footer>
</div>
</body>
</html>
