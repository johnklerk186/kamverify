<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Read-only analytics over real order/payment data. Every figure is
 * computed from the database — nothing is fabricated or hard-coded.
 *
 * "Sales" = orders the customer paid for that were not undone —
 * pending/processing/active/completed. Cancelled, expired, failed and
 * refunded orders are excluded because their money came back.
 */
class AnalyticsService
{
    /** Statuses that represent real (non-returned) sales. */
    public const SALE_STATUSES = [
        'pending', 'processing', 'number_assigned',
        'waiting_for_sms', 'sms_received', 'completed',
    ];

    /**
     * Provider cost was actually incurred when the activation reached
     * the provider's billable states (number assigned onward) or when a
     * wound-down order's cost was consumed rather than released.
     * pending/processing orders have no activation yet — no cost.
     */
    protected function costIncurredScope($query)
    {
        return $query->whereIn('status', ['number_assigned', 'waiting_for_sms', 'sms_received', 'completed'])
            ->orWhere('provider_refund_status', 'consumed');
    }

    /**
     * Resolve a range key (today|yesterday|last7|last30|this_month|
     * last_month|custom) to an inclusive [from, to] Carbon pair.
     */
    public function resolveRange(?string $key, ?string $from = null, ?string $to = null): array
    {
        $key = $key ?: 'last7';

        switch ($key) {
            case 'today':
                return ['today', now()->startOfDay(), now()->endOfDay()];
            case 'yesterday':
                return ['yesterday', now()->subDay()->startOfDay(), now()->subDay()->endOfDay()];
            case 'last30':
                return ['last30', now()->subDays(29)->startOfDay(), now()->endOfDay()];
            case 'this_month':
                return ['this_month', now()->startOfMonth(), now()->endOfDay()];
            case 'last_month':
                return ['last_month', now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()->endOfDay()];
            case 'custom':
                try {
                    $f = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(6)->startOfDay();
                    $t = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
                } catch (\Throwable $e) {
                    $f = now()->subDays(6)->startOfDay();
                    $t = now()->endOfDay();
                }
                if ($t->lt($f)) {
                    [$f, $t] = [$t->copy()->startOfDay(), $f->copy()->endOfDay()];
                }
                // Cap custom ranges at 24 months to keep charts sane
                if ($f->lt(now()->subMonths(24))) {
                    $f = now()->subMonths(24)->startOfDay();
                }
                return ['custom', $f, $t];
            case 'last7':
            default:
                return ['last7', now()->subDays(6)->startOfDay(), now()->endOfDay()];
        }
    }

    /**
     * Sales series for the chart. Buckets paid order revenue + order
     * count per day/week/month across the range.
     */
    public function salesSeries(Carbon $from, Carbon $to, string $granularity = 'daily'): array
    {
        $granularity = in_array($granularity, ['daily', 'weekly', 'monthly'], true) ? $granularity : 'daily';

        $rows = Order::whereIn('status', self::SALE_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('date(created_at) as day, sum(selling_price) as revenue, count(*) as orders')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Bucket map: key => ['label' => ..., 'revenue' => 0, 'orders' => 0]
        $buckets = $this->emptyBuckets($from, $to, $granularity);

        foreach ($rows as $row) {
            $key = $this->bucketKey(Carbon::parse($row->day), $granularity);
            if (isset($buckets[$key])) {
                $buckets[$key]['revenue'] += (float) $row->revenue;
                $buckets[$key]['orders'] += (int) $row->orders;
            }
        }

        return [
            'labels' => array_column(array_values($buckets), 'label'),
            'revenue' => array_map(fn ($b) => round($b['revenue'], 2), array_values($buckets)),
            'orders' => array_column(array_values($buckets), 'orders'),
            'totals' => [
                'revenue' => round((float) $rows->sum('revenue'), 2),
                'orders' => (int) $rows->sum('orders'),
            ],
        ];
    }

    /**
     * Full business breakdown for the range.
     */
    public function businessMetrics(Carbon $from, Carbon $to): array
    {
        $orders = Order::whereBetween('created_at', [$from, $to]);

        $byStatus = (clone $orders)->selectRaw('status, count(*) as n')
            ->groupBy('status')->pluck('n', 'status')->all();

        $paidOrders = (clone $orders)->whereIn('status', self::SALE_STATUSES);
        $grossSales = (float) (clone $paidOrders)->sum('selling_price');

        // Provider cost = activations HeroSMS actually charged for:
        // live/delivered orders, plus refunded orders whose activation
        // was consumed (OTP delivered) rather than released.
        $providerCost = (float) (clone $orders)
            ->where(fn ($q) => $this->costIncurredScope($q))
            ->sum('purchase_price');
        $grossProfit = (float) (clone $orders)
            ->where(fn ($q) => $this->costIncurredScope($q))
            ->sum('profit');
        $refunded = (float) (clone $orders)->whereIn('status', ['refunded', 'cancelled', 'expired'])
            ->sum('refund_amount');
        $deposits = (float) WalletTransaction::where('type', 'deposit')
            ->whereBetween('created_at', [$from, $to])->sum('amount');
        $paymentsReceived = (float) Payment::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])->sum('amount');

