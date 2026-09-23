<?php

namespace Pterodactyl\Services\Shop;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ShopInvoice;
use Pterodactyl\Models\ShopInvoiceLine;
use Pterodactyl\Models\ShopOrder;
use Pterodactyl\Models\ShopResourceChange;
use Pterodactyl\Models\User;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Servers\BuildModificationService;

/**
 * Lets a client raise or lower the resources of a server bought in the shop, and bills the difference monthly, at the
 * price the administration set per component. A change made in the middle of a month is charged only for the part of the
 * month that is left; on the first of each month an invoice gathers the recurring cost and those mid-month changes, and
 * is paid from the credit.
 */
class ResourceBillingService
{
    public const UNPAID_REASON = 'Shop: an invoice has not been paid.';

    public function __construct(
        private ShopSettings $settings,
        private ShopService $shop,
        private BuildModificationService $builder,
        private SuspensionService $suspensions,
        private ServerProvisioner $provisioner,
    ) {
    }

    /**
     * A custom server is one a client built themselves: it has no offer, so every resource is billed (nothing is free).
     */
    public function isCustom(ShopOrder $order): bool
    {
        return $order->offer_id === null;
    }

    /**
     * The resources that are for sale (a price was set), key => price in cents.
     *
     * @return array<string, int>
     */
    public function prices(): array
    {
        $prices = [];
        foreach (array_keys(ShopSettings::RESOURCES) as $key) {
            $price = $this->settings->resourcePrice($key);
            if ($price > 0) {
                $prices[$key] = $price;
            }
        }

        return $prices;
    }

    /**
     * The resources of a server as whole units (GB of RAM, hundreds of percent of CPU, number of databases...).
     *
     * @param array<string, int|null> $fields the server fields (memory, disk, cpu, ...)
     *
     * @return array<string, int>
     */
    public function toUnits(array $fields): array
    {
        $units = [];
        foreach (ShopSettings::RESOURCES as $key => $meta) {
            $value = (int) ($fields[$meta['field']] ?? 0);
            $units[$key] = intdiv($value, $meta['unit']);
        }

        return $units;
    }

    /**
     * The baseline of an order (the offer's resources): what the client cannot go below and where the extra is counted
     * from.
     *
     * @return array<string, int> the server fields
     */
    public function baseline(ShopOrder $order): array
    {
        $fields = ['memory' => 0, 'disk' => 0, 'cpu' => 0, 'database_limit' => 0, 'backup_limit' => 0, 'allocation_limit' => 0];

        if (is_array($order->base_resources)) {
            return array_merge($fields, array_intersect_key($order->base_resources, $fields));
        }
        if ($order->offer) {
            return array_merge($fields, [
                'memory' => (int) $order->offer->memory,
                'disk' => (int) $order->offer->disk,
                'cpu' => (int) $order->offer->cpu,
                'database_limit' => (int) $order->offer->database_limit,
                'backup_limit' => (int) $order->offer->backup_limit,
                'allocation_limit' => (int) $order->offer->allocation_limit,
            ]);
        }
        // No offer left and no stored baseline: the current server is treated as the baseline (nothing extra yet).
        if ($order->server) {
            foreach ($fields as $field => $_) {
                $fields[$field] = (int) ($order->server->{$field} ?? 0);
            }
        }

        return $fields;
    }

    /**
     * What a client may set for each resource on an order, in whole units: the lowest and highest they may pick, what they
     * have now, the unit price, and the point from which it is billed ("billedFrom": the offer for a bought server, zero
     * for a custom one, where everything is paid for).
     *
     * @return array<string, array{min: int, max: int, current: int, price: int, billedFrom: int}>
     */
    public function limits(ShopOrder $order): array
    {
        $custom = $this->isCustom($order);
        $base = $this->toUnits($this->baseline($order));
        $current = $order->server ? $this->toUnits($order->server->only(array_column(ShopSettings::RESOURCES, 'field'))) : $base;

        $out = [];
        foreach ($this->prices() as $key => $price) {
            $billedFrom = $base[$key];
            $min = $custom ? $this->settings->customMin($key) : $billedFrom;
            $max = $custom ? $this->settings->customMax($key) : $billedFrom + $this->settings->resourceMax($key);
            $out[$key] = [
                'min' => $min,
                'max' => max($min, $max),
                'current' => min(max($min, $current[$key] ?? $min), max($min, $max)),
                'price' => $price,
                'billedFrom' => $billedFrom,
            ];
        }

        return $out;
    }

