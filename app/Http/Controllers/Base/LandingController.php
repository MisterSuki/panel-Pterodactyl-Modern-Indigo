<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ShopOffer;
use Pterodactyl\Services\Auth\AuthFeatures;
use Pterodactyl\Services\Landing\LandingContent;
use Pterodactyl\Services\Shop\ShopSettings;

/**
 * The home page for visitors who are not signed in (see LandingContent). It is public: nothing here depends on who is
 * looking, and it only shows what the administration chose to show.
 */
class LandingController extends Controller
{
    public function __construct(private LandingContent $content, private ShopSettings $shop)
    {
    }

    public function show(Request $request): View
    {
        $content = $this->content->get(app()->getLocale());

        $offers = [];
        if ($content['show_offers'] && $this->shop->enabled()) {
            $offers = ShopOffer::query()->with('location:id,short')->where('enabled', true)->orderBy('position')->orderBy('price_cents')->limit(6)->get()
                ->map(fn (ShopOffer $offer) => [
                    'name' => $offer->name,
                    'description' => $offer->description,
                    'price' => number_format($offer->price_cents / 100, 2, '.', '') . ' ' . $this->shop->currency(),
                    'days' => $offer->duration_days,
                    'memory' => $offer->memory,
                    'disk' => $offer->disk,
                    'cpu' => $offer->cpu,
                    'available' => $offer->isAvailable(),
                ])->all();
        }

        return view('landing', [
            'content' => $content,
            'offers' => $offers,
            'stats' => $content['show_stats'] ? $this->stats() : null,
            'registration' => AuthFeatures::registrationEnabled(),
            'signedIn' => $request->user() !== null,
        ]);
    }

    /**
     * How many servers the panel hosts, for everybody to see. Only a number, kept for five minutes so that a busy home page
     * does not count on every visit.
     *
     * @return array{servers: int}
     */
    private function stats(): array
    {
        return Cache::remember('landing:stats', 300, function () {
            try {
                return ['servers' => (int) Server::query()->withoutGlobalScopes()->without('allocation')->count()];
            } catch (\Throwable) {
                return ['servers' => 0];
            }
        });
    }
}
