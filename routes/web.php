<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\CountryController as AdminCountryController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Admin\ServiceCountryController as AdminServiceCountryController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\ReconciliationController as AdminReconciliationController;
use App\Http\Controllers\Admin\ReferralController as AdminReferralController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Public demo entry — signs the visitor into a seeded demo customer
// account. Only active when config('app.demo_enabled') is true.
Route::get('/demo', [DemoController::class, 'enter'])->name('demo.enter');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post}', [BlogController::class, 'show'])->name('blog.show');

// Public legal / info pages
Route::prefix('pages')->name('pages.')->group(function () {
    Route::get('/terms', [PageController::class, 'terms'])->name('terms');
    Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
    Route::get('/refund-policy', [PageController::class, 'refund'])->name('refund');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
});

// Payment provider webhooks (server-to-server, CSRF-exempt)
Route::post('/webhooks/payments/{provider}', [WalletController::class, 'paymentWebhook'])
    ->name('webhooks.payments')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

Route::middleware(['demo', 'auth', 'verified', 'throttle:120,1'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Orders
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/create', [OrderController::class, 'create'])->name('create');
        Route::get('/countries', [OrderController::class, 'serviceCountries'])->name('countries');
        Route::get('/quote', [OrderController::class, 'quote'])->name('quote');
        Route::post('/', [OrderController::class, 'store'])->name('store')->middleware('throttle:orders');
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');
        Route::get('/{order}/sms-feed', [OrderController::class, 'smsFeed'])->name('sms-feed');
        Route::post('/{order}/refresh-sms', [OrderController::class, 'refreshSms'])->name('refresh-sms');
        Route::delete('/{order}', [OrderController::class, 'cancel'])->name('cancel');
    });

    // Wallet
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [WalletController::class, 'index'])->name('index');
        Route::get('/deposit', [WalletController::class, 'deposit'])->name('deposit');
        Route::post('/deposit', [WalletController::class, 'processDeposit'])->name('process-deposit');
        Route::get('/deposit/return', [WalletController::class, 'depositReturn'])->name('deposit-return');
        Route::get('/payments/{payment}/status', [WalletController::class, 'paymentStatus'])->name('payment-status');
    });

    // Browser push subscriptions (Web Push / VAPID)
    Route::post('/push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    // Notification dropdown feed
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->name('read');
    });

    // Support
    Route::prefix('support')->name('support.')->group(function () {
        Route::get('/', [SupportController::class, 'index'])->name('index');
        Route::get('/create', [SupportController::class, 'create'])->name('create');
        Route::post('/', [SupportController::class, 'store'])->name('store');
        Route::get('/{ticket}', [SupportController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply', [SupportController::class, 'reply'])->name('reply');
        Route::post('/{ticket}/close', [SupportController::class, 'close'])->name('close');
    });

    // Referral
    Route::prefix('referral')->name('referral.')->group(function () {
        Route::get('/', [ReferralController::class, 'index'])->name('index');
        Route::post('/apply', [ReferralController::class, 'store'])->name('apply');
    });
});

// Admin authentication — separate portal, separate session guard
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'create'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'store'])->name('login.store');
});