    /**
     * What the client sees to build a custom server: whether it is on, the eggs and locations they may pick, and each
     * resource with its range and price.
     *
     * @return array{enabled: bool, eggs: array<int, array{id: int, name: string}>, locations: array<int, array{id: int, name: string}>, items: array<int, array{key: string, label: string, unit: int, min: int, max: int, default: int, price: int}>, maxPerUser: int, owned: int}
     */
    public function customConfig(User $user): array
    {
        if (!$this->settings->customEnabled()) {
            return ['enabled' => false, 'eggs' => [], 'locations' => [], 'items' => [], 'maxPerUser' => 0, 'owned' => 0];
        }

        $eggIds = $this->settings->customEggIds();
        $eggs = \Pterodactyl\Models\Egg::query()->whereIn('id', $eggIds ?: [0])->with('nest:id,name')->get(['id', 'nest_id', 'name'])
            ->map(fn ($egg) => ['id' => $egg->id, 'name' => ($egg->nest?->name ? $egg->nest->name . ' · ' : '') . $egg->name])->values()->all();

        $locationIds = $this->settings->customLocationIds();
        $locations = $locationIds
            ? \Pterodactyl\Models\Location::query()->whereIn('id', $locationIds)->get(['id', 'short'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->short])->values()->all()
            : [];

        $items = [];
        foreach ($this->prices() as $key => $price) {
            $min = $this->settings->customMin($key);
            $max = $this->settings->customMax($key);
            // Only offer a component that the administration actually opened up (a ceiling above the floor).
            if ($max <= 0 || $max <= $min) {
                continue;
            }
            $items[] = [
                'key' => $key,
                'label' => ShopSettings::RESOURCES[$key]['label'],
                'unit' => ShopSettings::RESOURCES[$key]['unit'],
                'min' => $min,
                'max' => $max,
                'default' => $min,
                'price' => $price,
            ];
        }

        return [
            'enabled' => !empty($eggs) && !empty($items),
            'eggs' => $eggs,
            'locations' => $locations,
            'items' => $items,
            'maxPerUser' => $this->settings->customMaxPerUser(),
            'owned' => $this->countCustom($user),
        ];
    }

    public function countCustom(User $user): int
    {
        return ShopOrder::query()->where('user_id', $user->id)->whereNull('offer_id')
            ->where('status', ShopOrder::ACTIVE)->count();
    }

    /**
     * The monthly cost, in cents, of the resources of an order above its baseline.
     *
     * @param array<string, int>|null $units the chosen units per resource; the server's own when left out
     */
    public function monthlyCost(ShopOrder $order, ?array $units = null): int
    {
        $base = $this->toUnits($this->baseline($order));
        if ($units === null) {
            $units = $order->server ? $this->toUnits($order->server->only(array_column(ShopSettings::RESOURCES, 'field'))) : $base;
        }

        $total = 0;
        foreach ($this->prices() as $key => $price) {
            $extra = max(0, ($units[$key] ?? 0) - $base[$key]);
            $total += $extra * $price;
        }

        return $total;
    }

