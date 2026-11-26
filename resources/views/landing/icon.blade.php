{{-- One of the icons of the home page, by name (see LandingContent::ICONS). Drawn as lines, in the colour of the text. --}}
<svg class="ld-icon" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('server')
            <rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6.5h.01M7 17.5h.01"/>
            @break
        @case('bolt')
            <path d="M13 2 4 14h7l-1 8 9-12h-7z"/>
            @break
        @case('shield')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('headset')
            <path d="M4 15v-3a8 8 0 0 1 16 0v3"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/><path d="M19 20a4 4 0 0 1-4 2h-2"/>
            @break
        @case('globe')
            <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10 15 15 0 0 1-4-10 15 15 0 0 1 4-10z"/>
            @break
        @case('database')
            <ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>
            @break
        @case('users')
            <circle cx="9" cy="8" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/><path d="M16 4.5a4 4 0 0 1 0 7M22 21v-1a6 6 0 0 0-4-5.6"/>
            @break
        @case('gamepad')
            <rect x="2" y="7" width="20" height="12" rx="5"/><path d="M7 11v4M5 13h4M16 12h.01M18.5 14h.01"/>
            @break
        @case('cloud')
            <path d="M18 10h-1.3A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>
            @break
        {{-- The kinds of game of the shop (OfferGame). --}}
        @case('cube')
            <path d="M21 16V8l-9-5-9 5v8l9 5z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>
            @break
        @case('car')
            <path d="M5 17h14M5 17a2 2 0 1 1-4 0v-4l2-5a2 2 0 0 1 1.9-1.4h12.2A2 2 0 0 1 19 8l2 5v4a2 2 0 1 1-4 0"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
            @break
        @case('robot')
            <rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M9 13h.01M15 13h.01M9 17h6"/>
            @break
        @case('terminal')
            <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m6 9 3 3-3 3M12 15h6"/>
            @break
        @case('tree')
            <path d="M12 2 5 11h4l-4 6h14l-4-6h4z"/><path d="M12 17v5"/>
            @break
        @default
            <path d="m12 2 3 6.5 7 .8-5.2 4.8 1.5 7-6.3-3.6L5.7 21l1.5-7L2 9.3l7-.8z"/>
    @endswitch
</svg>
