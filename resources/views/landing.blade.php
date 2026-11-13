@php
    $name = config('app.name', 'Pterodactyl');
    $logo = app(\Pterodactyl\Services\Helpers\Appearance::class)->logoUrl();
    $copyright = \Pterodactyl\Services\Helpers\Copyright::custom();
    $hasOffers = count($offers) > 0;
    $hasAbout = $content['about_text'] !== '';
    $hasFeatures = count($content['features']) > 0;
    $showRegister = $registration && !$signedIn;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $name }} &ndash; {{ $content['title'] }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($content['subtitle'], 200) }}">
    <meta property="og:title" content="{{ $name }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($content['subtitle'], 200) }}">
    <meta name="theme-color" content="#0a0a0a">
    @if ($logo)
        <link rel="icon" href="{{ $logo }}">
        <link rel="apple-touch-icon" href="{{ $logo }}">
    @else
        <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
        <link rel="shortcut icon" href="/favicons/favicon.ico">
    @endif

    <script>window.PterodactylSiteLocale = @json(\Pterodactyl\Services\Helpers\Locales::default());</script>
    <script>window.PterodactylDictionaryVersion = @json(\Pterodactyl\Services\Helpers\Locales::dictionaryVersion());</script>
    <script src="/js/translator.js?v={{ @filemtime(public_path('js/translator.js')) }}"></script>
    @include('partials.appearance')

    <style>
        @font-face { font-family: 'Geist'; font-style: normal; font-weight: 100 900; font-display: swap; src: url('/fonts/geist/geist-latin-wght-normal.woff2') format('woff2'); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
        @font-face { font-family: 'Geist'; font-style: normal; font-weight: 100 900; font-display: swap; src: url('/fonts/geist/geist-latin-ext-wght-normal.woff2') format('woff2'); unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
        @font-face { font-family: 'Geist Mono'; font-style: normal; font-weight: 100 900; font-display: swap; src: url('/fonts/geist/geist-mono-latin-wght-normal.woff2') format('woff2'); }

        /* The accent colour, blue until /js/appearance.js puts the one of the panel. */
        :root {
            --pd-accent-200: 157 206 255; --pd-accent-300: 108 181 255; --pd-accent-400: 59 157 255; --pd-accent-500: 10 132 255; --pd-accent-600: 9 116 224;
            --bg: #0a0a0a; --surface: rgba(17, 17, 17, 0.88); --line: rgba(255, 255, 255, 0.08); --line-strong: rgba(255, 255, 255, 0.14);
            --text: #ededed; --muted: #a1a1a1; --faint: #707070;
        }
        * { box-sizing: border-box; }
        html { background: var(--bg); scroll-behavior: smooth; }
        body { margin: 0; color: var(--text); font-family: Geist, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 15px; line-height: 1.6; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        ::selection { background: rgb(var(--pd-accent-500) / 0.4); color: #fff; }

        /* The background: a soft light of the accent at the top and a grid of dots that fades out. */
        .ld-bg { position: fixed; inset: 0; z-index: -1; pointer-events: none;
            background: radial-gradient(60% 45% at 50% -12%, rgb(var(--pd-accent-500) / 0.22), transparent 70%),
                radial-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px) 0 0 / 24px 24px;
            -webkit-mask-image: linear-gradient(180deg, #000 0%, #000 40%, transparent 100%); mask-image: linear-gradient(180deg, #000 0%, #000 40%, transparent 100%); }
        html[data-pd-background]:not([data-pd-background='liquid']) .ld-bg { display: none; }

        .ld-wrap { width: min(1120px, 100% - 40px); margin: 0 auto; }

        /* The top bar. */
        .ld-nav { position: sticky; top: 0; z-index: 10; background: rgba(10, 10, 10, 0.72); border-bottom: 1px solid var(--line); -webkit-backdrop-filter: saturate(160%) blur(14px); backdrop-filter: saturate(160%) blur(14px); }
        .ld-nav .ld-wrap { display: flex; align-items: center; gap: 28px; height: 60px; }
        .ld-brand { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 15px; letter-spacing: -0.01em; color: #fff; min-width: 0; }
        .ld-brand span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ld-mark { width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0; background: linear-gradient(135deg, rgb(var(--pd-accent-300)) 0%, rgb(var(--pd-accent-600)) 100%); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), 0 4px 14px -4px rgb(var(--pd-accent-500) / 0.6); }
        img.ld-mark { background: none; box-shadow: none; object-fit: contain; border-radius: 6px; }
        .ld-links { display: flex; gap: 4px; }
        .ld-links a { padding: 6px 10px; border-radius: 6px; font-size: 14px; color: var(--muted); transition: color .15s ease, background .15s ease; }
        .ld-links a:hover { color: #fff; background: rgba(255, 255, 255, 0.06); }
        .ld-actions { margin-left: auto; display: flex; gap: 8px; align-items: center; }

        .ld-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 36px; padding: 0 14px; border-radius: 8px; border: 1px solid var(--line-strong); background: transparent; color: var(--text); font-weight: 500; font-size: 14px; white-space: nowrap; transition: background .15s ease, border-color .15s ease, transform .15s ease; }
        .ld-btn:hover { background: rgba(255, 255, 255, 0.06); border-color: rgba(255, 255, 255, 0.22); }
        .ld-btn--primary { background: rgb(var(--pd-accent-500)); border-color: rgb(var(--pd-accent-500)); color: #fff; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18), 0 1px 2px rgba(0, 0, 0, 0.4); }
        .ld-btn--primary:hover { background: rgb(var(--pd-accent-400)); border-color: rgb(var(--pd-accent-400)); }
        .ld-btn--lg { height: 44px; padding: 0 20px; font-size: 15px; border-radius: 10px; }
        .ld-btn svg { width: 16px; height: 16px; }

        /* The hero. */
        .ld-hero { padding: 96px 0 56px; text-align: center; }
        .ld-pill { display: inline-flex; align-items: center; gap: 8px; padding: 5px 12px; border-radius: 999px; border: 1px solid var(--line-strong); background: rgba(255, 255, 255, 0.03); font-size: 13px; color: var(--muted); }
        .ld-dot { position: relative; width: 7px; height: 7px; border-radius: 50%; background: #3ecf8e; }
        .ld-dot::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: #3ecf8e; animation: ld-ping 2s cubic-bezier(0, 0, .2, 1) infinite; }
        @keyframes ld-ping { 75%, 100% { transform: scale(2.4); opacity: 0; } }
        .ld-hero h1 { margin: 22px auto 0; max-width: 860px; font-size: clamp(36px, 6.4vw, 68px); line-height: 1.04; font-weight: 600; letter-spacing: -0.045em;
            background: linear-gradient(180deg, #ffffff 30%, #a1a1a1 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .ld-hero p { margin: 22px auto 0; max-width: 620px; font-size: clamp(16px, 2vw, 19px); color: var(--muted); white-space: pre-line; }
        .ld-cta { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 34px; }

        /* A picture of the panel under the hero, drawn with plain markup (nothing is live). */
        .ld-shot { position: relative; margin: 8px auto 0; max-width: 980px; }
        .ld-shot::before { content: ''; position: absolute; inset: -60px -40px auto; height: 300px; z-index: -1; background: radial-gradient(50% 60% at 50% 40%, rgb(var(--pd-accent-500) / 0.25), transparent 70%); filter: blur(20px); }
        .ld-window { border-radius: 14px; border: 1px solid var(--line-strong); background: #0d0d0d; box-shadow: 0 40px 100px -40px rgba(0, 0, 0, 1), inset 0 1px 0 rgba(255, 255, 255, 0.06); overflow: hidden; text-align: left;
            -webkit-mask-image: linear-gradient(180deg, #000 70%, transparent 100%); mask-image: linear-gradient(180deg, #000 70%, transparent 100%); }
        .ld-bar { display: flex; align-items: center; gap: 7px; height: 40px; padding: 0 14px; border-bottom: 1px solid var(--line); background: #111; }
        .ld-light { width: 10px; height: 10px; border-radius: 50%; background: rgba(255, 255, 255, 0.14); }
        .ld-url { margin: 0 auto; padding: 3px 40px; border-radius: 6px; background: rgba(255, 255, 255, 0.05); font-size: 12px; color: var(--faint); }
        .ld-app { display: grid; grid-template-columns: 180px 1fr; min-height: 340px; }
        .ld-side { padding: 18px 12px; border-right: 1px solid var(--line); }
        .ld-side i { display: block; height: 9px; margin: 0 6px 16px; border-radius: 4px; background: rgba(255, 255, 255, 0.07); }
        .ld-side i.on { background: rgb(var(--pd-accent-500) / 0.55); width: 70%; }
        .ld-main { padding: 22px; }
        .ld-h { font-size: 18px; font-weight: 600; letter-spacing: -0.02em; color: #fff; }
        .ld-tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; }
        .ld-tile { position: relative; overflow: hidden; padding: 12px 14px 30px; border-radius: 10px; border: 1px solid var(--line); background: rgba(255, 255, 255, 0.02); }
        .ld-tile small { display: block; font-size: 11px; color: var(--faint); }
        .ld-tile b { font-size: 17px; font-weight: 600; color: #fff; font-variant-numeric: tabular-nums; }
        .ld-tile svg { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: 30px; }
        .ld-rows { margin-top: 12px; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        .ld-row { display: flex; align-items: center; gap: 12px; padding: 11px 14px; font-size: 13px; }
        .ld-row + .ld-row { border-top: 1px solid var(--line); }
        .ld-row .ico { width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0; }
        .ld-row .nm { color: #fff; font-weight: 500; }
        .ld-row .st { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; padding: 2px 8px; border-radius: 999px; font-size: 11px; }
        .ld-row .mono { font-family: 'Geist Mono', ui-monospace, monospace; color: var(--faint); font-size: 12px; }

        .ld-stats { display: flex; justify-content: center; gap: 48px; flex-wrap: wrap; margin: 8px 0 0; padding: 24px 0; }
        .ld-stat { text-align: center; }
        .ld-stat strong { display: block; font-size: 36px; font-weight: 600; letter-spacing: -0.03em; color: #fff; font-variant-numeric: tabular-nums; }
        .ld-stat span { font-size: 14px; color: var(--muted); }

        /* The sections. */
        .ld-section { padding: 72px 0 24px; }
        .ld-eyebrow { display: block; text-align: center; font-size: 13px; font-weight: 500; color: rgb(var(--pd-accent-400)); letter-spacing: 0.02em; }
        .ld-section h2 { margin: 8px auto 40px; max-width: 640px; text-align: center; font-size: clamp(26px, 3.6vw, 40px); line-height: 1.1; font-weight: 600; letter-spacing: -0.035em; color: #fff; }
        .ld-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }

        /* A card with a hairline edge a little brighter at the top, like light falling on it. */
        .ld-card { position: relative; padding: 24px; border-radius: 16px; background: var(--surface); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4), 0 12px 32px -20px rgba(0, 0, 0, 0.8); transition: transform .2s ease; }
        .ld-card::before { content: ''; position: absolute; inset: 0; border-radius: inherit; padding: 1px; pointer-events: none;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.04));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); -webkit-mask-composite: xor; mask: linear-gradient(#000 0 0) content-box exclude, linear-gradient(#000 0 0); }
        .ld-card:hover { transform: translateY(-2px); }
        .ld-card h3 { margin: 18px 0 6px; font-size: 16px; font-weight: 600; letter-spacing: -0.01em; color: #fff; }
        .ld-card p { margin: 0; color: var(--muted); font-size: 14.5px; white-space: pre-line; }
        .ld-icon-box { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; color: rgb(var(--pd-accent-300)); background: rgb(var(--pd-accent-500) / 0.12); border: 1px solid rgb(var(--pd-accent-500) / 0.25); }
        .ld-icon-box svg { width: 20px; height: 20px; }

        .ld-offer { display: flex; flex-direction: column; }
        .ld-offer h3 { margin-top: 0; }
        .ld-price { margin: 6px 0 4px; font-size: 34px; font-weight: 600; letter-spacing: -0.03em; color: #fff; font-variant-numeric: tabular-nums; }
        .ld-price small { font-size: 14px; font-weight: 400; color: var(--muted); letter-spacing: 0; }
        .ld-specs { margin: 18px 0 22px; padding: 18px 0 0; list-style: none; border-top: 1px solid var(--line); color: var(--muted); font-size: 14px; }
        .ld-specs li { display: flex; align-items: center; gap: 10px; padding: 4px 0; }
        .ld-specs li::before { content: ''; width: 14px; height: 14px; flex-shrink: 0; border-radius: 50%; background: rgb(var(--pd-accent-500) / 0.18) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 12 4 4 8-8'/%3E%3C/svg%3E") center / 9px no-repeat; }
        .ld-offer .ld-btn { margin-top: auto; width: 100%; }
        .ld-soldout { margin-top: auto; text-align: center; color: #ff9592; font-weight: 500; font-size: 14px; }

        .ld-about { max-width: 720px; margin: 0 auto; text-align: center; color: var(--muted); font-size: 17px; white-space: pre-line; }

        /* The last call to action. */
        .ld-final { position: relative; margin: 80px 0 0; padding: 56px 24px; border-radius: 20px; text-align: center; overflow: hidden; background: var(--surface); }
        .ld-final::after { content: ''; position: absolute; inset: 0; z-index: 0; pointer-events: none; background: radial-gradient(60% 80% at 50% 0%, rgb(var(--pd-accent-500) / 0.22), transparent 70%); }
        .ld-final > * { position: relative; z-index: 1; }
        .ld-final h2 { margin: 0 0 10px; font-size: clamp(24px, 3.4vw, 36px); font-weight: 600; letter-spacing: -0.035em; color: #fff; }
        .ld-final p { margin: 0 auto 28px; max-width: 480px; color: var(--muted); }

        .ld-footer { margin-top: 64px; padding: 28px 0 40px; border-top: 1px solid var(--line); color: var(--faint); font-size: 13px; }
        .ld-footer .ld-wrap { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .ld-footer p { margin: 0; white-space: pre-line; }

        @media (max-width: 760px) {
            .ld-links { display: none; }
            .ld-hero { padding: 64px 0 40px; }
            .ld-app { grid-template-columns: 1fr; }
            .ld-side { display: none; }
            .ld-tiles { grid-template-columns: repeat(2, 1fr); }
            .ld-tiles .ld-tile:nth-child(3) { display: none; }
        }
        @media (max-width: 420px) { .ld-actions .ld-btn:not(.ld-btn--primary) { display: none; } }
        @media (prefers-reduced-motion: reduce) { .ld-dot::after { animation: none; } .ld-card, .ld-btn { transition: none; } }
    </style>
</head>
<body>
<div class="ld-bg" aria-hidden="true"></div>

<nav class="ld-nav">
    <div class="ld-wrap">
        <a class="ld-brand" href="/welcome">
            @if ($logo)<img class="ld-mark" src="{{ $logo }}" alt="">@else<span class="ld-mark" aria-hidden="true"></span>@endif
            <span>{{ $name }}</span>
        </a>
        <div class="ld-links">
            @if ($hasFeatures)<a href="#features"><span>Features</span></a>@endif
            @if ($hasOffers)<a href="#offers"><span>Offers</span></a>@endif
            @if ($hasAbout)<a href="#about"><span>About</span></a>@endif
        </div>
        <div class="ld-actions">
            @if ($signedIn)
                <a class="ld-btn ld-btn--primary" href="/"><span>Dashboard</span></a>
            @else
                <a class="ld-btn" href="/auth/login"><span>Log in</span></a>
                @if ($showRegister)<a class="ld-btn ld-btn--primary" href="/auth/register"><span>Create an account</span></a>@endif
            @endif
        </div>
    </div>
</nav>

<div class="ld-wrap">
    <header class="ld-hero">
        @if ($stats !== null && $stats['servers'] > 0)
            <span class="ld-pill"><span class="ld-dot"></span>{{ number_format($stats['servers'], 0, ',', ' ') }} {{ trans_choice('landing.servers_hosted', $stats['servers']) }}</span>
        @else
            <span class="ld-pill"><span class="ld-dot"></span>{{ $name }}</span>
        @endif
        <h1>{{ $content['title'] }}</h1>
        @if ($content['subtitle'] !== '')<p>{{ $content['subtitle'] }}</p>@endif
        <div class="ld-cta">
            @if ($content['cta_primary_text'] !== '' && $content['cta_primary_link'] !== '')
                <a class="ld-btn ld-btn--primary ld-btn--lg" href="{{ $content['cta_primary_link'] }}">{{ $content['cta_primary_text'] }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            @endif
            @if ($content['cta_secondary_text'] !== '' && $content['cta_secondary_link'] !== '' && (!str_starts_with($content['cta_secondary_link'], '/auth/register') || $registration))
                <a class="ld-btn ld-btn--lg" href="{{ $content['cta_secondary_link'] }}">{{ $content['cta_secondary_text'] }}</a>
            @endif
        </div>
    </header>

    {{-- A picture of the panel: plain markup, nothing is live. --}}
    <div class="ld-shot" aria-hidden="true">
        <div class="ld-window">
            <div class="ld-bar"><span class="ld-light"></span><span class="ld-light"></span><span class="ld-light"></span><span class="ld-url">{{ request()->getHost() }}</span></div>
            <div class="ld-app">
                <div class="ld-side"><i class="on"></i><i style="width:60%"></i><i style="width:75%"></i><i style="width:55%"></i><i style="width:68%"></i><i style="width:50%"></i></div>
                <div class="ld-main">
                    <div class="ld-h"><span>Servers</span></div>
                    <div class="ld-tiles">
                        <div class="ld-tile"><small>CPU</small><b>34.2 %</b>
                            <svg viewBox="0 0 100 30" preserveAspectRatio="none"><path d="M0 22 C10 20 14 14 22 16 S36 24 44 18 S58 8 66 12 S82 20 90 14 L100 12 L100 30 L0 30Z" fill="rgb(var(--pd-accent-500) / .18)"/><path d="M0 22 C10 20 14 14 22 16 S36 24 44 18 S58 8 66 12 S82 20 90 14 L100 12" fill="none" stroke="rgb(var(--pd-accent-400))" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg></div>
                        <div class="ld-tile"><small>Memory</small><b>5.1 GiB</b>
                            <svg viewBox="0 0 100 30" preserveAspectRatio="none"><path d="M0 20 C12 19 20 18 30 17 S50 15 60 14 S80 12 100 10 L100 30 L0 30Z" fill="rgba(34,211,238,.16)"/><path d="M0 20 C12 19 20 18 30 17 S50 15 60 14 S80 12 100 10" fill="none" stroke="#22d3ee" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg></div>
                        <div class="ld-tile"><small>Players</small><b>18 / 64</b>
                            <svg viewBox="0 0 100 30" preserveAspectRatio="none"><path d="M0 24 C8 22 16 18 26 19 S42 12 52 14 S70 20 80 12 S94 8 100 9 L100 30 L0 30Z" fill="rgba(167,139,250,.16)"/><path d="M0 24 C8 22 16 18 26 19 S42 12 52 14 S70 20 80 12 S94 8 100 9" fill="none" stroke="#a78bfa" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg></div>
                    </div>
                    <div class="ld-rows">
                        <div class="ld-row"><span class="ico" style="background:#ea580c"></span><span class="nm">survival-1</span><span class="mono">:25565</span><span class="st" style="background:#3ecf8e1f;color:#6ee7b7">● <span>Running</span></span></div>
                        <div class="ld-row"><span class="ico" style="background:rgb(var(--pd-accent-500))"></span><span class="nm">rp-city</span><span class="mono">:30120</span><span class="st" style="background:#3ecf8e1f;color:#6ee7b7">● <span>Running</span></span></div>
                        <div class="ld-row"><span class="ico" style="background:#16a34a"></span><span class="nm">discord-bot</span><span class="mono">node</span><span class="st" style="background:#7070701f;color:#d4d4d4">● <span>Offline</span></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($hasFeatures)
        <section class="ld-section" id="features">
            <span class="ld-eyebrow"><span>Features</span></span>
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

    @if ($hasOffers)
        <section class="ld-section" id="offers">
            <span class="ld-eyebrow"><span>Offers</span></span>
            @if ($content['offers_title'] !== '')<h2>{{ $content['offers_title'] }}</h2>@endif
            <div class="ld-grid">
                @foreach ($offers as $offer)
                    <div class="ld-card ld-offer">
                        <h3>{{ $offer['name'] }}</h3>
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

    @if ($hasAbout)
        <section class="ld-section" id="about">
            <span class="ld-eyebrow"><span>About</span></span>
            @if ($content['about_title'] !== '')<h2>{{ $content['about_title'] }}</h2>@endif
            <div class="ld-about">{{ $content['about_text'] }}</div>
        </section>
    @endif

    @if (!$signedIn)
        <section class="ld-final">
            <h2><span>Ready to start?</span></h2>
            <p><span>Create your account in a minute and launch your first server.</span></p>
            <div class="ld-cta" style="margin-top:0">
                @if ($showRegister)<a class="ld-btn ld-btn--primary ld-btn--lg" href="/auth/register"><span>Create an account</span></a>@endif
                <a class="ld-btn ld-btn--lg" href="/auth/login"><span>Log in</span></a>
            </div>
        </section>
    @endif
</div>

<footer class="ld-footer">
    <div class="ld-wrap">
        <a class="ld-brand" href="/welcome">
            @if ($logo)<img class="ld-mark" src="{{ $logo }}" alt="" style="width:20px;height:20px">@else<span class="ld-mark" aria-hidden="true" style="width:20px;height:20px;border-radius:6px"></span>@endif
            <span>{{ $name }}</span>
        </a>
        <div>
            @if ($content['footer_text'] !== '')<p>{{ $content['footer_text'] }}</p>@endif
            <p>{{ $copyright['text'] ?? ($name . ' © ' . date('Y')) }}</p>
        </div>
    </div>
</footer>
</body>
</html>
