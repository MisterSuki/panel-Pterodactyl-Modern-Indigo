<?php

namespace Pterodactyl\Services\Shop\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\ShopPayment;
use Pterodactyl\Services\Shop\ShopSettings;

/**
 * SumUp hosted checkout: the person pays on a page of SumUp.
 */
class SumUpProvider implements PaymentProvider
{
    private const API = 'https://api.sumup.com/v0.1';

    public function __construct(private ShopSettings $settings)
    {
    }

    public function code(): string
    {
        return 'sumup';
    }

    public function label(): string
    {
        return 'SumUp';
    }

    public function configured(): bool
    {
        return $this->settings->has('sumup:api_key') && $this->settings->has('sumup:merchant_code');
    }

    public function createCheckout(ShopPayment $payment, string $successUrl, string $cancelUrl, string $description): array
    {
        $response = $this->api()->post(self::API . '/checkouts', [
            'checkout_reference' => $payment->token,
            'amount' => round($payment->amount_cents / 100, 2),
            'currency' => $payment->currency,
            'merchant_code' => $this->settings->get('sumup:merchant_code'),
            'description' => mb_substr($description, 0, 120),
            'redirect_url' => $successUrl,
            'hosted_checkout' => ['enabled' => true],
        ]);

        if (!$response->successful() || !is_string($response->json('id')) || !is_string($response->json('hosted_checkout_url'))) {
            Log::warning('SumUp refused to open a payment.', ['status' => $response->status(), 'error' => $response->json('message')]);

            throw new DisplayException('SumUp did not accept the payment. Try again, or choose another way to pay.');
        }

        return ['ref' => $response->json('id'), 'url' => $response->json('hosted_checkout_url')];
    }

    public function status(ShopPayment $payment): string
    {
        if (!$payment->provider_ref) {
            return self::PENDING;
        }

        $response = $this->api()->get(self::API . '/checkouts/' . rawurlencode($payment->provider_ref));
        if (!$response->successful()) {
            return self::PENDING;
        }

        $status = strtoupper((string) $response->json('status'));
        if ($status === 'PAID') {
            $matches = abs((float) $response->json('amount') * 100 - $payment->amount_cents) < 0.5
                && strtoupper((string) $response->json('currency')) === $payment->currency
                && $response->json('checkout_reference') === $payment->token;

            return $matches ? self::PAID : self::FAILED;
        }

        return in_array($status, ['FAILED', 'EXPIRED'], true) ? self::FAILED : self::PENDING;
    }

    /**
     * The payment that a call from SumUp is about. SumUp does not sign its calls, so nothing in the call is believed: it
     * only tells which payment to ask SumUp about, and the answer of SumUp is what counts.
     */
    public function paymentFromWebhook(string $payload): ?ShopPayment
    {
        $event = json_decode($payload, true);
        $ref = is_array($event) ? ($event['id'] ?? $event['payload']['checkout_id'] ?? null) : null;

        return is_string($ref) ? ShopPayment::query()->where('provider', 'sumup')->where('provider_ref', $ref)->first() : null;
    }

    private function api(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout(15)->withToken((string) $this->settings->get('sumup:api_key'));
    }
}
