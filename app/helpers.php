<?php

if (! function_exists('statusColor')) {
    function statusColor(string $status): string
    {
        $colors = [
            'pending' => 'bg-ink-100 text-ink-700',
            'processing' => 'bg-sky-100 text-sky-700',
            'number_assigned' => 'bg-brand-100 text-brand-700',
            'waiting_for_sms' => 'bg-amber-100 text-amber-700',
            'sms_received' => 'bg-emerald-100 text-emerald-700',
            'completed' => 'bg-emerald-100 text-emerald-700',
            'cancelled' => 'bg-red-100 text-red-700',
            'refunded' => 'bg-orange-100 text-orange-700',
            'expired' => 'bg-ink-100 text-ink-600',
            'failed' => 'bg-red-100 text-red-700',
            'open' => 'bg-emerald-100 text-emerald-700',
            'in_progress' => 'bg-sky-100 text-sky-700',
            'resolved' => 'bg-sky-100 text-sky-700',
            'closed' => 'bg-ink-100 text-ink-600',
            'success' => 'bg-emerald-100 text-emerald-700',
            'approved' => 'bg-emerald-100 text-emerald-700',
            'rejected' => 'bg-red-100 text-red-700',
            'active' => 'bg-emerald-100 text-emerald-700',
            'inactive' => 'bg-ink-100 text-ink-600',
            'suspended' => 'bg-red-100 text-red-700',
            'low' => 'bg-ink-100 text-ink-600',
            'medium' => 'bg-sky-100 text-sky-700',
            'normal' => 'bg-sky-100 text-sky-700',
            'high' => 'bg-amber-100 text-amber-700',
            'urgent' => 'bg-red-100 text-red-700',
        ];

        return $colors[$status] ?? 'bg-ink-100 text-ink-700';
    }
}

if (! function_exists('transactionTypeColor')) {
    function transactionTypeColor(string $type): string
    {
        $colors = [
            'deposit' => 'bg-emerald-100 text-emerald-700',
            'withdrawal' => 'bg-red-100 text-red-700',
            'purchase' => 'bg-sky-100 text-sky-700',
            'refund' => 'bg-brand-100 text-brand-700',
            'reward' => 'bg-violet-100 text-violet-700',
            'adjustment' => 'bg-ink-100 text-ink-600',
        ];

        return $colors[$type] ?? 'bg-ink-100 text-ink-700';
    }
}

if (! function_exists('xaf')) {
    /**
     * Format an amount in XAF — the only customer-facing currency.
     * XAF has no decimal subdivision in practice: integer values only.
     */
    function xaf($amount): string
    {
        return number_format((float) $amount, 0) . ' XAF';
    }
}

if (! function_exists('usdToXaf')) {
    /**
     * Convert a USD amount (provider cost) to integer XAF using the
     * admin-configured rate. All customer money math happens in XAF.
     */
    function usdToXaf(float $usd): int
    {
        return (int) round($usd * xafRate());
    }
}

if (! function_exists('xafRate')) {
    function xafRate(): float
    {
        return max(1, (float) \App\Models\Setting::get('usd_to_xaf_rate', 600));
    }
}

if (! function_exists('demoMode')) {
    /**
     * Whether the current request is inside the demo environment.
     * Checks the request attribute set by the DemoAuthenticate
     * middleware first (session-independent), then the session flag.
     */
    function demoMode(): bool
    {
        $request = request();

        if ($request->attributes->get('demo_mode')) {
            return true;
        }

        return $request->hasSession() && (bool) $request->session()->get('demo_mode');
    }
}

if (! function_exists('countryFlag')) {
    /**
     * Convert an ISO-3166 alpha-2 country code to a flag emoji.
     */
    function countryFlag(?string $code): string
    {
        if (!$code || strlen($code) !== 2) {
            return '🌐';
        }

        $code = strtoupper($code);
        $flag = '';
        foreach (str_split($code) as $char) {
            $flag .= mb_chr(127397 + ord($char));
        }

        return $flag;
    }
}

if (! function_exists('serviceIcon')) {
    /**
     * Map a service slug/icon name to a Font Awesome class + brand color.
     */
    function serviceIcon(?string $icon): array
    {
        $map = [
            'whatsapp'  => ['fab fa-whatsapp', 'text-emerald-500'],
            'facebook'  => ['fab fa-facebook', 'text-blue-600'],
            'telegram'  => ['fab fa-telegram', 'text-sky-500'],
            'tiktok'    => ['fab fa-tiktok', 'text-ink-900'],
            'google'    => ['fab fa-google', 'text-red-500'],
            'instagram' => ['fab fa-instagram', 'text-pink-500'],
            'twitter'   => ['fab fa-x-twitter', 'text-ink-900'],
            'x'         => ['fab fa-x-twitter', 'text-ink-900'],
            'amazon'    => ['fab fa-amazon', 'text-amber-500'],
            'discord'   => ['fab fa-discord', 'text-indigo-500'],
            'snapchat'  => ['fab fa-snapchat', 'text-yellow-400'],
            'linkedin'  => ['fab fa-linkedin', 'text-blue-700'],
            'microsoft' => ['fab fa-microsoft', 'text-sky-600'],
            'apple'     => ['fab fa-apple', 'text-ink-900'],
            'netflix'   => ['fas fa-play-circle', 'text-red-600'],
            'spotify'   => ['fab fa-spotify', 'text-emerald-500'],
            'paypal'    => ['fab fa-paypal', 'text-blue-600'],
            'uber'      => ['fab fa-uber', 'text-ink-900'],
        ];

        return $map[strtolower((string) $icon)] ?? ['fas fa-mobile-alt', 'text-brand-600'];
    }
}
