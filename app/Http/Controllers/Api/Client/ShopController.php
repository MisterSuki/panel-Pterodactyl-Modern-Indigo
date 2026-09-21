<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\ShopCategory;
use Pterodactyl\Models\ShopOffer;
use Pterodactyl\Models\ShopOrder;
use Pterodactyl\Models\ShopTransaction;
use Pterodactyl\Services\Shop\Payments\PaymentProvider;
use Pterodactyl\Services\Shop\ShopService;
use Pterodactyl\Services\Shop\ShopSettings;

/**
 * The shop, seen by the people who buy: what is for sale, their credit and their orders, and the actions to add credit,
 * buy and renew. Every price and every amount is decided here, from what the panel has; what the browser sends is only
 * which offer, which order and which way to pay.
 */
class ShopController extends ClientApiController
{
    public function __construct(private ShopService $shop, private ShopSettings $settings)
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
}
