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
use Pterodactyl\Models\WebAccount;
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
        private SuspensionService $suspensions,
        private ?\Pterodactyl\Services\Web\WebHostingService $web = null
    ) {
    }

    /**
     * The web hosting, for the offers that are web hosting plans.
     */
    private function web(): \Pterodactyl\Services\Web\WebHostingService
    {
        return $this->web ??= app(\Pterodactyl\Services\Web\WebHostingService::class);
    }

    /**
     * What is needed to make the site of a web hosting plan, checked before anything is charged: a name, and a domain that
     * is free (the buyer's own, or a name under the domain of the hosting).
     *
     * @param array<string, mixed> $options site_name, domain and subdomain, as the buyer wrote them
     *
     * @return array{name: string, domain: string|null}
     *
     * @throws DisplayException
     */
    private function prepareWeb(ShopOffer $offer, array $options): array
    {
        $plan = $offer->webPlan;
        if (!$plan || !$plan->enabled) {
            throw new DisplayException('This offer is not available.');
        }
        $name = trim((string) ($options['site_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            throw new DisplayException('Give a name to your site (80 characters at most).');
        }

        return ['name' => $name, 'domain' => $this->web()->resolveDomain($options['domain'] ?? null, $options['subdomain'] ?? null, $name)];
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
    public function startPayment(User $user, string $providerCode, int $amountCents, ?ShopOffer $buy = null, ?ShopOrder $renew = null, array $options = []): ShopPayment
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
            // The site of a web hosting plan is checked now, so that nobody pays for a name that is already taken.
            if ($buy && $buy->isWeb()) {
                $this->prepareWeb($buy, $options);
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
            'buy_options' => $buy && $buy->isWeb() ? array_map(fn ($value) => mb_substr(trim((string) $value), 0, 253), array_intersect_key($options, array_flip(['site_name', 'domain', 'subdomain']))) : null,
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
                $this->purchase($user, $offer, $payment->buy_options ?? []);
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
    public function purchase(User $user, ShopOffer $offer, array $options = []): ShopOrder
    {
        $this->assertEnabled();
        $web = $offer->isWeb() ? $this->prepareWeb($offer, $options) : null;

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
            ]);
            $this->move($user->id, 'purchase', -$offer->price_cents, $offer->name, ['order_id' => $order->id]);
            if ($offer->stock !== null) {
                $offer->decrement('stock');
            }

            return [$order, $offer];
        });

        $accountId = null;
        try {
            if ($web !== null) {
                [$account, $site] = $this->web()->provisionForOrder($user, $offer->webPlan, $web['name'], $web['domain']);
                $serverId = $site->server_id;
                $accountId = $account->id;
            } else {
                $serverId = $this->provisioner->provision($user, $offer)->id;
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->giveBack($order, $offer);

            throw new DisplayException($web !== null
                ? 'The site could not be made, and you were not charged. Try again later or contact the support.'
                : 'The server could not be made, and you were not charged. Try again later or contact the support.');
        }

        $order->update(['server_id' => $serverId, 'web_account_id' => $accountId, 'status' => ShopOrder::ACTIVE, 'expires_at' => now()->addDays($order->duration_days)]);

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

        $wasExpired = false;
        $order = DB::transaction(function () use ($user, $order, &$wasExpired) {
            $order = ShopOrder::query()->whereKey($order->id)->lockForUpdate()->first();
            if (!$order || $order->user_id !== $user->id || !in_array($order->status, [ShopOrder::ACTIVE, ShopOrder::EXPIRED], true) || !$order->server_id) {
                throw new DisplayException('This order cannot be renewed.');
            }
            $wasExpired = $order->status === ShopOrder::EXPIRED;

            $this->move($user->id, 'renewal', -$order->price_cents, $order->offer_name, ['order_id' => $order->id]);
            $from = $order->expires_at && $order->expires_at->isFuture() ? $order->expires_at : now();
            $order->update([
                'status' => ShopOrder::ACTIVE,
                'expires_at' => $from->copy()->addDays($order->duration_days),
                'renewals' => $order->renewals + 1,
            ]);

            return $order;
        });

        $this->liftExpiry($order, $wasExpired);

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
                if ($order->web_account_id) {
                    // A web hosting plan: the whole account stops (all its sites), and nothing is deleted.
                    $account = WebAccount::query()->find($order->web_account_id);
                    if ($account && $account->status === WebAccount::ACTIVE) {
                        $this->web()->setAccountSuspended($account, true);
                        ++$count;
                    }
                    $order->update(['status' => ShopOrder::EXPIRED]);

                    continue;
                }

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
    private function liftExpiry(ShopOrder $order, bool $wasExpired = true): void
    {
        try {
            if ($order->web_account_id) {
                $account = WebAccount::query()->find($order->web_account_id);
                if ($account && $wasExpired && $account->status === WebAccount::SUSPENDED) {
                    $this->web()->setAccountSuspended($account, false);
                }

                return;
            }

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
