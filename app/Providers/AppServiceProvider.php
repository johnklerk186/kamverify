<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Policies\OrderPolicy;
use App\Policies\SupportTicketPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);

        // The Vite "hot" file only makes sense for local browsing — its
        // [::1]:5173 asset URLs are unreachable from anywhere else. When
        // the app is reached through a public tunnel/proxy (any host that
        // isn't localhost), force the compiled production build so shared
        // preview links always render correctly. Local HMR is unaffected.
        if (!app()->runningInConsole()) {
            $host = request()->getHost();
            if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                \Illuminate\Support\Facades\Vite::useHotFile(storage_path('framework/.hot-disabled'));
            }
        }

        // Rate limiting for login attempts
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->ip() . '|' . $request->email);
        });

        // Rate limiting for order creation
        RateLimiter::for('orders', function ($request) {
            return Limit::perMinute(10)->by($request->user()?->id ?? $request->ip());
        });

        // Rate limiting for wallet deposits
        RateLimiter::for('wallet', function ($request) {
            return Limit::perMinute(5)->by($request->user()?->id ?? $request->ip());
        });

        // Rate limiting for API/provider calls
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by($request->user()?->id ?? $request->ip());
        });
    }
}
