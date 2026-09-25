<?php

namespace Pterodactyl\Services\Shop\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\ShopPayment;
use Pterodactyl\Services\Shop\ShopSettings;

/**
 * Stripe Checkout: the person pays on a page of Stripe, the card details never reach the panel.
 */
class StripeProvider implements PaymentProvider
{
    private const API = 'https://api.stripe.com/v1';

    /**
     * How old a call from Stripe may be, in seconds. An older one could be a copy sent again by somebody else.
     */
    private const TOLERANCE = 300;

    public function __construct(private ShopSettings $settings)
    {
    }

    public function code(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function configured(): bool
    {
        return $this->settings->has('stripe:secret');
    }

    public function createCheckout(ShopPayment $payment, string $successUrl, string $cancelUrl, string $description): array
    {
        $response = Http::asForm()->timeout(15)->withBasicAuth((string) $this->settings->get('stripe:secret'), '')
            ->post(self::API . '/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $payment->token,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($payment->currency),
                        'unit_amount' => $payment->amount_cents,
                        'product_data' => ['name' => mb_substr($description, 0, 120)],
                    ],
                ]],
            ]);

        if (!$response->successful() || !is_string($response->json('id')) || !is_string($response->json('url'))) {
            Log::warning('Stripe refused to open a payment.', ['status' => $response->status(), 'error' => $response->json('error.message')]);

            throw new DisplayException('Stripe did not accept the payment. Try again, or choose another way to pay.');
        }

        return ['ref' => $response->json('id'), 'url' => $response->json('url')];
    }

    public function status(ShopPayment $payment): string
    {
        if (!$payment->provider_ref) {
            return self::PENDING;
        }

        $response = Http::timeout(15)->withBasicAuth((string) $this->settings->get('stripe:secret'), '')
            ->get(self::API . '/checkout/sessions/' . rawurlencode($payment->provider_ref));
        if (!$response->successful()) {
            return self::PENDING;
        }

        if ($response->json('payment_status') === 'paid') {
            $matches = (int) $response->json('amount_total') === $payment->amount_cents
                && strtoupper((string) $response->json('currency')) === $payment->currency;

            return $matches ? self::PAID : self::FAILED;
        }

        return $response->json('status') === 'expired' ? self::FAILED : self::PENDING;
    }

    /**
     * The payment that a call from Stripe is about, if the call really comes from Stripe. The signature is checked with
     * the secret of the webhook, so nobody else can make the panel believe anything; and what the call says is only
     * used to know which payment to ask Stripe about.
     */
    public function paymentFromWebhook(string $payload, ?string $header): ?ShopPayment
    {
        $secret = $this->settings->get('stripe:webhook_secret');
        if (!$secret || !$header || !$this->signatureIsValid($payload, $header, $secret)) {
            return null;
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || !in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return null;
        }
        $ref = $event['data']['object']['id'] ?? null;

        return is_string($ref) ? ShopPayment::query()->where('provider', 'stripe')->where('provider_ref', $ref)->first() : null;
    }

    public function signatureIsValid(string $payload, string $header, string $secret, ?int $now = null): bool
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        if (!$timestamp || $signatures === [] || abs(($now ?? time()) - $timestamp) > self::TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
