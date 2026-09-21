<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Shop\Payments\StripeProvider;
use Pterodactyl\Services\Shop\Payments\SumUpProvider;
use Pterodactyl\Services\Shop\ShopService;

/**
 * The calls of the payment providers, for the people who pay and then close their browser before coming back. No session
 * and no user here: a call is only used to know which payment to ask the provider about, and the answer of the provider
 * is what settles it (see ShopService::settle). Stripe also signs its calls, which are refused without the right signature.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private ShopService $shop)
    {
    }

    public function stripe(Request $request, StripeProvider $stripe): JsonResponse
    {
        $payment = $stripe->paymentFromWebhook($request->getContent(), $request->header('Stripe-Signature'));
        if ($payment) {
            $this->shop->settle($payment);
        }

        // Always the same answer, so that nobody can use this address to find out anything.
        return new JsonResponse(['received' => true]);
    }

    public function sumup(Request $request, SumUpProvider $sumup): JsonResponse
    {
        $payment = $sumup->paymentFromWebhook($request->getContent());
        if ($payment) {
            $this->shop->settle($payment);
        }

        return new JsonResponse(['received' => true]);
    }
}
