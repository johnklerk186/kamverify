<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use App\Services\PaymentService;
use App\Http\Requests\DepositRequest;
use App\Models\Payment;
use App\Models\Setting;
use App\Notifications\KamVerifyNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class WalletController extends Controller
{
    protected WalletService $walletService;
    protected PaymentService $paymentService;

    public function __construct(WalletService $walletService, PaymentService $paymentService)
    {
        $this->walletService = $walletService;
        $this->paymentService = $paymentService;
    }

    /**
     * Whether the live Fapshi integration is configured. When false the
     * application runs deposits through the sandbox (mock) provider.
     */
    protected function livePaymentsEnabled(): bool
    {
        return !filter_var(env('FAPSHI_USE_MOCK', true), FILTER_VALIDATE_BOOL)
            && !empty(env('FAPSHI_API_KEY'))
            && !empty(env('FAPSHI_API_USER'));
    }

    public function index()
    {
        $user = Auth::user();
        $wallet = $this->walletService->getWallet($user);
        $transactions = $this->walletService->getTransactions($user, 50);
        $pendingPayments = Payment::where('user_id', $user->id)->where('status', 'pending')->latest()->get();
        $sandbox = !$this->livePaymentsEnabled();

        return view('wallet.index', compact('wallet', 'transactions', 'pendingPayments', 'sandbox'));
    }

    public function deposit()
    {
        $user = Auth::user();
        $wallet = $this->walletService->getWallet($user);
        $sandbox = !$this->livePaymentsEnabled();
        $minDeposit = (int) Setting::get('min_deposit_amount', 100);
        $maxDeposit = (int) Setting::get('max_deposit_amount', 1000000);

        return view('wallet.deposit', compact('wallet', 'sandbox', 'minDeposit', 'maxDeposit'));
    }

    public function processDeposit(DepositRequest $request)
    {
        $user = Auth::user();
        $amount = (int) $request->amount;
        $live = $this->livePaymentsEnabled();

        if (demoMode() && $live) {
            return back()->with('error', 'Deposits are disabled in the demo environment.');
        }

        $providerName = $live ? 'fapshi' : 'mock';

        try {
            $payment = $this->paymentService->createPayment($user, $amount, $providerName, [
                'payment_method' => 'mtn_momo',
                'medium' => 'mtn',
                'phone' => $request->phone,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'redirect_url' => route('wallet.deposit-return'),
            ]);
        } catch (\Exception $e) {
            $user->notify(new KamVerifyNotification(
                'deposit_failed',
                'Deposit Failed',
                'Your MTN Mobile Money payment of ' . xaf($amount) . ' could not be initiated.',
                route('wallet.deposit'),
                'Try Again',
                'fa-wallet'
            ));

            return back()->withInput()->with('error',
                'Unable to start the payment: ' . $e->getMessage());
        }

        if ($live) {
            // Direct Pay: Fapshi pushes the approval prompt to the
            // customer's phone — no redirect. A hosted-checkout link is
            // still honoured if the provider ever returns one. The wallet
            // credits only after backend verification (webhook / status
            // check), never on request success alone.
            $checkoutUrl = $payment->provider_response['redirect_url'] ?? null;

            if ($checkoutUrl) {
                return redirect()->away($checkoutUrl);
            }

            return redirect()->route('wallet.index')
                ->with('success', 'Payment request sent — approve the MTN MoMo prompt on your phone. Your wallet credits automatically once the payment confirms.');
        }

        // Sandbox mode: simulate an instant successful deposit.
        try {
            $this->paymentService->processSuccessfulPayment($payment);

            return redirect()->route('wallet.index')
                ->with('success', 'Sandbox deposit successful — ' . xaf($amount) . ' added to your wallet.');
        } catch (\Exception $e) {
            return back()->with('error', 'Deposit failed: ' . $e->getMessage());
        }
    }

    /**
     * Landing page after the customer returns from the Fapshi hosted
     * checkout. Re-verifies the latest pending payment server-side before
     * showing any outcome.
     */
    public function depositReturn()
    {
        $payment = Payment::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($payment) {
            $this->paymentService->verifyAndApplyStatus($payment);
            $status = $payment->refresh()->status;

            return redirect()->route('wallet.index')->with(match ($status) {
                'completed' => 'success',
                'failed', 'cancelled', 'expired' => 'error',
                default => 'warning',
            }, match ($status) {
                'completed' => 'Payment confirmed — ' . xaf($payment->amount) . ' added to your wallet.',
                'failed'    => 'The payment could not be completed. No funds were taken.',
                'cancelled' => 'The payment was cancelled.',
                'expired'   => 'The payment link expired. Start a new deposit to try again.',
                default     => 'Payment is still processing — your wallet will credit automatically once confirmed.',
            });
        }

        return redirect()->route('wallet.index');
    }

    /**
     * Customer-facing status check for a pending payment. Re-verifies
     * with the provider server-side — the frontend is never trusted.
     * Throttled to respect Fapshi's 6-requests/minute status limit.
     */
    public function paymentStatus(Payment $payment)
    {
        if ($payment->user_id !== Auth::id()) {
            abort(403);
        }

        if ($payment->status === 'pending'
            && Cache::add('pay-status:' . $payment->id, 1, 12)) {
            $this->paymentService->verifyAndApplyStatus($payment);
        }

        return response()->json([
            'status' => $payment->refresh()->status,
            'completed' => $payment->status === 'completed',
        ]);
    }

    /**
     * Provider webhook endpoint (server-to-server). The payload locates
     * the payment; state changes only happen after provider re-verification.
     */
    public function paymentWebhook(Request $request, string $provider)
    {
        $this->paymentService->handleWebhook($provider, $request->all());

        return response()->json(['received' => true]);
    }
}
