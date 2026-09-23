<?php

namespace Pterodactyl\Services\Shop;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * The settings of the shop, kept with the other settings of the panel. The secrets (API keys) are kept encrypted and are
 * never given back to a page: the administration only learns whether one is set.
 */
class ShopSettings
{
    public const PROVIDERS = ['stripe', 'paypal', 'sumup'];

    /**
     * The settings that are secrets.
     */
    private const SECRETS = ['stripe:secret', 'stripe:webhook_secret', 'paypal:secret', 'sumup:api_key'];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->settings->get('shop:' . $key, null);
        if ($value === null || $value === '') {
            return $default;
        }
        if (in_array($key, self::SECRETS, true)) {
            try {
                return Crypt::decryptString((string) $value);
            } catch (DecryptException) {
                return $default;
            }
        }

        return (string) $value;
    }

    public function set(string $key, ?string $value): void
    {
        if ($value !== null && $value !== '' && in_array($key, self::SECRETS, true)) {
            $value = Crypt::encryptString($value);
        }
        $this->settings->set('shop:' . $key, $value === '' ? null : $value);
    }

    /**
     * Whether a secret has been given, without giving it.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function enabled(): bool
    {
        return $this->get('enabled') === '1';
    }

    /**
     * The currency of every price, as an ISO 4217 code.
     */
    public function currency(): string
    {
        $currency = strtoupper((string) $this->get('currency', 'EUR'));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'EUR';
    }

    /**
     * The smallest and the biggest amount of credit that can be bought at once, in cents.
     */
    public function minTopup(): int
    {
        return max(50, (int) $this->get('min_topup', '500'));
    }

    public function maxTopup(): int
    {
        return max($this->minTopup(), (int) $this->get('max_topup', '50000'));
    }

    /**
     * Whether the person who runs the panel switched a provider on.
     */
    public function providerSwitchedOn(string $code): bool
    {
        return $this->get($code . ':enabled') === '1';
    }

    // ---- Resource billing (per-component prices, billed monthly) ---------------------------------------------------

    /**
     * The resources a client can add to a server, each with the field it maps to on the server, the size of one unit and
     * a short label. Prices are set per unit, in cents.
     */
    public const RESOURCES = [
        'ram' => ['field' => 'memory', 'unit' => 1024, 'label' => 'RAM (per GB)'],
        'disk' => ['field' => 'disk', 'unit' => 1024, 'label' => 'Disk (per GB)'],
        'cpu' => ['field' => 'cpu', 'unit' => 100, 'label' => 'CPU (per 100%)'],
        'database' => ['field' => 'database_limit', 'unit' => 1, 'label' => 'Database'],
        'backup' => ['field' => 'backup_limit', 'unit' => 1, 'label' => 'Backup'],
        'port' => ['field' => 'allocation_limit', 'unit' => 1, 'label' => 'Extra port'],
    ];

    /**
     * Whether clients may change the resources of their servers (and be billed monthly for them).
     */
    public function resourceBillingEnabled(): bool
    {
        return $this->get('res:enabled') === '1';
    }

    /**
     * The price of one unit of a resource, in cents (0 means it cannot be added).
     */
    public function resourcePrice(string $key): int
    {
        return max(0, (int) $this->get('res:' . $key . ':price', '0'));
    }

    /**
     * The most units of a resource a client may add on top of the offer (0 means none).
     */
    public function resourceMax(string $key): int
    {
        return max(0, (int) $this->get('res:' . $key . ':max', '0'));
    }

    /**
     * How many days a client has to pay an invoice before their servers are suspended.
     */
    public function invoiceDueDays(): int
    {
        return max(1, (int) $this->get('res:due_days', '7'));
    }

    // ---- Custom servers (the client builds their own, billed monthly) ----------------------------------------------

    /**
     * Whether clients may build their own server from the resources, at the per-unit prices.
     */
    public function customEnabled(): bool
    {
        return $this->get('res:custom:enabled') === '1';
    }

    /**
     * The smallest and biggest number of units of a resource a client may pick when building a custom server.
     */
    public function customMin(string $key): int
    {
        return max(0, (int) $this->get('res:' . $key . ':cmin', '0'));
    }

    public function customMax(string $key): int
    {
        return max($this->customMin($key), (int) $this->get('res:' . $key . ':cmax', '0'));
    }

    /**
     * The eggs (games) a client may choose for a custom server.
     *
     * @return array<int, int>
     */
    public function customEggIds(): array
    {
        return $this->ids('res:custom:eggs');
    }

    /**
     * The locations a client may choose. Empty means the panel picks one on its own.
     *
     * @return array<int, int>
     */
    public function customLocationIds(): array
    {
        return $this->ids('res:custom:locations');
    }

    /**
     * How many custom servers one client may have (0 means no limit).
     */
    public function customMaxPerUser(): int
    {
        return max(0, (int) $this->get('res:custom:max_per_user', '0'));
    }

    /**
     * @return array<int, int>
     */
    private function ids(string $key): array
    {
        return collect(explode(',', (string) $this->get($key, '')))
            ->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
    }
}
