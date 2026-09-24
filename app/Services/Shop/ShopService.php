<?php

namespace Pterodactyl\Services\Shop;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ShopOffer;
use Pterodactyl\Models\ShopOrder;
use Pterodactyl\Models\ShopPayment;
use Pterodactyl\Models\ShopTransaction;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Shop\Payments\PaymentProvider;
use Pterodactyl\Services\Shop\Payments\PayPalProvider;
use Pterodactyl\Services\Shop\Payments\StripeProvider;
use Pterodactyl\Services\Shop\Payments\SumUpProvider;

/**
 * The shop: the credit of the people, what they buy with it, and the money that comes in to add some.
 *
 * Every amount is a whole number of cents. The credit only changes through move(), one transaction at a time and with
 * the wallet locked, so two things happening at once can never spend the same money twice or make it negative. Money
 * that comes in is settled once (see settle()), and an offer that could not be delivered is always paid back.
 */
class ShopService
{
    /**
     * Written as the reason when a server is suspended because it is not paid any more, so that only those are given
     * back when it is paid again.
     */
    public const EXPIRED_REASON = 'Shop: the time paid for has ended.';

    public const CANCELLED_REASON = 'Shop: the order was cancelled.';

    /**
     * The smallest payment the providers take, in cents.
     */
    public const MIN_PAYMENT = 50;

    /**
     * Payment providers to use instead of the real ones (for the tests).
     *
     * @var array<string, PaymentProvider>|null
     */
    public ?array $providerOverrides = null;

    public function __construct(
        private ShopSettings $settings,
        private ServerProvisioner $provisioner,
        private SuspensionService $suspensions
    ) {
    }

    /**
     * @return array<string, PaymentProvider> by code
     */
    public function providers(): array
    {
        return $this->providerOverrides ?? [
            'stripe' => app(StripeProvider::class),
            'paypal' => app(PayPalProvider::class),
            'sumup' => app(SumUpProvider::class),
        ];
    }

    /**
     * The providers that people can use: switched on and with their keys.
     *
     * @return array<string, PaymentProvider>
     */
    public function availableProviders(): array
    {
        return array_filter($this->providers(), fn (PaymentProvider $provider) => $this->settings->providerSwitchedOn($provider->code()) && $provider->configured());
    }

    public function balance(int $userId): int
    {
        return (int) DB::table('shop_wallets')->where('user_id', $userId)->value('balance_cents');
    }