// Admin Routes — every route behind the admin guard + role check
Route::middleware(['admin', 'throttle:120,1'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Analytics — sales chart feed + business breakdowns
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/sales-data', [AdminAnalyticsController::class, 'salesData'])->name('analytics.sales-data');

    // Users
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::get('/{user}', [AdminUserController::class, 'show'])->name('show');
        Route::put('/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{user}/wallet-adjust', [AdminUserController::class, 'adjustWallet'])->name('wallet-adjust');
        Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
    });

    // Orders
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [AdminOrderController::class, 'index'])->name('index');
        Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show');
        Route::put('/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('update-status');
    });

    // Countries
    Route::prefix('countries')->name('countries.')->group(function () {
        Route::get('/', [AdminCountryController::class, 'index'])->name('index');
        Route::get('/create', [AdminCountryController::class, 'create'])->name('create');
        Route::post('/', [AdminCountryController::class, 'store'])->name('store');
        Route::get('/{country}', [AdminCountryController::class, 'show'])->name('show');
        Route::get('/{country}/edit', [AdminCountryController::class, 'edit'])->name('edit');
        Route::put('/{country}', [AdminCountryController::class, 'update'])->name('update');
        Route::delete('/{country}', [AdminCountryController::class, 'destroy'])->name('destroy');
        Route::put('/{country}/toggle-status', [AdminCountryController::class, 'toggleStatus'])->name('toggle-status');
        Route::put('/{country}/toggle-popular', [AdminCountryController::class, 'togglePopular'])->name('toggle-popular');
    });

    // Services
    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/', [AdminServiceController::class, 'index'])->name('index');
        Route::get('/create', [AdminServiceController::class, 'create'])->name('create');
        Route::post('/', [AdminServiceController::class, 'store'])->name('store');
        Route::get('/{service}', [AdminServiceController::class, 'show'])->name('show');
        Route::get('/{service}/edit', [AdminServiceController::class, 'edit'])->name('edit');
        Route::put('/{service}', [AdminServiceController::class, 'update'])->name('update');
        Route::delete('/{service}', [AdminServiceController::class, 'destroy'])->name('destroy');
        Route::put('/{service}/toggle-status', [AdminServiceController::class, 'toggleStatus'])->name('toggle-status');
        Route::put('/{service}/toggle-customer', [AdminServiceController::class, 'toggleCustomer'])->name('toggle-customer');
    });

    // Providers
    Route::prefix('providers')->name('providers.')->group(function () {
        Route::get('/', [AdminProviderController::class, 'index'])->name('index');
        Route::get('/{provider}/edit', [AdminProviderController::class, 'edit'])->name('edit');
        Route::put('/{provider}', [AdminProviderController::class, 'update'])->name('update');
        Route::put('/{provider}/toggle', [AdminProviderController::class, 'toggle'])->name('toggle');
        Route::post('/{provider}/services', [AdminProviderController::class, 'storeServiceMapping'])->name('services.store');
        Route::put('/{provider}/services/{mapping}/toggle', [AdminProviderController::class, 'toggleServiceMapping'])->name('services.toggle');
        Route::delete('/{provider}/services/{mapping}', [AdminProviderController::class, 'destroyServiceMapping'])->name('services.destroy');
        Route::post('/{provider}/countries', [AdminProviderController::class, 'storeCountryMapping'])->name('countries.store');
        Route::put('/{provider}/countries/{mapping}/toggle', [AdminProviderController::class, 'toggleCountryMapping'])->name('countries.toggle');
        Route::delete('/{provider}/countries/{mapping}', [AdminProviderController::class, 'destroyCountryMapping'])->name('countries.destroy');
    });

    // Pricing
    Route::get('/service-countries', [AdminServiceCountryController::class, 'index'])->name('service-countries.index');
    Route::put('/service-countries/{service}/{country}', [AdminServiceCountryController::class, 'update'])->name('service-countries.update');

    Route::prefix('pricing')->name('pricing.')->group(function () {
        Route::get('/', [AdminPricingController::class, 'index'])->name('index');
        Route::put('/defaults', [AdminPricingController::class, 'updateDefaults'])->name('defaults');
        Route::put('/services/{service}', [AdminPricingController::class, 'updateService'])->name('services.update');
        Route::put('/countries/{country}', [AdminPricingController::class, 'updateCountry'])->name('countries.update');
    });

    // Payments
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [AdminPaymentController::class, 'index'])->name('index');
        Route::get('/{payment}', [AdminPaymentController::class, 'show'])->name('show');
    });

    // Refunds
    Route::get('/refunds', [AdminRefundController::class, 'index'])->name('refunds.index');

    // Wallet transactions
    Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');

    // Financial reconciliation — ledger integrity + provider costs
    Route::get('/reconciliation', [AdminReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::post('/reconciliation/apply', [AdminReconciliationController::class, 'apply'])->name('reconciliation.apply');

    // Referrals
    Route::prefix('referrals')->name('referrals.')->group(function () {
        Route::get('/', [AdminReferralController::class, 'index'])->name('index');
        Route::put('/settings', [AdminReferralController::class, 'updateSettings'])->name('settings');
    });

    // Support tickets
    Route::prefix('support')->name('support.')->group(function () {
        Route::get('/', [AdminSupportController::class, 'index'])->name('index');
        Route::get('/{ticket}', [AdminSupportController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply', [AdminSupportController::class, 'reply'])->name('reply');
        Route::put('/{ticket}', [AdminSupportController::class, 'updateStatus'])->name('update');
    });

    // Notification management — send to one/selected/all + history log
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [AdminNotificationController::class, 'index'])->name('index');
        Route::get('/send', [AdminNotificationController::class, 'create'])->name('create');
        Route::post('/send', [AdminNotificationController::class, 'send'])->name('send');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [AdminSettingsController::class, 'index'])->name('index');
        Route::put('/', [AdminSettingsController::class, 'update'])->name('update');
        Route::post('/', [AdminSettingsController::class, 'create'])->name('create');
        Route::delete('/{setting}', [AdminSettingsController::class, 'destroy'])->name('destroy');
    });

    // Audit logs
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
});

require __DIR__.'/auth.php';
