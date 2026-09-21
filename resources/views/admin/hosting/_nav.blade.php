@php
    $tabs = [
        ['admin.hosting.clients', 'Clients', 'admin.hosting.clients*|admin.hosting.account*', true],
        ['admin.hosting.sites', 'Sites', 'admin.hosting.sites*', true],
        ['admin.hosting.plans', 'Plans', 'admin.hosting.plans*', true],
        ['admin.hosting.settings', 'Settings', 'admin.hosting.settings*', (bool) auth()->user()->root_admin],
    ];
@endphp
<div class="pd-tabs">
    @foreach ($tabs as [$route, $label, $pattern, $visible])
        @if ($visible)
            <a href="{{ route($route) }}" class="{{ request()->routeIs(...explode('|', $pattern)) ? 'active' : '' }}"><span>{{ $label }}</span></a>
        @endif
    @endforeach
</div>
@unless (app(\Pterodactyl\Services\Web\WebHostingSettings::class)->enabled())
    <div class="alert alert-warning"><span>The web hosting is off: clients do not see their sites and no domain is served until you switch it on in the settings.</span></div>
@endunless