    /**
     * Applies the resources a client chose to their server, and records the part of the month left to pay for the change.
     *
     * @param array<string, int> $chosen the wanted units per resource (only the ones for sale are read)
     *
     * @throws DisplayException
     */
    public function adjust(User $user, ShopOrder $order, array $chosen, ?CarbonInterface $now = null): ShopOrder
    {
        if (!$this->settings->resourceBillingEnabled()) {
            throw new DisplayException('Changing the resources is not available.');
        }
        if ($order->user_id !== $user->id) {
            throw new DisplayException('This server is not yours.');
        }
        if ($order->status !== ShopOrder::ACTIVE || !$order->server_id) {
            throw new DisplayException('The resources of this server cannot be changed right now.');
        }
        $server = Server::query()->without('allocation')->find($order->server_id);
        if (!$server) {
            throw new DisplayException('The server was not found.');
        }

        $limits = $this->limits($order);
        $base = $this->baseline($order);
        // Start from what the server has now, then set the resources that are for sale to what the client chose.
        $fields = $server->only(['memory', 'disk', 'cpu', 'database_limit', 'backup_limit', 'allocation_limit']);
        $units = [];
        foreach ($limits as $key => $range) {
            $want = (int) ($chosen[$key] ?? $range['current']);
            if ($want < $range['min'] || $want > $range['max']) {
                throw new DisplayException('The chosen amount of ' . $key . ' is out of the allowed range.');
            }
            $units[$key] = $want;
            $fields[ShopSettings::RESOURCES[$key]['field']] = $want * ShopSettings::RESOURCES[$key]['unit'];
        }

        $oldMonthly = (int) $order->resource_cents;
        $newMonthly = $this->monthlyCost($order, $units);
        if ($newMonthly === $oldMonthly && $fields == $server->only(array_keys($fields))) {
            return $order; // nothing changed
        }

        try {
            $this->builder->handle($server, $fields);
        } catch (\Throwable $exception) {
            report($exception);
            throw new DisplayException('The server could not be changed. Try again later.');
        }

        $now = $now ? Carbon::instance($now->toDateTime()) : Carbon::now();
        $amount = $this->prorate($newMonthly - $oldMonthly, $now);

        DB::transaction(function () use ($order, $newMonthly, $base, $user, $amount, $now) {
            // Keep the baseline the first time the order is touched, so a later change of the offer does not move it.
            $order->base_resources = $order->base_resources ?: $base;
            $order->resource_cents = $newMonthly;
            $order->save();

            if ($amount !== 0) {
                ShopResourceChange::query()->create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'description' => 'Resource change on ' . $order->offer_name . ' (' . $now->format('d/m') . ')',
                    'amount_cents' => $amount,
                ]);
            }
        });

        return $order->refresh();
    }

    /**
     * Builds a server the client configured themselves: an egg, a location and the resources they chose, at the per-unit
     * prices. The rest of the current month is taken from the credit now, then it is billed every month like any other
     * resources. The server is billed in full (there is no free floor).
     *
     * @param array<string, int> $chosen the wanted units per resource
     *
     * @throws DisplayException
     */
    public function createCustom(User $user, int $eggId, ?int $locationId, array $chosen, string $name, ?CarbonInterface $now = null): ShopOrder
    {
        if (!$this->settings->customEnabled()) {
            throw new DisplayException('Building a server is not available.');
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new DisplayException('Give a name to your server (80 characters at most).');
        }
        if (!in_array($eggId, $this->settings->customEggIds(), true)) {
            throw new DisplayException('This game is not available.');
        }

        $allowed = $this->settings->customLocationIds();
        if ($allowed) {
            if ($locationId === null || !in_array($locationId, $allowed, true)) {
                throw new DisplayException('Choose a location.');
            }
            $locations = [$locationId];
        } else {
            $locations = \Pterodactyl\Models\Location::query()->pluck('id')->all();
        }

        $max = $this->settings->customMaxPerUser();
        if ($max > 0 && $this->countCustom($user) >= $max) {
            throw new DisplayException('You have reached the number of custom servers you may have (' . $max . ').');
        }

        // The resources, checked against the allowed range, turned into the server fields and the monthly price.
        $fields = ['memory' => 0, 'disk' => 0, 'cpu' => 0, 'database_limit' => 0, 'backup_limit' => 0, 'allocation_limit' => 0];
        $monthly = 0;
        foreach ($this->prices() as $key => $price) {
            $units = (int) ($chosen[$key] ?? $this->settings->customMin($key));
            if ($units < $this->settings->customMin($key) || $units > $this->settings->customMax($key)) {
                throw new DisplayException('The chosen amount of ' . $key . ' is out of the allowed range.');
            }
            $fields[ShopSettings::RESOURCES[$key]['field']] = $units * ShopSettings::RESOURCES[$key]['unit'];
            $monthly += $units * $price;
        }
        if ($monthly <= 0) {
            throw new DisplayException('Choose some resources for your server.');
        }

        $now = $now ? Carbon::instance($now->toDateTime()) : Carbon::now();
        $firstCharge = max(0, $this->prorate($monthly, $now));
        if ($this->shop->balance($user->id) < $firstCharge) {
            throw new DisplayException('Not enough credit: the rest of this month costs ' . $this->shop->format($firstCharge) . '.');
        }

        // Take the money and open the order first; if the server cannot be made, give the money back (like a purchase).
        $order = DB::transaction(function () use ($user, $name, $monthly, $firstCharge, $now) {
            $order = ShopOrder::query()->create([
                'user_id' => $user->id,
                'offer_id' => null,
                'offer_name' => $name,
                'status' => ShopOrder::PROVISIONING,
                'price_cents' => 0,
                'duration_days' => 30,
                'resource_cents' => $monthly,
                'base_resources' => ['memory' => 0, 'disk' => 0, 'cpu' => 0, 'database_limit' => 0, 'backup_limit' => 0, 'allocation_limit' => 0],
            ]);
            if ($firstCharge > 0) {
                $this->shop->move($user->id, 'custom', -$firstCharge, $name . ' (' . $now->format('d/m') . ' – end of month)', ['order_id' => $order->id]);
            }

            return $order;
        });

        try {
            $server = $this->provisioner->provisionCustom($user, $eggId, $fields, $name, $locations);
        } catch (\Throwable $exception) {
            report($exception);
            DB::transaction(function () use ($order, $firstCharge) {
                if ($firstCharge > 0) {
                    $this->shop->move($order->user_id, 'refund', $firstCharge, $order->offer_name . ' (not delivered)', ['order_id' => $order->id]);
                }
                $order->update(['status' => ShopOrder::FAILED]);
            });

            throw new DisplayException('The server could not be made, and you were not charged. Try again later or contact the support.');
        }

        $order->update(['server_id' => $server->id, 'status' => ShopOrder::ACTIVE]);

        return $order->refresh();
    }

    /**
     * The part of a monthly amount that is left to pay from a day until the end of its month.
     */
    public function prorate(int $monthlyDelta, CarbonInterface $day): int
    {
        if ($monthlyDelta === 0) {
            return 0;
        }
        $daysInMonth = (int) $day->daysInMonth;
        $remaining = $daysInMonth - (int) $day->day + 1; // today counts

        return (int) round($monthlyDelta * $remaining / $daysInMonth);
    }

    /**
     * Makes the invoices for a month: the recurring cost of every server's resources plus the mid-month changes that were
     * waiting. Paid from the credit straight away when there is enough. Safe to run more than once.
     *
     * @return int how many invoices were made
     */
    public function buildInvoices(?CarbonInterface $onDate = null): int
    {
        $onDate = $onDate ? Carbon::instance($onDate->toDateTime()) : Carbon::now();
        $period = $onDate->format('Y-m');
        $made = 0;

        // Everyone who has resources to pay for, or a change waiting to be billed.
        $userIds = ShopOrder::query()->where('status', ShopOrder::ACTIVE)->where('resource_cents', '>', 0)->distinct()->pluck('user_id')
            ->merge(ShopResourceChange::query()->whereNull('invoice_id')->distinct()->pluck('user_id'))
            ->unique();

        foreach ($userIds as $userId) {
            if (ShopInvoice::query()->where('user_id', $userId)->where('period', $period)->exists()) {
                continue;
            }
            $lines = [];
            foreach (ShopOrder::query()->where('user_id', $userId)->where('status', ShopOrder::ACTIVE)->where('resource_cents', '>', 0)->get() as $order) {
                $lines[] = ['order_id' => $order->id, 'label' => 'Resources — ' . $order->offer_name . ' (' . $period . ')', 'amount_cents' => (int) $order->resource_cents];
            }
            $changes = ShopResourceChange::query()->where('user_id', $userId)->whereNull('invoice_id')->get();
            foreach ($changes as $change) {
                $lines[] = ['order_id' => $change->order_id, 'label' => $change->description, 'amount_cents' => (int) $change->amount_cents];
            }
            if (empty($lines)) {
                continue;
            }
            $total = max(0, array_sum(array_column($lines, 'amount_cents')));

            $invoice = DB::transaction(function () use ($userId, $period, $lines, $total, $onDate, $changes) {
                $invoice = ShopInvoice::query()->create([
                    'user_id' => $userId,
                    'period' => $period,
                    'status' => ShopInvoice::OPEN,
                    'total_cents' => $total,
                    'due_at' => $onDate->copy()->addDays($this->settings->invoiceDueDays()),
                ]);
                foreach ($lines as $line) {
                    ShopInvoiceLine::query()->create($line + ['invoice_id' => $invoice->id]);
                }
                foreach ($changes as $change) {
                    $change->update(['invoice_id' => $invoice->id]);
                }

                return $invoice;
            });
            ++$made;

            // Pay it now if the credit covers it, so the client does not have to do anything.
            try {
                $user = User::query()->find($userId);
                if ($user && $this->shop->balance($userId) >= $total) {
                    $this->pay($user, $invoice);
                }
            } catch (\Throwable $exception) {
                Log::warning('An invoice could not be paid automatically.', ['invoice' => $invoice->id, 'error' => $exception->getMessage()]);
            }
        }

        return $made;
    }

    /**
     * Pays an invoice from the credit. Gives the servers back if they were suspended for an unpaid invoice.
     *
     * @throws DisplayException
     */
    public function pay(User $user, ShopInvoice $invoice): ShopInvoice
    {
        if ($invoice->user_id !== $user->id) {
            throw new DisplayException('This invoice is not yours.');
        }
        if ($invoice->status !== ShopInvoice::OPEN) {
            return $invoice;
        }
        $total = (int) $invoice->total_cents;
        if ($total > 0) {
            if ($this->shop->balance($user->id) < $total) {
                throw new DisplayException('Not enough credit to pay this invoice.');
            }
            $this->shop->move($user->id, 'invoice', -$total, 'Invoice ' . $invoice->period, ['invoice_id' => $invoice->id]);
        }
        $invoice->update(['status' => ShopInvoice::PAID, 'paid_at' => now()]);

        $this->liftSuspensionIfClear($user);

        return $invoice->refresh();
    }

    /**
     * Suspends the servers of clients who have an invoice past its due date. Nothing is deleted.
     *
     * @return int how many servers were suspended
     */
    public function suspendOverdue(?CarbonInterface $now = null): int
    {
        $now = $now ?: Carbon::now();
        $count = 0;
        $userIds = ShopInvoice::query()->where('status', ShopInvoice::OPEN)->whereNotNull('due_at')
            ->where('due_at', '<', $now)->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            foreach (ShopOrder::query()->where('user_id', $userId)->where('status', ShopOrder::ACTIVE)->whereNotNull('server_id')->get() as $order) {
                try {
                    $server = Server::query()->without('allocation')->find($order->server_id);
                    if ($server && !$server->isSuspended()) {
                        $this->suspensions->toggle($server, SuspensionService::ACTION_SUSPEND, ['reason' => self::UNPAID_REASON]);
                        ++$count;
                    }
                } catch (\Throwable $exception) {
                    Log::warning('A server could not be suspended for an unpaid invoice.', ['order' => $order->id, 'error' => $exception->getMessage()]);
                }
            }
        }

        return $count;
    }

    /**
     * Gives back the servers of a client suspended for an unpaid invoice, once no invoice is overdue any more.
     */
    private function liftSuspensionIfClear(User $user): void
    {
        $stillOverdue = ShopInvoice::query()->where('user_id', $user->id)->where('status', ShopInvoice::OPEN)
            ->whereNotNull('due_at')->where('due_at', '<', now())->exists();
        if ($stillOverdue) {
            return;
        }

        foreach (ShopOrder::query()->where('user_id', $user->id)->where('status', ShopOrder::ACTIVE)->whereNotNull('server_id')->get() as $order) {
            try {
                $server = Server::query()->without('allocation')->find($order->server_id);
                if (!$server || !$server->isSuspended()) {
                    continue;
                }
                $reason = optional(\Pterodactyl\Models\ServerSuspension::query()->where('server_id', $server->id)->first())->reason;
                if ($reason === self::UNPAID_REASON) {
                    $this->suspensions->toggle($server, SuspensionService::ACTION_UNSUSPEND);
                }
            } catch (\Throwable $exception) {
                Log::warning('A server could not be given back after an invoice was paid.', ['order' => $order->id, 'error' => $exception->getMessage()]);
            }
        }
    }
}