    /**
     * The only place where the credit changes.
     *
     * @param array{payment_id?: int|null, order_id?: int|null, staff_id?: int|null} $refs
     *
     * @throws DisplayException when it would take more than what there is
     */
    public function move(int $userId, string $type, int $amount, ?string $note = null, array $refs = []): ShopTransaction
    {
        return DB::transaction(function () use ($userId, $type, $amount, $note, $refs) {
            DB::table('shop_wallets')->insertOrIgnore(['user_id' => $userId, 'balance_cents' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $balance = (int) DB::table('shop_wallets')->where('user_id', $userId)->lockForUpdate()->value('balance_cents');

            $after = $balance + $amount;
            if ($after < 0) {
                throw new DisplayException('You do not have enough credit.');
            }

            DB::table('shop_wallets')->where('user_id', $userId)->update(['balance_cents' => $after, 'updated_at' => now()]);

            return ShopTransaction::query()->create([
                'user_id' => $userId,
                'type' => $type,
                'amount_cents' => $amount,
                'balance_after_cents' => $after,
                'note' => $note === null ? null : Str::limit($note, 160, ''),
                'payment_id' => $refs['payment_id'] ?? null,
                'order_id' => $refs['order_id'] ?? null,
                'staff_id' => $refs['staff_id'] ?? null,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * A correction of the credit made by a member of the staff.
     *
     * @throws DisplayException
     */
    public function adjust(User $user, int $cents, string $note, User $staff): ShopTransaction
    {
        if ($cents === 0) {
            throw new DisplayException('The amount cannot be zero.');
        }

        return $this->move($user->id, 'adjust', $cents, $note, ['staff_id' => $staff->id]);
    }

    /**
     * Opens a payment at a provider, to add credit (and to buy an offer or renew an order with it as soon as it arrives).
     *
     * @throws DisplayException
     */
    public function startPayment(User $user, string $providerCode, int $amountCents, ?ShopOffer $buy = null, ?ShopOrder $renew = null): ShopPayment
    {
        $this->assertEnabled();
        $provider = $this->availableProviders()[$providerCode] ?? null;
        if (!$provider) {
            throw new DisplayException('This way of paying is not available.');
        }

        if ($buy || $renew) {
            $price = $buy ? $buy->price_cents : $renew->price_cents;
            if ($buy && !$buy->isAvailable()) {
                throw new DisplayException('This offer is not available.');
            }
            if ($renew && $renew->user_id !== $user->id) {
                throw new DisplayException('This order is not yours.');
            }
            // Only what is missing is asked.
            $amountCents = max(self::MIN_PAYMENT, $price - $this->balance($user->id));
        } elseif ($amountCents < $this->settings->minTopup() || $amountCents > $this->settings->maxTopup()) {
            throw new DisplayException('The amount must be between ' . $this->format($this->settings->minTopup()) . ' and ' . $this->format($this->settings->maxTopup()) . '.');
        }

        $payment = ShopPayment::query()->create([
            'token' => Str::random(40),
            'user_id' => $user->id,
            'provider' => $provider->code(),
            'amount_cents' => $amountCents,
            'currency' => $this->settings->currency(),
            'status' => ShopPayment::PENDING,
            'buy_offer_id' => $buy?->id,
            'renew_order_id' => $renew?->id,
        ]);

        try {
            $checkout = $provider->createCheckout(
                $payment,
                route('shop.return', ['provider' => $provider->code(), 'p' => $payment->token]),
                route('shop.return', ['provider' => $provider->code(), 'p' => $payment->token, 'cancel' => 1]),
                config('app.name', 'Panel') . ' - credit'
            );
        } catch (\Throwable $exception) {
            $payment->update(['status' => ShopPayment::FAILED]);

            throw $exception;
        }

        $payment->update(['provider_ref' => $checkout['ref'], 'checkout_url' => $checkout['url']]);

        return $payment;
    }

    /**
     * Asks the provider where a payment stands and, if it is paid, adds the credit, once and only once: whoever comes
     * first (the person coming back, the call of the provider) does it, the others find it done. The offer or renewal
     * that was waiting for the money is then made.
     *
     * @return string one of PaymentProvider::PAID, PENDING, FAILED
     */
    public function settle(ShopPayment $payment): string
    {
        if ($payment->status === ShopPayment::PAID) {
            return PaymentProvider::PAID;
        }
        if ($payment->status !== ShopPayment::PENDING) {
            return PaymentProvider::FAILED;
        }

        $provider = $this->providers()[$payment->provider] ?? null;
        if (!$provider) {
            return PaymentProvider::PENDING;
        }

        try {
            $status = $provider->status($payment);
        } catch (\Throwable $exception) {
            Log::warning('A payment could not be checked.', ['payment' => $payment->id, 'error' => $exception->getMessage()]);

            return PaymentProvider::PENDING;
        }

        if ($status === PaymentProvider::FAILED) {
            ShopPayment::query()->whereKey($payment->id)->where('status', ShopPayment::PENDING)->update(['status' => ShopPayment::FAILED]);

            return PaymentProvider::FAILED;
        }
        if ($status !== PaymentProvider::PAID) {
            return PaymentProvider::PENDING;
        }

        $credited = DB::transaction(function () use ($payment, $provider) {
            $fresh = ShopPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (!$fresh || $fresh->status !== ShopPayment::PENDING) {
                return false;
            }
            $fresh->update(['status' => ShopPayment::PAID, 'paid_at' => now()]);
            $this->move($fresh->user_id, 'topup', $fresh->amount_cents, $provider->label(), ['payment_id' => $fresh->id]);

            return true;
        });

        if ($credited) {
            $this->runIntent($payment->refresh());
        }

        return PaymentProvider::PAID;
    }

    /**
     * What the person wanted to do with the money: buy an offer or renew an order. If it cannot be done (the offer sold
     * out meanwhile...) the money stays as credit, which is not lost.
     */
    private function runIntent(ShopPayment $payment): void
    {
        $user = User::query()->find($payment->user_id);
        if (!$user) {
            return;
        }

        try {
            if ($payment->buy_offer_id && ($offer = ShopOffer::query()->find($payment->buy_offer_id))) {
                $this->purchase($user, $offer);
            } elseif ($payment->renew_order_id && ($order = ShopOrder::query()->find($payment->renew_order_id))) {
                $this->renew($user, $order);
            }
        } catch (\Throwable $exception) {
            Log::warning('The purchase that waited for a payment could not be made.', ['payment' => $payment->id, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * Buys an offer with the credit. The credit is taken first; if the server then cannot be made, it is given back.
     *
     * @throws DisplayException
     */
    public function purchase(User $user, ShopOffer $offer): ShopOrder
    {
        $this->assertEnabled();

        [$order, $offer] = DB::transaction(function () use ($user, $offer) {
            $offer = ShopOffer::query()->whereKey($offer->id)->lockForUpdate()->first();
            if (!$offer || !$offer->isAvailable()) {
                throw new DisplayException('This offer is not available.');
            }

            $order = ShopOrder::query()->create([
                'user_id' => $user->id,
                'offer_id' => $offer->id,
                'offer_name' => $offer->name,
                'status' => ShopOrder::PROVISIONING,
                'price_cents' => $offer->price_cents,
                'duration_days' => $offer->duration_days,
                // The resources of the offer become the floor the client cannot go below, kept so a later change of the
                // offer does not move it.
                'base_resources' => [
                    'memory' => (int) $offer->memory,
                    'disk' => (int) $offer->disk,
                    'cpu' => (int) $offer->cpu,
                    'database_limit' => (int) $offer->database_limit,
                    'backup_limit' => (int) $offer->backup_limit,
                    'allocation_limit' => (int) $offer->allocation_limit,
                ],
            ]);
            $this->move($user->id, 'purchase', -$offer->price_cents, $offer->name, ['order_id' => $order->id]);
            if ($offer->stock !== null) {
                $offer->decrement('stock');
            }

            return [$order, $offer];
        });

        try {
            $serverId = $this->provisioner->provision($user, $offer)->id;
        } catch (\Throwable $exception) {
            report($exception);
            $this->giveBack($order, $offer);

            throw new DisplayException('The server could not be made, and you were not charged. Try again later or contact the support.');
        }

        $order->update(['server_id' => $serverId, 'status' => ShopOrder::ACTIVE, 'expires_at' => now()->addDays($order->duration_days)]);

        return $order->refresh();
    }

    /**
     * Pays for another period of an order. If the server was suspended because the time had ended, it is given back.
     *
     * @throws DisplayException
     */
    public function renew(User $user, ShopOrder $order): ShopOrder
    {
        $this->assertEnabled();

        $order = DB::transaction(function () use ($user, $order) {
            $order = ShopOrder::query()->whereKey($order->id)->lockForUpdate()->first();
            if (!$order || $order->user_id !== $user->id || !in_array($order->status, [ShopOrder::ACTIVE, ShopOrder::EXPIRED], true) || !$order->server_id) {
                throw new DisplayException('This order cannot be renewed.');
            }
            // A custom server is billed every month on its own; it is not renewed by a fixed period.
            if ($order->offer_id === null) {
                throw new DisplayException('This server is billed monthly and does not need to be renewed.');
            }

            $this->move($user->id, 'renewal', -$order->price_cents, $order->offer_name, ['order_id' => $order->id]);
            $from = $order->expires_at && $order->expires_at->isFuture() ? $order->expires_at : now();
            $order->update([
                'status' => ShopOrder::ACTIVE,
                'expires_at' => $from->copy()->addDays($order->duration_days),
                'renewals' => $order->renewals + 1,
            ]);

            return $order;
        });

        $this->liftExpiry($order);

        return $order->refresh();
    }

    /**
     * Cancels an order: the server is suspended (nothing is deleted) and it stops being billed. It cannot be brought back
     * by the person; an administrator can remove the server or hand it back.
     *
     * @throws DisplayException
     */
    public function cancel(User $user, ShopOrder $order): ShopOrder
    {
        $order = ShopOrder::query()->whereKey($order->id)->first();
        if (!$order || $order->user_id !== $user->id) {
            throw new DisplayException('This order is not yours.');
        }
        if (!in_array($order->status, [ShopOrder::ACTIVE, ShopOrder::EXPIRED], true)) {
            throw new DisplayException('This order cannot be cancelled.');
        }

        if ($order->server_id) {
            try {
                $server = Server::query()->without('allocation')->find($order->server_id);
                if ($server && !$server->isSuspended()) {
                    $this->suspensions->toggle($server, SuspensionService::ACTION_SUSPEND, ['reason' => self::CANCELLED_REASON]);
                }
            } catch (\Throwable $exception) {
                Log::warning('A cancelled order could not have its server suspended.', ['order' => $order->id, 'error' => $exception->getMessage()]);
            }
        }

        // Stop future billing (custom servers) and mark it cancelled.
        $order->update(['status' => ShopOrder::CANCELLED, 'resource_cents' => 0]);

        return $order->refresh();
    }

    /**
     * Admin: extends an order (a game offer) for another period without charging, and gives the server back if it was
     * suspended. For a custom (monthly) server it just makes it active again and lifts the suspension.
     */
    public function adminRenew(ShopOrder $order): ShopOrder
    {
        $from = $order->expires_at && $order->expires_at->isFuture() ? $order->expires_at : now();
        $order->update([
            'status' => ShopOrder::ACTIVE,
            'expires_at' => $order->offer_id ? $from->copy()->addDays($order->duration_days) : null,
            'renewals' => $order->renewals + 1,
        ]);

        // The administrator asked for it, so the server comes back whatever the reason it was suspended.
        if ($order->server_id) {
            try {
                $server = Server::query()->without('allocation')->find($order->server_id);
                if ($server && $server->isSuspended()) {
                    $this->suspensions->toggle($server, SuspensionService::ACTION_UNSUSPEND);
                }
            } catch (\Throwable $exception) {
                Log::warning('A server could not be given back by an administrator.', ['order' => $order->id, 'error' => $exception->getMessage()]);
            }
        }

        return $order->refresh();
    }

    /**
     * Admin: suspends the server (nothing deleted) and stops billing.
     */
    public function adminCancel(ShopOrder $order): ShopOrder
    {
        if ($order->server_id) {
            try {
                $server = Server::query()->without('allocation')->find($order->server_id);
                if ($server && !$server->isSuspended()) {
                    $this->suspensions->toggle($server, SuspensionService::ACTION_SUSPEND, ['reason' => self::CANCELLED_REASON]);
                }
            } catch (\Throwable $exception) {
                Log::warning('Admin cancel could not suspend the server.', ['order' => $order->id, 'error' => $exception->getMessage()]);
            }
        }
        $order->update(['status' => ShopOrder::CANCELLED, 'resource_cents' => 0]);

        return $order->refresh();
    }

    /**
     * Admin: gives an amount back to the buyer's credit and cancels the order.
     */
    public function adminRefund(ShopOrder $order, int $cents): ShopOrder
    {
        if ($cents > 0) {
            $this->move($order->user_id, 'refund', $cents, $order->offer_name . ' (refund by an administrator)', ['order_id' => $order->id]);
        }
        if (in_array($order->status, [ShopOrder::ACTIVE, ShopOrder::EXPIRED], true)) {
            $this->adminCancel($order);
        }

        return $order->refresh();
    }

    /**
     * Suspends the servers that are not paid for any more. A server that cannot be reached is tried again the next time;
     * nothing is ever deleted, so the files of a person who comes back to pay are still there.
     *
     * @return int how many servers were suspended
     */
    public function expireDue(): int
    {
        $count = 0;
        $due = ShopOrder::query()->whereIn('status', [ShopOrder::ACTIVE, ShopOrder::EXPIRED])
            ->whereNotNull('server_id')->where('expires_at', '<=', now())->get();

        foreach ($due as $order) {
            try {
                $server = Server::query()->without('allocation')->find($order->server_id);
                if ($server && !$server->isSuspended()) {
                    $this->suspensions->toggle($server, SuspensionService::ACTION_SUSPEND, ['reason' => self::EXPIRED_REASON]);
                    ++$count;
                }
                $order->update(['status' => ShopOrder::EXPIRED]);
            } catch (\Throwable $exception) {
                Log::warning('A server that is not paid for could not be suspended.', ['order' => $order->id, 'error' => $exception->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * Gives the server back if it was suspended for having run out of time (and only then).
     */
    private function liftExpiry(ShopOrder $order): void
    {
        try {
            $server = Server::query()->without('allocation')->find($order->server_id);
            if ($server && $server->isSuspended() && optional(\Pterodactyl\Models\ServerSuspension::query()->where('server_id', $server->id)->first())->reason === self::EXPIRED_REASON) {
                $this->suspensions->toggle($server, SuspensionService::ACTION_UNSUSPEND);
            }
        } catch (\Throwable $exception) {
            Log::warning('A renewed server could not be given back.', ['order' => $order->id, 'error' => $exception->getMessage()]);
        }
    }

    private function giveBack(ShopOrder $order, ShopOffer $offer): void
    {
        DB::transaction(function () use ($order, $offer) {
            $this->move($order->user_id, 'refund', $order->price_cents, $order->offer_name . ' (not delivered)', ['order_id' => $order->id]);
            $order->update(['status' => ShopOrder::FAILED]);
            if ($offer->stock !== null) {
                ShopOffer::query()->whereKey($offer->id)->increment('stock');
            }
        });
    }

    private function assertEnabled(): void
    {
        if (!$this->settings->enabled()) {
            throw new DisplayException('The shop is closed.');
        }
    }

    /**
     * An amount of cents written for a person, with the currency.
     */
    public function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '') . ' ' . $this->settings->currency();
    }
}