        return [
            'orders' => [
                'total'      => (clone $orders)->count(),
                'completed'  => (int) ($byStatus['completed'] ?? 0),
                'waiting'    => (int) (($byStatus['waiting_for_sms'] ?? 0) + ($byStatus['number_assigned'] ?? 0)),
                'processing' => (int) (($byStatus['pending'] ?? 0) + ($byStatus['processing'] ?? 0)),
                'cancelled'  => (int) ($byStatus['cancelled'] ?? 0),
                'expired'    => (int) ($byStatus['expired'] ?? 0),
                'failed'     => (int) ($byStatus['failed'] ?? 0),
                'refunded'   => (int) ($byStatus['refunded'] ?? 0),
            ],
            'revenue' => [
                'gross_sales' => round($grossSales, 2),
                'deposits'    => round($deposits, 2),
                'payments'    => round($paymentsReceived, 2),
                'refunds'     => round($refunded, 2),
                'net_sales'   => round($grossSales - $refunded, 2),
            ],
            'profit' => [
                // purchase_price is stored in USD (provider truth);
                // displayed in XAF alongside the customer-facing money.
                'provider_cost' => usdToXaf($providerCost),
                'customer_price' => round($grossSales, 2),
                'gross_profit'  => round($grossProfit, 2),
                'margin'        => $grossSales > 0 ? round(($grossProfit / $grossSales) * 100, 1) : 0.0,
            ],
            'customers' => $this->customerMetrics($from, $to),
            'top_services' => $this->topServices($from, $to),
            'top_countries' => $this->topCountries($from, $to),
        ];
    }

    /**
     * Headline cards for the admin dashboard — today vs all-time.
     */
    public function dashboardOverview(): array
    {
        $todayFrom = now()->startOfDay();
        $todayTo = now()->endOfDay();

        $todayOrders = Order::whereBetween('created_at', [$todayFrom, $todayTo]);
        $todaySales = (float) (clone $todayOrders)->whereIn('status', self::SALE_STATUSES)->sum('selling_price');
        $todayCost = (float) (clone $todayOrders)->whereIn('status', self::SALE_STATUSES)->sum('purchase_price');

        $allSales = (float) Order::whereIn('status', self::SALE_STATUSES)->sum('selling_price');
        $allCost = (float) Order::where(fn ($q) => $this->costIncurredScope($q))->sum('purchase_price');
        $allProfit = (float) Order::where(fn ($q) => $this->costIncurredScope($q))->sum('profit');

        return [
            'today_sales'    => round($todaySales, 2),
            'today_orders'   => (clone $todayOrders)->count(),
            'today_deposits' => round((float) WalletTransaction::where('type', 'deposit')
                ->whereBetween('created_at', [$todayFrom, $todayTo])->sum('amount'), 2),
            'today_customers' => User::where('role', 'customer')
                ->whereBetween('created_at', [$todayFrom, $todayTo])->count(),
            'today_refunds'  => round((float) WalletTransaction::where('type', 'refund')
                ->whereBetween('created_at', [$todayFrom, $todayTo])->sum('amount'), 2),

            'total_customers'  => User::where('role', 'customer')->count(),
            'suspended'        => User::where('role', 'customer')->where('is_active', false)->count(),
            'total_orders'     => Order::count(),
            'active_orders'    => Order::whereIn('status', Order::ACTIVE_STATUSES)->count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'cancelled_orders' => Order::whereIn('status', ['cancelled', 'refunded'])->count(),

            'total_sales'    => round($allSales, 2),
            'total_deposits' => round((float) WalletTransaction::where('type', 'deposit')->sum('amount'), 2),
            'total_refunds'  => round((float) WalletTransaction::where('type', 'refund')->sum('amount'), 2),
            'provider_cost'  => usdToXaf($allCost),
            'gross_profit'   => round($allProfit, 2),
            'margin'         => $allSales > 0 ? round(($allProfit / $allSales) * 100, 1) : 0.0,
            'rewards_paid'   => round((float) ReferralReward::where('status', 'approved')->sum('reward_amount'), 2),
        ];
    }

    protected function customerMetrics(Carbon $from, Carbon $to): array
    {
        $customers = User::where('role', 'customer');
        $newInRange = (clone $customers)->whereBetween('created_at', [$from, $to])->count();

        // Customers with >1 paid order ever = "returning"
        $returning = Order::whereIn('status', self::SALE_STATUSES)
            ->select('user_id')->groupBy('user_id')
            ->havingRaw('count(*) > 1')->pluck('user_id')->count();

        // Active = placed an order inside the range
        $activeInRange = Order::whereBetween('created_at', [$from, $to])
            ->distinct()->count('user_id');

        return [
            'total'     => (clone $customers)->count(),
            'new'       => $newInRange,
            'returning' => $returning,
            'active'    => $activeInRange,
            'suspended' => (clone $customers)->where('is_active', false)->count(),
        ];
    }

    protected function topServices(Carbon $from, Carbon $to): Collection
    {
        return Order::whereIn('orders.status', self::SALE_STATUSES)
            ->whereBetween('orders.created_at', [$from, $to])
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->selectRaw('services.name, services.icon, count(*) as orders, sum(orders.selling_price) as revenue, sum(orders.profit) as profit')
            ->groupBy('services.id', 'services.name', 'services.icon')
            ->orderByDesc('orders')
            ->limit(8)
            ->get();
    }

    protected function topCountries(Carbon $from, Carbon $to): Collection
    {
        $countries = Order::whereBetween('orders.created_at', [$from, $to])
            ->join('countries', 'orders.country_id', '=', 'countries.id')
            ->selectRaw('countries.id, countries.name, countries.code, count(*) as orders, sum(orders.selling_price) as revenue')
            ->groupBy('countries.id', 'countries.name', 'countries.code')
            ->orderByDesc('orders')
            ->limit(8)
            ->get();

        // Completion rate per country = completed / all orders in range
        $completed = Order::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('country_id, count(*) as n')
            ->groupBy('country_id')
            ->pluck('n', 'country_id');

        $totals = Order::whereBetween('created_at', [$from, $to])
            ->selectRaw('country_id, count(*) as n')
            ->groupBy('country_id')
            ->pluck('n', 'country_id');

        return $countries->map(function ($row) use ($completed, $totals) {
            $done = (int) ($completed[$row->id] ?? 0);
            $all = (int) ($totals[$row->id] ?? 0);
            $row->success_rate = $all > 0 ? round(($done / $all) * 100, 1) : 0.0;
            return $row;
        });
    }

    /**
     * Pre-fill every bucket in the range so the chart has a continuous
     * axis (zero-filled gaps) instead of only days with sales.
     */
    protected function emptyBuckets(Carbon $from, Carbon $to, string $granularity): array
    {
        $buckets = [];
        $period = CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->endOfDay());

        foreach ($period as $day) {
            $key = $this->bucketKey($day, $granularity);
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'label' => $this->bucketLabel($day, $granularity),
                    'revenue' => 0.0,
                    'orders' => 0,
                ];
            }
        }

        return $buckets;
    }

    protected function bucketKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'monthly' => $date->format('Y-m'),
            'weekly'  => $date->copy()->startOfWeek()->format('Y-m-d'),
            default   => $date->format('Y-m-d'),
        };
    }

    protected function bucketLabel(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'monthly' => $date->format('M Y'),
            'weekly'  => 'W/c ' . $date->copy()->startOfWeek()->format('M j'),
            default   => $date->format('M j'),
        };
    }
}
