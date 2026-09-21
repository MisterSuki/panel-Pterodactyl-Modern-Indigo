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
}
