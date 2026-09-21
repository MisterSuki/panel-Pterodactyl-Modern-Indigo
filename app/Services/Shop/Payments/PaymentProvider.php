<?php

namespace Pterodactyl\Services\Shop\Payments;

use Pterodactyl\Models\ShopPayment;

/**
 * A way to be paid. The panel never trusts what the browser of the person says about a payment: after they come back
 * (and when the provider calls the panel) it asks the provider itself, and it only believes the provider when the
 * amount and the currency are the ones that were asked.
 */
interface PaymentProvider
{
    public const PAID = 'paid';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public function code(): string;

    public function label(): string;

    /**
     * Whether the keys needed to use it have been given.
     */
    public function configured(): bool;

    /**
     * Opens a payment page at the provider.
     *
     * @return array{ref: string, url: string} the reference of the payment at the provider, and where to send the person
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function createCheckout(ShopPayment $payment, string $successUrl, string $cancelUrl, string $description): array;

    /**
     * Asks the provider where the payment stands. The result is "paid" only if it is paid for the exact amount and
     * currency of the payment.
     *
     * @return string one of self::PAID, self::PENDING, self::FAILED
     */
    public function status(ShopPayment $payment): string;
}
