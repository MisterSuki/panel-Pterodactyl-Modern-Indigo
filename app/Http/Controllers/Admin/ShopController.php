<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Admin\Concerns\ReadsEggEnvironment;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\ShopCategory;
use Pterodactyl\Models\ShopOffer;
use Pterodactyl\Models\ShopOrder;
use Pterodactyl\Models\ShopPayment;
use Pterodactyl\Models\ShopTransaction;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Shop\ShopService;
use Pterodactyl\Services\Shop\ShopSettings;

/**
 * The shop, seen by the administration: the offers, the orders, the credit and the payments, and the settings with the
 * keys of the payment providers. Seeing is for the staff who can see the shop, changing needs the right to manage it (see
 * the "admin.can:shop" of the routes), and the settings are for full administrators only, since they hold the keys.
 */
class ShopController extends Controller
{
    use ReadsEggEnvironment;

    public function __construct(private ShopService $shop, private ShopSettings $settings, private AlertsMessageBag $alert)
    {
    }

    // ---- categories -----------------------------------------------------------------------------------------------

    public function categories(): View
    {
        return view('admin.shop.categories', [
            'categories' => ShopCategory::query()->withCount('offers')->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function saveCategory(Request $request, ?int $id = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);

        $category = $id ? ShopCategory::query()->findOrFail($id) : new ShopCategory();
        $category->fill(['name' => trim($data['name']), 'position' => (int) ($data['position'] ?? 0)])->save();
        $this->alert->success('The category was saved.')->flash();

        return redirect()->route('admin.shop.categories');
    }

    public function deleteCategory(int $id): RedirectResponse
    {
        // The offers of the category stay on sale, without a category.
        ShopOffer::query()->where('category_id', $id)->update(['category_id' => null]);
        ShopCategory::query()->findOrFail($id)->delete();
        $this->alert->success('The category was deleted. Its offers are still on sale, without a category.')->flash();

        return redirect()->route('admin.shop.categories');
    }

    // ---- offers ---------------------------------------------------------------------------------------------------

    public function offers(): View
    {
        return view('admin.shop.offers', [
            'offers' => ShopOffer::query()->with(['egg:id,name', 'location:id,short'])->orderBy('position')->orderBy('id')->get(),
            'currency' => $this->settings->currency(),
        ]);
    }

    public function offerForm(?int $id = null): View
    {
        $offer = $id ? ShopOffer::query()->findOrFail($id) : new ShopOffer(['duration_days' => 30, 'enabled' => true, 'memory' => 2048, 'disk' => 10240, 'cpu' => 0]);

        return view('admin.shop.offer', [
            'offer' => $offer,
            'eggs' => Egg::query()->with('nest:id,name')->orderBy('name')->get(['id', 'nest_id', 'name']),
            'locations' => Location::query()->orderBy('short')->get(['id', 'short']),
            'categories' => ShopCategory::query()->orderBy('position')->orderBy('name')->get(['id', 'name']),
            'requiredVariables' => $this->requiredVariables(),
            'currency' => $this->settings->currency(),
            'price' => $offer->exists ? $this->plain($offer->price_cents) : '',
            'environment' => collect($offer->environment ?? [])->map(fn ($value, $name) => $name . '=' . $value)->implode("\n"),
        ]);
    }

    public function saveOffer(Request $request, ?int $id = null): RedirectResponse
    {
        // The form gives the memory and the disk in GB; they are kept in MB.
        foreach (['memory', 'disk'] as $field) {
            if ($request->filled($field . '_gb')) {
                $request->merge([$field => (int) round((float) str_replace(',', '.', (string) $request->input($field . '_gb')) * 1024)]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'string'],
            'duration_days' => ['required', 'integer', 'between:1,365'],
            'egg_id' => ['required', 'integer', 'exists:eggs,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'category_id' => ['nullable', 'integer', 'exists:shop_categories,id'],
            'memory' => ['required', 'integer', 'min:64'],
            'disk' => ['required', 'integer', 'min:64'],
            'cpu' => ['required', 'integer', 'min:0'],
            'database_limit' => ['nullable', 'integer', 'min:0'],
            'allocation_limit' => ['nullable', 'integer', 'min:0'],
            'backup_limit' => ['nullable', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'position' => ['nullable', 'integer', 'min:0'],
            'environment' => ['nullable', 'string', 'max:4000'],
        ]);

        $price = $this->cents($data['price']);
        if ($price === null || $price < ShopService::MIN_PAYMENT) {
            throw ValidationException::withMessages(['price' => 'The price must be at least ' . $this->plain(ShopService::MIN_PAYMENT) . '.']);
        }
        $environment = $this->eggEnvironment((string) ($data['environment'] ?? ''), (int) $data['egg_id']);

        $values = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => $price,
            'duration_days' => (int) $data['duration_days'],
            'egg_id' => (int) $data['egg_id'],
            'location_id' => (int) $data['location_id'],
            'category_id' => ($data['category_id'] ?? '') === '' ? null : (int) $data['category_id'],
            'memory' => (int) $data['memory'],
            'disk' => (int) $data['disk'],
            'cpu' => (int) $data['cpu'],
            'database_limit' => (int) ($data['database_limit'] ?? 0),
            'allocation_limit' => (int) ($data['allocation_limit'] ?? 0),
            'backup_limit' => (int) ($data['backup_limit'] ?? 0),
            'stock' => ($data['stock'] ?? '') === '' ? null : (int) $data['stock'],
            'position' => (int) ($data['position'] ?? 0),
            'environment' => $environment ?: null,
            'enabled' => $request->boolean('enabled'),
        ];

        $offer = $id ? ShopOffer::query()->findOrFail($id) : new ShopOffer();
        $offer->fill($values)->save();
        $this->alert->success('The offer was saved.')->flash();

        return redirect()->route('admin.shop.offers');
    }

    public function deleteOffer(int $id): RedirectResponse
    {
        // What people already bought keeps its own copy of the name, the price and the duration.
        ShopOffer::query()->findOrFail($id)->delete();
        $this->alert->success('The offer was deleted. What was bought stays as it is.')->flash();

        return redirect()->route('admin.shop.offers');
    }

    // ---- orders, credit and payments ------------------------------------------------------------------------------

    public function orders(Request $request): View
    {
        $status = in_array($request->query('status'), [ShopOrder::ACTIVE, ShopOrder::EXPIRED, ShopOrder::PROVISIONING, ShopOrder::FAILED], true) ? $request->query('status') : null;

        return view('admin.shop.orders', [
            'orders' => ShopOrder::query()->with(['user:id,username', 'server:id,uuidShort'])->when($status, fn ($query) => $query->where('status', $status))->orderByDesc('id')->paginate(50)->withQueryString(),
            'status' => $status,
            'currency' => $this->settings->currency(),
        ]);
    }

    public function credit(): View
    {
        return view('admin.shop.credit', [
            'transactions' => ShopTransaction::query()->with('user:id,username')->orderByDesc('id')->paginate(50),
            'payments' => ShopPayment::query()->with('user:id,username')->orderByDesc('id')->limit(30)->get(),
            'currency' => $this->settings->currency(),
            'outstanding' => (int) DB::table('shop_wallets')->sum('balance_cents'),
        ]);
    }

    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user' => ['required', 'string', 'max:191'],
            'amount' => ['required', 'string'],
            'note' => ['required', 'string', 'max:160'],
        ]);

        $user = User::query()->where('username', $data['user'])->orWhere('email', $data['user'])->first();
        if (!$user) {
            throw ValidationException::withMessages(['user' => 'No user has this name or email.']);
        }
        $negative = str_starts_with(trim($data['amount']), '-');
        $cents = $this->cents(ltrim(trim($data['amount']), '+-'));
        if ($cents === null || $cents === 0) {
            throw ValidationException::withMessages(['amount' => 'Write an amount like 12.50 or -5.']);
        }

        try {
            $this->shop->adjust($user, $negative ? -$cents : $cents, $data['note'], $request->user());
        } catch (DisplayException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }
        $this->alert->success('The credit of ' . $user->username . ' is now ' . $this->plain($this->shop->balance($user->id)) . ' ' . $this->settings->currency() . '.')->flash();

        return redirect()->route('admin.shop.credit');
    }

