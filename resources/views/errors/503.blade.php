@php
    // Drawn once, when the panel goes down for its update ("artisan down --render"): the panel does not answer while
    // it is down, so the logo is put inside the page and nothing here asks the panel anything but "are you back?".
    $pdName = config('app.name', 'Pterodactyl');
    $pdFrench = str_starts_with(\Pterodactyl\Services\Helpers\Locales::default(), 'fr');
    $pdLogo = null;
    $pdSite = null;
    try {
        $pdAppearance = app(\Pterodactyl\Services\Helpers\Appearance::class);
        $pdSite = $pdAppearance->site();
        $pdLogoPath = $pdAppearance->logoPath();
        if ($pdLogoPath && filesize($pdLogoPath) <= 300 * 1024) {
            $pdTypes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'];
            $pdType = $pdTypes[strtolower(pathinfo($pdLogoPath, PATHINFO_EXTENSION))] ?? null;
            if ($pdType) {
                $pdLogo = 'data:' . $pdType . ';base64,' . base64_encode((string) file_get_contents($pdLogoPath));
            }
        }
    } catch (\Throwable) {
        $pdSite = null;
    }
    $pdSince = time();
    $pdText = $pdFrench ? [
        'title' => 'Mise à jour en cours',
        'sub' => 'Le panel installe une nouvelle version. Il revient tout seul dans quelques instants.',
        'running' => 'Vos serveurs de jeu et vos VPS continuent de tourner pendant la mise à jour.',
        'since' => 'Depuis',
        'checking' => 'Vérification automatique toutes les 5 secondes',
        'back' => 'Le panel est de retour, rechargement…',
        'retry' => 'Réessayer maintenant',
    ] : [
        'title' => 'Update in progress',
        'sub' => 'The panel is installing a new version. It comes back on its own in a few moments.',
        'running' => 'Your game servers and VPS keep running during the update.',
        'since' => 'For',
        'checking' => 'Checked automatically every 5 seconds',
        'back' => 'The panel is back, reloading…',
        'retry' => 'Try again now',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $pdFrench ? 'fr' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Without JavaScript, the page still comes back by itself. --}}
    <noscript><meta http-equiv="refresh" content="20"></noscript>
    <title>{{ $pdText['title'] }} &ndash; {{ $pdName }}</title>
    <meta name="theme-color" content="#0a0a0a">
    @if ($pdLogo)
        <link rel="icon" href="{{ $pdLogo }}">
    @else
        <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
    @endif
    @if ($pdSite)
        <script>window.PdAppearanceConfig = @json(['site' => $pdSite, 'user' => null]);</script>
        <script src="/js/appearance.js"></script>
    @endif
    <style>
        @font-face { font-family: 'Geist'; font-style: normal; font-weight: 100 900; font-display: swap; src: url('/fonts/geist/geist-latin-wght-normal.woff2') format('woff2'); }
        @font-face { font-family: 'Geist Mono'; font-style: normal; font-weight: 100 900; font-display: swap; src: url('/fonts/geist/geist-mono-latin-wght-normal.woff2') format('woff2'); }
        :root {
            --accent: rgb(var(--pd-accent-500, 99 102 241));
            --accent-soft: rgb(var(--pd-accent-400, 129 140 248));
            --text: #ededed; --muted: #a1a1a1; --faint: #737373; --line: rgba(255, 255, 255, 0.08);
        }
        * { box-sizing: border-box; }
        html { background: #0a0a0a; }
        html, body { min-height: 100%; margin: 0; }
        body {
            min-height: 100vh; display: flex; flex-direction: column; color: var(--text);
            font-family: Geist, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased;
        }
        body::before {
            content: ''; position: fixed; inset: 0; z-index: -1; pointer-events: none;
            background: radial-gradient(55% 45% at 50% 0%, rgb(var(--pd-accent-500, 99 102 241) / 0.16), transparent 70%);
        }
        body::after {
            content: ''; position: fixed; inset: 0; z-index: -1; pointer-events: none; opacity: .5;
            background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 48px 48px;
            -webkit-mask-image: radial-gradient(60% 50% at 50% 40%, #000, transparent 75%); mask-image: radial-gradient(60% 50% at 50% 40%, #000, transparent 75%);
        }
        header { display: flex; align-items: center; gap: 10px; padding: 20px 24px; font-weight: 600; font-size: 15px; letter-spacing: -0.01em; }
        .mark { width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0; background: linear-gradient(135deg, rgb(var(--pd-accent-300, 165 180 252)) 0%, rgb(var(--pd-accent-600, 79 70 229)) 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,.35); }
        img.mark { background: none; box-shadow: none; object-fit: contain; }
        main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px 16px 48px; }
        .card {
            width: 100%; max-width: 480px; text-align: center; border-radius: 16px; padding: 40px 32px 28px;
            background: rgb(17 17 17 / 0.88); border: 1px solid var(--line);
            box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }
        .ring { position: relative; width: 76px; height: 76px; margin: 0 auto 26px; }
        .ring svg.track { position: absolute; inset: 0; width: 100%; height: 100%; animation: spin 1.4s linear infinite; }
        .ring .icon { position: absolute; inset: 14px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgb(var(--pd-accent-500, 99 102 241) / 0.12); color: var(--accent-soft); }
        .ring .icon svg { width: 22px; height: 22px; animation: spin 6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h1 { margin: 0 0 10px; font-size: 22px; font-weight: 600; letter-spacing: -0.02em; }
        .sub { margin: 0 auto; max-width: 360px; color: var(--muted); font-size: 14.5px; line-height: 1.6; }
        .bar { position: relative; height: 3px; margin: 28px 0 0; border-radius: 3px; background: rgba(255,255,255,.06); overflow: hidden; }
        .bar::after { content: ''; position: absolute; top: 0; bottom: 0; width: 40%; border-radius: 3px; background: linear-gradient(90deg, transparent, var(--accent), transparent); animation: slide 1.6s ease-in-out infinite; }
        @keyframes slide { from { left: -40%; } to { left: 100%; } }
        .meta { display: flex; justify-content: center; gap: 12px; margin-top: 12px; font-size: 12px; color: var(--faint); }
        .meta strong { font-family: 'Geist Mono', ui-monospace, monospace; font-weight: 500; color: var(--muted); }
        .note { display: flex; align-items: flex-start; gap: 10px; margin-top: 24px; padding: 12px 14px; border-radius: 10px; text-align: left; font-size: 13px; line-height: 1.5; color: #d4d4d4; background: rgb(62 207 142 / .06); border: 1px solid rgb(62 207 142 / .2); }
        .note svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; color: #3ecf8e; }
        .foot { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 22px; font-size: 12px; color: var(--faint); }
        .foot i { width: 6px; height: 6px; border-radius: 50%; background: #f5a524; animation: pulse 1.6s ease-in-out infinite; }
        .foot button { font: inherit; color: var(--muted); background: none; border: 0; padding: 0; cursor: pointer; text-decoration: underline; text-underline-offset: 2px; }
        .foot button:hover { color: var(--text); }
        @keyframes pulse { 50% { opacity: .3; } }
        body.is-back .foot i { background: #3ecf8e; animation: none; }
        body.is-back .bar::after { animation: none; left: 0; width: 100%; background: #3ecf8e; }
        @media (prefers-reduced-motion: reduce) { .ring svg.track, .ring .icon svg, .bar::after, .foot i { animation: none; } }
        @media (max-width: 480px) { .card { padding: 32px 20px 22px; } .meta { flex-direction: column; align-items: center; gap: 4px; } }
    </style>
</head>
<body>
    <header>
        @if ($pdLogo)
            <img class="mark" src="{{ $pdLogo }}" alt="">
        @else
            <span class="mark" aria-hidden="true"></span>
        @endif
        <span>{{ $pdName }}</span>
    </header>
    <main>
        <div class="card" role="status" aria-live="polite">
            <div class="ring">
                <svg class="track" viewBox="0 0 76 76" aria-hidden="true">
                    <circle cx="38" cy="38" r="35" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="3"></circle>
                    <circle cx="38" cy="38" r="35" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="55 165" style="color: var(--accent)"></circle>
                </svg>
                <span class="icon">
                    <svg viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M487.4 315.7l-42.6-24.6c4.3-23.2 4.3-47 0-70.2l42.6-24.6c4.9-2.8 7.1-8.6 5.5-14-11.1-35.6-30-67.8-54.7-94.6-3.8-4.1-10-5.1-14.8-2.3L380.8 110c-17.9-15.4-38.5-27.3-60.8-35.1V25.8c0-5.6-3.9-10.5-9.4-11.7-36.7-8.2-74.3-7.8-109.2 0-5.5 1.2-9.4 6.1-9.4 11.7V75c-22.2 7.9-42.8 19.8-60.8 35.1L88.7 85.5c-4.9-2.8-11-1.9-14.8 2.3-24.7 26.7-43.6 58.9-54.7 94.6-1.7 5.4.6 11.2 5.5 14L67.3 221c-4.3 23.2-4.3 47 0 70.2l-42.6 24.6c-4.9 2.8-7.1 8.6-5.5 14 11.1 35.6 30 67.8 54.7 94.6 3.8 4.1 10 5.1 14.8 2.3l42.6-24.6c17.9 15.4 38.5 27.3 60.8 35.1v49.2c0 5.6 3.9 10.5 9.4 11.7 36.7 8.2 74.3 7.8 109.2 0 5.5-1.2 9.4-6.1 9.4-11.7v-49.2c22.2-7.9 42.8-19.8 60.8-35.1l42.6 24.6c4.9 2.8 11 1.9 14.8-2.3 24.7-26.7 43.6-58.9 54.7-94.6 1.6-5.4-.6-11.2-5.5-14zM256 336c-44.1 0-80-35.9-80-80s35.9-80 80-80 80 35.9 80 80-35.9 80-80 80z"/></svg>
                </span>
            </div>
            <h1 id="pd-title">{{ $pdText['title'] }}</h1>
            <p class="sub">{{ $pdText['sub'] }}</p>
            <div class="bar" aria-hidden="true"></div>
            <div class="meta">
                <span>{{ $pdText['since'] }} <strong id="pd-elapsed">0:00</strong></span>
            </div>
            <div class="note">
                <svg viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M173.9 439.4l-166.4-166.4c-10-10-10-26.2 0-36.2l36.2-36.2c10-10 26.2-10 36.2 0L192 312.7 432.1 72.6c10-10 26.2-10 36.2 0l36.2 36.2c10 10 10 26.2 0 36.2l-294.4 294.4c-10 10-26.2 10-36.2 0z"/></svg>
                <span>{{ $pdText['running'] }}</span>
            </div>
            <div class="foot"><i></i><span id="pd-state">{{ $pdText['checking'] }}</span> &middot; <button type="button" id="pd-retry">{{ $pdText['retry'] }}</button></div>
        </div>
    </main>
    <script>
        (function () {
            // The time since the panel went down (kept from when this page was drawn, not from when it was opened).
            var since = {{ $pdSince }} * 1000;
            var elapsed = document.getElementById('pd-elapsed');
            var tick = function () {
                var s = Math.max(0, Math.floor((Date.now() - since) / 1000));
                var m = Math.floor(s / 60);
                elapsed.textContent = (m >= 60 ? Math.floor(m / 60) + ':' + String(m % 60).padStart(2, '0') : m) + ':' + String(s % 60).padStart(2, '0');
            };
            tick();
            setInterval(tick, 1000);

            // Every 5 seconds: is the panel back? Then reload the page that was asked for.
            var back = false;
            var check = function () {
                if (back) return;
                fetch(window.location.href, { method: 'HEAD', cache: 'no-store', credentials: 'same-origin', redirect: 'manual' })
                    .then(function (response) {
                        if (response.status !== 503 && response.status !== 502 && response.status !== 504) {
                            back = true;
                            document.body.classList.add('is-back');
                            document.getElementById('pd-state').textContent = @json($pdText['back']);
                            setTimeout(function () { window.location.reload(); }, 800);
                        }
                    })
                    .catch(function () { /* Not back yet. */ });
            };
            setInterval(check, 5000);
            document.getElementById('pd-retry').addEventListener('click', check);
        })();
    </script>
</body>
</html>
