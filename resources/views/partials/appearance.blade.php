{{-- The look of the panel (accent colour and background), drawn by /js/appearance.js on every page. --}}
@php($pdAppearance = app(\Pterodactyl\Services\Helpers\Appearance::class))
<script>window.PdAppearanceConfig = @json(['site' => $pdAppearance->site(), 'user' => $pdAppearance->forUser(Auth::user())]);</script>
<script src="/js/appearance.js?v={{ @filemtime(public_path('js/appearance.js')) }}"></script>
<script src="/js/liquid-light.js?v={{ @filemtime(public_path('js/liquid-light.js')) }}" defer></script>
