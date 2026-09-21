<?php

namespace Pterodactyl\Services\Shop\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\ShopPayment;
use Pterodactyl\Services\Shop\ShopSettings;

/**
 * PayPal Checkout (orders API): the person approves the payment on PayPal, then the panel captures it.
 */
class PayPalProvider implements PaymentProvider
{
    public function __construct(private ShopSettings $settings)
    {
    }

    public function code(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function configured(): bool
    {
        return $this->settings->has('paypal:client_id') && $this->settings->has('paypal:secret');
    }

    public function createCheckout(ShopPayment $payment, string $successUrl, string $cancelUrl, string $description): array
    {
        $response = $this->api()->withHeaders(['PayPal-Request-Id' => $payment->token])->post($this->base() . '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $payment->token,
                'custom_id' => $payment->token,
                'description' => mb_substr($description, 0, 120),
                'amount' => ['currency_code' => $payment->currency, 'value' => $this->format($payment->amount_cents)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ]]],
        ]);

        $link = collect($response->json('links') ?? [])->first(fn ($link) => in_array($link['rel'] ?? '', ['payer-action', 'approve'], true));
        if (!$response->successful() || !is_string($response->json('id')) || !$link) {
            Log::warning('PayPal refused to open a payment.', ['status' => $response->status(), 'error' => $response->json('message')]);

            throw new DisplayException('PayPal did not accept the payment. Try again, or choose another way to pay.');
        }

        return ['ref' => $response->json('id'), 'url' => $link['href']];
    }

    public function status(ShopPayment $payment): string
    {
        if (!$payment->provider_ref) {
            return self::PENDING;
        }

        $order = $this->api()->get($this->base() . '/v2/checkout/orders/' . rawurlencode($payment->provider_ref));
        if (!$order->successful()) {
            return self::PENDING;
        }

        // The person approved: the money is taken now. Capturing twice is harmless, PayPal answers with the first one.
        if ($order->json('status') === 'APPROVED') {
            $order = $this->api()->withHeaders(['PayPal-Request-Id' => $payment->token . '-capture'])
                ->withBody('{}', 'application/json')
                ->post($this->base() . '/v2/checkout/orders/' . rawurlencode($payment->provider_ref) . '/capture');
            if (!$order->successful()) {
                return self::PENDING;
            }
        }

        if ($order->json('status') === 'COMPLETED') {
            $capture = $order->json('purchase_units.0.payments.captures.0');
            $matches = is_array($capture)
                && ($capture['status'] ?? '') === 'COMPLETED'
                && ($capture['amount']['value'] ?? '') === $this->format($payment->amount_cents)
                && ($capture['amount']['currency_code'] ?? '') === $payment->currency;

            return $matches ? self::PAID : self::FAILED;
        }

        return in_array($order->json('status'), ['VOIDED'], true) ? self::FAILED : self::PENDING;
    }

    private function base(): string
    {
        return $this->settings->get('paypal:sandbox') === '1' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private function format(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    /**
     * A request that is signed in with a fresh access token.
     */
    private function api(): \Illuminate\Http\Client\PendingRequest
    {
        $token = Http::asForm()->timeout(15)
            ->withBasicAuth((string) $this->settings->get('paypal:client_id'), (string) $this->settings->get('paypal:secret'))
            ->post($this->base() . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (!$token->successful() || !is_string($token->json('access_token'))) {
            Log::warning('PayPal did not give an access token.', ['status' => $token->status()]);

            throw new DisplayException('PayPal could not be reached. Try again, or choose another way to pay.');
        }

        return Http::acceptJson()->asJson()->timeout(15)->withToken($token->json('access_token'));
    }
}