    // ---- settings -------------------------------------------------------------------------------------------------

    public function settings(): View
    {
        $has = fn (string $key) => $this->settings->has($key);

        return view('admin.shop.settings', [
            'enabled' => $this->settings->enabled(),
            'currency' => $this->settings->currency(),
            'minTopup' => $this->plain($this->settings->minTopup()),
            'maxTopup' => $this->plain($this->settings->maxTopup()),
            'stripe' => ['on' => $this->settings->providerSwitchedOn('stripe'), 'secret' => $has('stripe:secret'), 'webhook' => $has('stripe:webhook_secret')],
            'paypal' => ['on' => $this->settings->providerSwitchedOn('paypal'), 'client' => (string) $this->settings->get('paypal:client_id'), 'secret' => $has('paypal:secret'), 'sandbox' => $this->settings->get('paypal:sandbox') === '1'],
            'sumup' => ['on' => $this->settings->providerSwitchedOn('sumup'), 'merchant' => (string) $this->settings->get('sumup:merchant_code'), 'key' => $has('sumup:api_key')],
            'webhooks' => ['stripe' => url('/api/payments/stripe'), 'sumup' => url('/api/payments/sumup')],
            'resources' => $this->resourceSettings(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'min_topup' => ['required', 'string'],
            'max_topup' => ['required', 'string'],
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'paypal_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_secret' => ['nullable', 'string', 'max:255'],
            'sumup_api_key' => ['nullable', 'string', 'max:255'],
            'sumup_merchant_code' => ['nullable', 'string', 'max:64'],
        ]);

        $min = $this->cents($data['min_topup']);
        $max = $this->cents($data['max_topup']);
        if ($min === null || $max === null || $min < ShopService::MIN_PAYMENT || $max < $min) {
            throw ValidationException::withMessages(['min_topup' => 'The smallest amount must be at least ' . $this->plain(ShopService::MIN_PAYMENT) . ' and not above the biggest.']);
        }

        $this->settings->set('enabled', $request->boolean('enabled') ? '1' : '0');
        $this->settings->set('currency', strtoupper($data['currency']));
        $this->settings->set('min_topup', (string) $min);
        $this->settings->set('max_topup', (string) $max);

        foreach (['stripe', 'paypal', 'sumup'] as $code) {
            $this->settings->set($code . ':enabled', $request->boolean($code . '_enabled') ? '1' : '0');
        }
        $this->settings->set('paypal:sandbox', $request->boolean('paypal_sandbox') ? '1' : '0');
        $this->settings->set('paypal:client_id', $data['paypal_client_id'] ?? null);
        $this->settings->set('sumup:merchant_code', $data['sumup_merchant_code'] ?? null);

        // Resource billing: the price per unit and the most a client may add, per component.
        $this->settings->set('res:enabled', $request->boolean('res_enabled') ? '1' : '0');
        $this->settings->set('res:due_days', (string) max(1, (int) $request->input('res_due_days', 7)));
        foreach (array_keys(ShopSettings::RESOURCES) as $key) {
            $price = $this->cents((string) $request->input('res_' . $key . '_price', ''));
            $this->settings->set('res:' . $key . ':price', $price === null ? '0' : (string) $price);
            $this->settings->set('res:' . $key . ':max', (string) max(0, (int) $request->input('res_' . $key . '_max', 0)));
        }

        // A secret is only replaced when a new one is typed, and removed when its box is ticked: the page never shows them.
        foreach ([
            'stripe:secret' => 'stripe_secret',
            'stripe:webhook_secret' => 'stripe_webhook_secret',
            'paypal:secret' => 'paypal_secret',
            'sumup:api_key' => 'sumup_api_key',
        ] as $key => $field) {
            if (!empty($data[$field])) {
                $this->settings->set($key, $data[$field]);
            } elseif ($request->boolean('clear_' . $field)) {
                $this->settings->set($key, null);
            }
        }

        $this->alert->success('The settings of the shop were saved.')->flash();

        return redirect()->route('admin.shop.settings');
    }

    // ---- helpers --------------------------------------------------------------------------------------------------

    /**
     * "12", "12.5" or "12,50" as cents, or null if it is not an amount.
     */
    private function cents(string $text): ?int
    {
        $text = trim($text);
        if (preg_match('/^(\d{1,7})(?:[.,](\d{1,2}))?$/', $text, $match) !== 1) {
            return null;
        }

        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '0', 2, '0');
    }

    private function plain(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * The per-component prices for the settings page.
     *
     * @return array{enabled: bool, dueDays: int, items: array<int, array{key: string, label: string, price: string, max: int}>}
     */
    private function resourceSettings(): array
    {
        $items = [];
        foreach (ShopSettings::RESOURCES as $key => $meta) {
            $items[] = [
                'key' => $key,
                'label' => $meta['label'],
                'price' => $this->plain($this->settings->resourcePrice($key)),
                'max' => $this->settings->resourceMax($key),
            ];
        }

        return [
            'enabled' => $this->settings->resourceBillingEnabled(),
            'dueDays' => $this->settings->invoiceDueDays(),
            'items' => $items,
        ];
    }
}
