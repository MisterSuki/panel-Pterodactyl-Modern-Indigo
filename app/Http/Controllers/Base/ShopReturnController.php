<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\ShopPayment;
use Pterodactyl\Services\Shop\ShopService;

/**
 * Where a person lands after paying (or giving up) at a provider. Nothing in the address is believed: the payment is
 * found by its token, has to be the one of the person who is signed in, and is checked with the provider itself.
 */
class ShopReturnController extends Controller
{
    public function __construct(private ShopService $shop)
    {
    }

    public function return(Request $request, string $provider): RedirectResponse
    {
        $payment = ShopPayment::query()
            ->where('token', (string) $request->query('p'))
            ->where('provider', $provider)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$payment) {
            return redirect('/shop');
        }
        if ($request->boolean('cancel') && $payment->status === ShopPayment::PENDING) {
            return redirect('/shop?payment=cancelled');
        }

        return redirect('/shop?payment=' . $this->shop->settle($payment));
    }
}
