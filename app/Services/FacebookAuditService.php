<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only forensic audit of Facebook/Meta orders — built to find
 * whether failed orders (no SMS delivered, plausibly the "mobile
 * carrier not supported" rejection customers report inside Facebook's
 * UI) cluster around particular number prefixes, ranges or countries.
 *
 * Honest limits, by design:
 *  - The Facebook-side rejection happens inside Meta's UI — we can
 *    never observe it server-side. Our proxy signal is "activation
 *    was sold, wound down, and no SMS was ever received".
 *  - HeroSMS returns no carrier/operator metadata on the endpoints we
 *    use, and provider_logs store no response bodies — the number
 *    prefix is the only carrier-identifying signal in our data.
 *  - Prefix buckets with small samples are reported but flagged —
 *    a pattern needs volume before it proves anything.
 */
class FacebookAuditService
{
    /** Minimum orders in a bucket before its failure rate is meaningful. */
    public const MIN_SAMPLE = 5;

    public function buildReport(?Carbon $since = null): array
    {
        $services = Service::where('slug', 'facebook')
            ->orWhere('name', 'like', '%facebook%')
            ->orWhere('name', 'like', '%meta%')
            ->pluck('id');

        $orders = Order::whereIn('service_id', $services)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->with('country', 'smsMessages')
            ->orderByDesc('created_at')
            ->get();

        $rows = $orders->map(fn ($o) => $this->rowFor($o));

        // ── Prefix analysis: digits-only first-4 bucket ───────────
        $prefixes = $rows->groupBy('prefix')->map(function ($bucket, $prefix) {
            $total = $bucket->count();
            $succeeded = $bucket->where('outcome', 'success')->count();
            $noSms = $bucket->where('outcome', 'failed_no_sms')->count();
            return [
                'prefix' => $prefix,
                'total' => $total,
                'succeeded' => $succeeded,
                'failed_no_sms' => $noSms,
                'success_rate' => $total ? round($succeeded / $total * 100, 1) : 0.0,
                'low_confidence' => $total < self::MIN_SAMPLE,
            ];
        })->sortByDesc('total')->values();

        // ── Country analysis ──────────────────────────────────────
        $countries = $rows->groupBy('country')->map(function ($bucket, $country) {
            $total = $bucket->count();
            $succeeded = $bucket->where('outcome', 'success')->count();
            return [
                'country' => $country,
                'total' => $total,
                'succeeded' => $succeeded,
                'failed_no_sms' => $bucket->where('outcome', 'failed_no_sms')->count(),
                'success_rate' => $total ? round($succeeded / $total * 100, 1) : 0.0,
            ];
        })->sortByDesc('total')->values();

        // ── Financial roll-up ─────────────────────────────────────
        $recovered = $rows->whereIn('provider_refund_status', ['released', 'provider_resolved']);
        $consumed = $rows->where('provider_refund_status', 'consumed');
        $unverified = $rows->where('provider_refund_status', null)
            ->whereIn('outcome', ['failed_no_sms', 'cancelled_after_sms']);

        return [
            'orders' => $rows,
            'prefixes' => $prefixes,
            'countries' => $countries,
            'totals' => [
                'orders' => $rows->count(),
                'completed' => $rows->where('outcome', 'success')->count(),
                'success_rate' => $rows->count()
                    ? round($rows->where('outcome', 'success')->count() / $rows->count() * 100, 1) : 0.0,
                'failed_no_sms' => $rows->where('outcome', 'failed_no_sms')->count(),
                'active' => $rows->where('outcome', 'active')->count(),
                'refunds_xaf' => round((float) $rows->sum('refund_amount'), 2),
                'provider_cost_usd' => round((float) $rows->sum('purchase_price'), 2),
                'provider_cost_xaf' => usdToXaf((float) $rows->sum('purchase_price')),
                'cost_recovered_usd' => round((float) $recovered->sum('purchase_price'), 2),
                'cost_consumed_usd' => round((float) $consumed->sum('purchase_price'), 2),
                'cost_unverified_usd' => round((float) $unverified->sum('purchase_price'), 2),
                // Out-of-pocket hit: provider cost that definitely did
                // NOT come back. 'unverified' is reported separately —
                // we cannot claim it recovered or lost without evidence.
                'kamverify_loss_usd' => round((float) $consumed->sum('purchase_price'), 2),
            ],
        ];
    }

    protected function rowFor(Order $o): array
    {
        $smsReceived = $o->smsMessages->isNotEmpty()
            || in_array($o->status, ['sms_received', 'completed'], true);

        $hasActivation = filled($o->provider_activation_id);
        $outcome = match (true) {
            in_array($o->status, ['sms_received', 'completed'], true) => 'success',
            $o->isActive() => 'active',
            !$hasActivation => 'no_activation',
            $smsReceived => 'cancelled_after_sms',
            default => 'failed_no_sms',
        };

        $digits = preg_replace('/\D/', '', (string) $o->phone_number);

        return [
            'order' => $o,
            'country' => $o->country->name ?? '?',
            'masked_phone' => $this->maskPhone($o->phone_number),
            'prefix' => $digits !== '' ? substr($digits, 0, 4) : 'unknown',
            'outcome' => $outcome,
            'sms_received' => $smsReceived,
            'status' => $o->status,
            'activation_id' => $o->provider_activation_id,
            'purchase_price' => (float) $o->purchase_price,
            'selling_price' => (float) $o->selling_price,
            'refund_amount' => (float) $o->refund_amount,
            'provider_refund_status' => $o->provider_refund_status,
            'cancellation_reason' => $o->cancellation_reason,
            'created_at' => $o->created_at,
        ];
    }

    /** Show enough digits to identify the range, mask the rest. */
    protected function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) <= 6) {
            return $digits !== '' ? substr($digits, 0, 4) . '•••' : '—';
        }
        return substr($digits, 0, 5) . '•••••' . substr($digits, -2);
    }
}
