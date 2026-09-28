<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use App\Services\OrderService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected WalletService $walletService;
    protected OrderService $orderService;
    protected SmsService $smsService;

    public function __construct(
        WalletService $walletService,
        OrderService $orderService,
        SmsService $smsService
    ) {
        $this->walletService = $walletService;
        $this->orderService = $orderService;
        $this->smsService = $smsService;
    }

    public function index()
    {
        $user = Auth::user();
        $wallet = $this->walletService->getWallet($user);
        $orderStats = $this->orderService->getOrderStats($user);
        $activeOrders = $this->orderService->getActiveOrders($user);
        $recentTransactions = $this->walletService->getTransactions($user, 10);
        $recentSms = collect();

        foreach ($activeOrders as $order) {
            $smsMessages = $this->smsService->getOrderSmsMessages($order);
            $recentSms = $recentSms->merge($smsMessages);
        }

        $recentSms = $recentSms->sortByDesc('received_at')->take(10);

        return view('dashboard.index', compact(
            'wallet',
            'orderStats',
            'activeOrders',
            'recentTransactions',
            'recentSms'
        ));
    }
}