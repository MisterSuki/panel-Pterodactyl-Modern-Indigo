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
        $games = [];
        if ($content['show_offers'] && $this->shop->enabled()) {
            $all = ShopOffer::query()->with(['location:id,short', 'category:id,name', 'egg.nest'])->where('enabled', true)
                ->orderBy('position')->orderBy('price_cents')->get();
            $price = fn (int $cents) => number_format($cents / 100, 2, '.', '') . ' ' . $this->shop->currency();
            $monthly = fn (ShopOffer $offer) => $offer->duration_days >= 28 && $offer->duration_days <= 31;

            // One card per category of the shop (or per game): its price "from".
            $games = $all->groupBy(fn (ShopOffer $offer) => (string) ($offer->category->name ?? $offer->egg->name ?? '-'))
                ->map(function ($list, $name) use ($price, $monthly) {
                    /** @var ShopOffer $cheapest */
                    $cheapest = $list->sortBy('price_cents')->first();

                    return [
                        'name' => (string) $name,
                        'game' => \Pterodactyl\Services\Shop\OfferGame::of($cheapest->egg),
                        'from' => $price($cheapest->price_cents),
                        'monthly' => $monthly($cheapest),
                        'days' => $cheapest->duration_days,
                        'count' => $list->count(),
                        'memory_min' => (int) $list->min('memory'),
                        'memory_max' => (int) $list->max('memory'),
                    ];
                })->values()->take(8)->all();

            // The offers put forward first ("Popular"), then the others.
            $offers = $all->sortBy(fn (ShopOffer $offer) => $offer->featured ? 0 : 1)->take(6)
                ->map(fn (ShopOffer $offer) => [
                    'name' => $offer->name,
                    'description' => $offer->description,
                    'price' => $price($offer->price_cents),
                    'days' => $offer->duration_days,
                    'monthly' => $monthly($offer),
                    'memory' => $offer->memory,
                    'disk' => $offer->disk,
                    'cpu' => $offer->cpu,
                    'featured' => (bool) $offer->featured,
                    'game' => \Pterodactyl\Services\Shop\OfferGame::of($offer->egg),
                    'available' => $offer->isAvailable(),
                ])->values()->all();
        }

        return view('landing', [
            'content' => $content,
            'offers' => $offers,
            'games' => $games,
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
