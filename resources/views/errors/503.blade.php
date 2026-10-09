<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- The app is down during an update, so the page refreshes itself and comes back on its own. --}}
    <meta http-equiv="refresh" content="20">
    <title>Maintenance</title>
    <style>
        :root {
            --bg: #0b0f17;
            --card: #141a26;
            --border: rgba(255, 255, 255, 0.06);
            --primary: #818cf8;
            --text: #e8eaf0;
            --muted: #9aa3b2;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            background: radial-gradient(1100px 560px at 50% -10%, rgba(99, 102, 241, 0.18), transparent 60%), var(--bg);
            color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.03), transparent), var(--card);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 44px 32px;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.45);
        }
        .badge {
            width: 74px;
            height: 74px;
            border-radius: 18px;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(99, 102, 241, 0.14);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: var(--primary);
        }
        .gear { width: 38px; height: 38px; animation: spin 5s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h1 { font-size: 22px; margin: 0 0 12px; font-weight: 700; letter-spacing: -0.01em; }
        p { color: var(--muted); font-size: 14.5px; line-height: 1.65; margin: 0 0 4px; }
        .dots { display: inline-flex; gap: 7px; margin-top: 24px; }
        .dots span {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--primary); opacity: 0.35;
            animation: pulse 1.4s ease-in-out infinite;
        }
        .dots span:nth-child(2) { animation-delay: 0.2s; }
        .dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes pulse {
            0%, 100% { opacity: 0.25; transform: translateY(0); }
            50% { opacity: 1; transform: translateY(-4px); }
        }
        .foot { margin-top: 28px; font-size: 12px; color: #6b7280; }
        @media (prefers-reduced-motion: reduce) {
            .gear, .dots span { animation: none; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">
            <svg class="gear" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true">
                <path d="M487.4 315.7l-42.6-24.6c4.3-23.2 4.3-47 0-70.2l42.6-24.6c4.9-2.8 7.1-8.6 5.5-14-11.1-35.6-30-67.8-54.7-94.6-3.8-4.1-10-5.1-14.8-2.3L380.8 110c-17.9-15.4-38.5-27.3-60.8-35.1V25.8c0-5.6-3.9-10.5-9.4-11.7-36.7-8.2-74.3-7.8-109.2 0-5.5 1.2-9.4 6.1-9.4 11.7V75c-22.2 7.9-42.8 19.8-60.8 35.1L88.7 85.5c-4.9-2.8-11-1.9-14.8 2.3-24.7 26.7-43.6 58.9-54.7 94.6-1.7 5.4.6 11.2 5.5 14L67.3 221c-4.3 23.2-4.3 47 0 70.2l-42.6 24.6c-4.9 2.8-7.1 8.6-5.5 14 11.1 35.6 30 67.8 54.7 94.6 3.8 4.1 10 5.1 14.8 2.3l42.6-24.6c17.9 15.4 38.5 27.3 60.8 35.1v49.2c0 5.6 3.9 10.5 9.4 11.7 36.7 8.2 74.3 7.8 109.2 0 5.5-1.2 9.4-6.1 9.4-11.7v-49.2c22.2-7.9 42.8-19.8 60.8-35.1l42.6 24.6c4.9 2.8 11 1.9 14.8-2.3 24.7-26.7 43.6-58.9 54.7-94.6 1.6-5.4-.6-11.2-5.5-14zM256 336c-44.1 0-80-35.9-80-80s35.9-80 80-80 80 35.9 80 80-35.9 80-80 80z"/>
            </svg>
        </div>
        <h1>Maintenance en cours</h1>
        <p>Le panel est en cours de mise à jour.</p>
        <p>Il revient dans quelques instants.</p>
        <div class="dots"><span></span><span></span><span></span></div>
        <div class="foot">Cette page se rafraîchit automatiquement &middot; This page refreshes on its own</div>
    </div>
</body>
</html>
