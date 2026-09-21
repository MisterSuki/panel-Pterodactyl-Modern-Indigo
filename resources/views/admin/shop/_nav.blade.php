@php
    $tabs = [
        ['admin.shop.offers', 'Offers', 'admin.shop.offers*', true],
        ['admin.shop.orders', 'Orders', 'admin.shop.orders*', true],
        ['admin.shop.credit', 'Credit and payments', 'admin.shop.credit*', true],
        ['admin.shop.settings', 'Settings', 'admin.shop.settings*', (bool) auth()->user()->root_admin],
    ];
@endphp
<div class="pd-tabs">
    @foreach ($tabs as [$route, $label, $pattern, $visible])
        @if ($visible)
            <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}"><span>{{ $label }}</span></a>
        @endif
    @endforeach
</div>
@unless (app(\Pterodactyl\Services\Shop\ShopSettings::class)->enabled())
    <div class="alert alert-warning"><span>The shop is closed: nobody can see or buy anything until you open it in the settings.</span></div>
@endunless
