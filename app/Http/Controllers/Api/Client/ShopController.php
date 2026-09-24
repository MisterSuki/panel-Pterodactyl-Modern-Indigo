<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\ShopCategory;
use Pterodactyl\Models\ShopOffer;
use Pterodactyl\Models\ShopOrder;
use Pterodactyl\Models\ShopTransaction;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Shop\Payments\PaymentProvider;
use Pterodactyl\Services\Shop\ResourceBillingService;
use Pterodactyl\Services\Shop\ShopService;
use Pterodactyl\Services\Shop\ShopSettings;

/**
 * The shop, seen by the people who buy: what is for sale, their credit and their orders, and the actions to add credit,
 * buy and renew. Every price and every amount is decided here, from what the panel has; what the browser sends is only
 * which offer, which order and which way to pay.
 */
class ShopController extends ClientApiController
{
    public function __construct(private ShopService $shop, private ShopSettings $settings, private ResourceBillingService $billing)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->settings->enabled()) {
            return new JsonResponse(['enabled' => false]);
        }

        $user = $request->user();

        return new JsonResponse([
            'enabled' => true,
            'currency' => $this->settings->currency(),
            'balance_cents' => $this->shop->balance($user->id),
            'min_topup_cents' => $this->settings->minTopup(),
            'max_topup_cents' => $this->settings->maxTopup(),
            'providers' => array_values(array_map(fn (PaymentProvider $provider) => ['code' => $provider->code(), 'label' => $provider->label()], $this->shop->availableProviders())),
            // What is needed to build a custom server (empty/off when the feature is not on).
            'custom' => $this->billing->customConfig($user),
            // Only the categories that have something on sale are shown.
            'categories' => ShopCategory::query()->whereIn('id', ShopOffer::query()->where('enabled', true)->whereNotNull('category_id')->select('category_id'))
                ->orderBy('position')->orderBy('name')->get(['id', 'name'])->map(fn (ShopCategory $category) => ['id' => $category->id, 'name' => $category->name])->all(),
            'offers' => ShopOffer::query()->with('location:id,short')->where('enabled', true)->orderBy('position')->orderBy('price_cents')->get()
                ->map(fn (ShopOffer $offer) => [
                    'id' => $offer->id,
                    'category_id' => $offer->category_id,
                    'name' => $offer->name,
                    'description' => $offer->description,
                    'price_cents' => $offer->price_cents,
                    'duration_days' => $offer->duration_days,
                    'memory' => $offer->memory,
                    'disk' => $offer->disk,
                    'cpu' => $offer->cpu,
                    'databases' => $offer->database_limit,
                    'backups' => $offer->backup_limit,
                    'location' => $offer->location?->short,
                    'available' => $offer->isAvailable(),
                    'stock' => $offer->stock,
                ])->all(),
            'orders' => ShopOrder::query()->with('server:id,uuidShort,name')->where('user_id', $user->id)->where('status', '!=', ShopOrder::FAILED)->orderByDesc('id')->limit(50)->get()
                ->map(fn (ShopOrder $order) => [
                    'id' => $order->id,
                    'name' => $order->offer_name,
                    'status' => $order->status,
                    'price_cents' => $order->price_cents,
                    'duration_days' => $order->duration_days,
                    'expires_at' => $order->expires_at?->toAtomString(),
                    // A custom server has no offer: it is billed monthly, not renewed by a fixed period.
                    'custom' => $order->offer_id === null,
                    'monthly_cents' => (int) $order->resource_cents,
                    'server' => $order->server ? ['identifier' => $order->server->uuidShort, 'name' => $order->server->name] : null,
                ])->all(),
            'transactions' => ShopTransaction::query()->where('user_id', $user->id)->orderByDesc('id')->limit(30)->get()
                ->map(fn (ShopTransaction $transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount_cents' => $transaction->amount_cents,
                    'note' => $transaction->note,
                    'at' => $transaction->created_at?->toAtomString(),
                ])->all(),
        ]);
    }

    /**
     * Buys an offer with the credit.
     */
    public function buy(Request $request): JsonResponse
    {
        $request->validate(['offer_id' => ['required', 'integer']]);
        $offer = ShopOffer::query()->findOrFail((int) $request->input('offer_id'));

        $order = $this->shop->purchase($request->user(), $offer);

        return new JsonResponse(['order_id' => $order->id, 'balance_cents' => $this->shop->balance($request->user()->id)], 201);
    }

    /**
     * Pays for another period of an order, with the credit.
     */
    public function renew(Request $request): JsonResponse
    {
        $request->validate(['order_id' => ['required', 'integer']]);
        $order = ShopOrder::query()->where('user_id', $request->user()->id)->find((int) $request->input('order_id'));
        if (!$order) {
            throw new DisplayException('This order cannot be renewed.');
        }

        $this->shop->renew($request->user(), $order);

        return new JsonResponse(['balance_cents' => $this->shop->balance($request->user()->id)]);
    }

    /**
     * Cancels an order: the server is suspended and it stops being billed.
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate(['order_id' => ['required', 'integer']]);
        $order = ShopOrder::query()->where('user_id', $request->user()->id)->find((int) $request->input('order_id'));
        if (!$order) {
            throw new DisplayException('This order cannot be cancelled.');
        }

        $this->shop->cancel($request->user(), $order);

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Opens a payment at a provider: to add an amount of credit, or to pay for an offer or a renewal that the credit does
     * not cover (only what is missing is asked). Gives where to send the person.
     */
    public function topup(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string', 'in:stripe,paypal,sumup'],
            'amount_cents' => ['nullable', 'integer'],
            'offer_id' => ['nullable', 'integer'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $offer = $request->filled('offer_id') ? ShopOffer::query()->findOrFail((int) $request->input('offer_id')) : null;
        $order = $request->filled('order_id') ? ShopOrder::query()->where('user_id', $user->id)->findOrFail((int) $request->input('order_id')) : null;

        $payment = $this->shop->startPayment($user, (string) $request->input('provider'), (int) $request->input('amount_cents', 0), $offer, $order);

        return new JsonResponse(['url' => $payment->checkout_url], 201);
    }

    /**
     * The servers the person may change the resources of (bought in the shop, with resource billing on). Only the
     * identifiers, so the dashboard can show the little settings button on the right cards.
     */
    public function upgradeable(Request $request): JsonResponse
    {
        // Resources can be changed as soon as selling resources OR building custom servers is on: both use the same prices.
        $anyOn = $this->settings->resourceBillingEnabled() || $this->settings->customEnabled();
        if (!$anyOn) {
            return new JsonResponse(['servers' => []]);
        }

        $identifiers = ShopOrder::query()->where('user_id', $request->user()->id)
            ->where('status', ShopOrder::ACTIVE)->whereNotNull('server_id')
            // If only custom is on, only custom servers can be changed; if selling resources is on, all of them can.
            ->when(!$this->settings->resourceBillingEnabled(), fn ($q) => $q->whereNull('offer_id'))
            ->with('server:id,uuidShort')->get()
            ->map(fn (ShopOrder $order) => $order->server?->uuidShort)->filter()->values();

        return new JsonResponse(['servers' => $identifiers]);
    }

    /**
     * What a person may set for the resources of one of their servers, with the prices and what they have now.
     */
    public function resources(Request $request, string $server): JsonResponse
    {
        [$order] = $this->orderForServer($request, $server);

        $limits = $this->billing->limits($order);
        $meta = ShopSettings::RESOURCES;
        $items = [];
        foreach ($limits as $key => $range) {
            $items[] = [
                'key' => $key,
                'label' => $meta[$key]['label'],
                'unit' => $meta[$key]['unit'],
                'min' => $range['min'],
                'max' => $range['max'],
                'current' => $range['current'],
                'price_cents' => $range['price'],
                'billed_from' => $range['billedFrom'],
            ];
        }

        return new JsonResponse([
            'currency' => $this->settings->currency(),
            'balance_cents' => $this->shop->balance($request->user()->id),
            'monthly_cents' => (int) $order->resource_cents,
            'items' => $items,
        ]);
    }

    /**
     * Applies the resources a person chose to their server and records the part of the month left to pay.
     */
    public function updateResources(Request $request, string $server): JsonResponse
    {
        [$order] = $this->orderForServer($request, $server);
        $request->validate(['resources' => ['required', 'array']]);

        $chosen = [];
        foreach (array_keys(ShopSettings::RESOURCES) as $key) {
            if ($request->has('resources.' . $key)) {
                $chosen[$key] = (int) $request->input('resources.' . $key);
            }
        }

        $order = $this->billing->adjust($request->user(), $order, $chosen);

        return new JsonResponse([
            'monthly_cents' => (int) $order->resource_cents,
            'balance_cents' => $this->shop->balance($request->user()->id),
        ]);
    }

    /**
     * Builds a server the client configured themselves, billed monthly.
     */
    public function createCustom(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'egg_id' => ['required', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'resources' => ['required', 'array'],
        ]);

        $chosen = [];
        foreach (array_keys(ShopSettings::RESOURCES) as $key) {
            if ($request->has('resources.' . $key)) {
                $chosen[$key] = (int) $request->input('resources.' . $key);
            }
        }

        $order = $this->billing->createCustom(
            $request->user(),
            (int) $request->input('egg_id'),
            $request->filled('location_id') ? (int) $request->input('location_id') : null,
            $chosen,
            (string) $request->input('name'),
        );

        return new JsonResponse([
            'order_id' => $order->id,
            'server' => $order->server ? $order->server->uuidShort : null,
            'balance_cents' => $this->shop->balance($request->user()->id),
        ], 201);
    }

    /**
     * Finds the active shop order of one of the person's servers, refusing when resource billing is off or the server is
     * not theirs.
     *
     * @return array{0: ShopOrder}
     *
     * @throws DisplayException
     */
    private function orderForServer(Request $request, string $server): array
    {
        if (!$this->settings->resourceBillingEnabled() && !$this->settings->customEnabled()) {
            throw new DisplayException('Changing the resources is not available.');
        }
        $model = Server::query()->where('uuidShort', $server)->first();
        $order = $model
            ? ShopOrder::query()->where('user_id', $request->user()->id)->where('server_id', $model->id)
                ->where('status', ShopOrder::ACTIVE)->first()
            : null;
        if (!$order) {
            throw new DisplayException('This server cannot be changed.');
        }
        // When only custom servers may be adjusted, an offer-bought server cannot be changed here.
        if (!$this->settings->resourceBillingEnabled() && $order->offer_id !== null) {
            throw new DisplayException('This server cannot be changed.');
        }

        return [$order];
    }
}
