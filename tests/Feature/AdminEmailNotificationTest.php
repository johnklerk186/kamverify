<?php

namespace Tests\Feature;

use App\Mail\AdminActivityMail;
use App\Models\Order;
use App\Services\AdminMailer;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

class AdminEmailNotificationTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        config(['kamverify.admin_notification_email' => 'admin@kamverify.test']);
    }

    protected function mailsMatching(string $subjectPart)
    {
        return Mail::queued(AdminActivityMail::class)
            ->filter(fn ($m) => str_contains($m->emailSubject, $subjectPart));
    }

    // ---------- Registration ----------

    public function test_new_registration_emails_admin(): void
    {
        $this->post('/register', [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        Mail::assertQueued(AdminActivityMail::class, fn ($m) =>
            str_contains($m->emailSubject, 'New Customer Registration'));
    }

    // ---------- Deposits ----------

    public function test_successful_deposit_emails_admin(): void
    {
        $user = $this->customer();
        $ps = app(PaymentService::class);

        $payment = $ps->createPayment($user, 5000, 'mock');
        $ps->processSuccessfulPayment($payment);

        Mail::assertQueued(AdminActivityMail::class, fn ($m) =>
            $m->emailSubject === 'KamVerify — New Deposit Received'
            && str_contains($m->fields['Amount'] ?? '', '5,000'));
    }

    public function test_pending_payment_sends_no_admin_email(): void
    {
        $user = $this->customer();
        app(PaymentService::class)->createPayment($user, 5000, 'mock');

        Mail::assertNothingQueued();
    }

    public function test_failed_payment_sends_failure_not_success_email(): void
    {
        $user = $this->customer();
        $ps = app(PaymentService::class);

        $payment = $ps->createPayment($user, 5000, 'mock');
        $ps->processFailedPayment($payment, 'Insufficient funds');

        Mail::assertQueued(AdminActivityMail::class, fn ($m) =>
            str_contains($m->emailSubject, 'Deposit Failed'));

        $this->assertSame(0, $this->mailsMatching('Deposit Received')->count());
    }

    public function test_duplicate_deposit_callbacks_send_one_email(): void
    {
        $user = $this->customer();
        $ps = app(PaymentService::class);

        $payment = $ps->createPayment($user, 5000, 'mock');

        // Repeated webhook/status verification must not spam the admin.
        $ps->processSuccessfulPayment($payment);
        $ps->processSuccessfulPayment($payment->fresh());
        $ps->verifyAndApplyStatus($payment->fresh());

        $this->assertSame(1, $this->mailsMatching('Deposit Received')->count());
    }

    // ---------- Orders ----------

    public function test_number_purchase_emails_admin(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();

        app(OrderService::class)->createOrder($user, $country, $service, $provider, 0.50);

        Mail::assertQueued(AdminActivityMail::class, fn ($m) =>
            str_contains($m->emailSubject, 'New Number Purchase'));
    }

    public function test_number_assigned_emails_admin(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = app(OrderService::class)->createOrder($user, $country, $service, $provider, 0.50);

        app(OrderService::class)->assignNumber($order, '670000000', 'act-123');

        Mail::assertQueued(AdminActivityMail::class, fn ($m) =>
            str_contains($m->emailSubject, 'Number Assigned'));
    }

    public function test_completed_order_emails_admin_once(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = app(OrderService::class)->createOrder($user, $country, $service, $provider, 0.50);
        app(OrderService::class)->assignNumber($order, '670000000', 'act-123');

        app(SmsService::class)->receiveSms($order->fresh(), 'WhatsApp', 'Your code is 123456', 'msg-1');
        // Provider redelivery must not re-trigger the admin email.
        app(SmsService::class)->receiveSms($order->fresh(), 'WhatsApp', 'Your code is 123456', 'msg-1');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, $this->mailsMatching('Order Completed')->count());
    }

    public function test_cancelled_order_emails_admin(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = app(OrderService::class)->createOrder($user, $country, $service, $provider, 0.50);

        app(OrderService::class)->cancelOrder($order->fresh(), true, force: true);

        Mail::assertQueued(AdminActivityMail::class, fn ($m) =>
            str_contains($m->emailSubject, 'Order Cancelled'));
    }

    public function test_expired_order_emails_admin(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = app(OrderService::class)->createOrder($user, $country, $service, $provider, 0.50);
        app(OrderService::class)->assignNumber($order->fresh(), '670000000', 'act-123');
        $order->fresh()->update(['expires_at' => now()->subMinute()]);

        app(OrderService::class)->expireOrder($order->fresh());

        Mail::assertQueued(AdminActivityMail::class, fn ($m) =>
            str_contains($m->emailSubject, 'Order Expired'));
    }

    public function test_refund_emails_admin(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = app(OrderService::class)->createOrder($user, $country, $service, $provider, 0.50);

        $os = app(OrderService::class);
        $os->refundOrder($order->fresh());
        $os->refundOrder($order->fresh()); // idempotent — no second email

        $this->assertSame(1, $this->mailsMatching('Refund Processed')->count());
    }

    // ---------- Support ----------

    public function test_new_support_ticket_emails_admin(): void
    {
        $this->actingAs($this->customer())->post('/support', [
            'category' => 'payment',
            'subject' => 'Deposit not credited',
            'message' => 'I paid 30 minutes ago and my wallet is still empty.',
        ])->assertRedirect();

        Mail::assertQueued(AdminActivityMail::class, fn ($m) =>
            str_contains($m->emailSubject, 'New Support Ticket'));
    }

    public function test_customer_ticket_reply_emails_admin(): void
    {
        $user = $this->customer();
        $ticket = \App\Models\SupportTicket::create([
            'user_id' => $user->id,
            'ticket_id' => 'TKT-TEST1',
            'category' => 'general',
            'subject' => 'Help',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $this->actingAs($user)->post("/support/{$ticket->id}/reply", [
            'message' => 'Any update on this?',
        ])->assertRedirect();

        Mail::assertQueued(AdminActivityMail::class, fn ($m) =>
            str_contains($m->emailSubject, 'Support Ticket Reply'));
    }

    // ---------- Safety ----------

    public function test_no_admin_email_when_address_unset(): void
    {
        config(['kamverify.admin_notification_email' => null]);

        $user = $this->customer();
        $ps = app(PaymentService::class);
        $ps->processSuccessfulPayment($ps->createPayment($user, 5000, 'mock'));

        Mail::assertNothingQueued();
        $this->assertSame('completed', $user->payments()->latest()->first()->status);
    }

    public function test_mail_failure_does_not_break_deposit(): void
    {
        // Simulate a dead SMTP relay — the wallet must still credit.
        $pending = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pending->shouldReceive('queue')->andThrow(new \RuntimeException('SMTP unreachable'));
        Mail::shouldReceive('to')->andReturn($pending);

        $user = $this->customer();
        $ps = app(PaymentService::class);

        $payment = $ps->createPayment($user, 5000, 'mock');
        $ps->processSuccessfulPayment($payment);

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(55000.0, (float) $user->wallet->fresh()->balance);
    }

    public function test_mail_failure_does_not_break_purchase(): void
    {
        $pending = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pending->shouldReceive('queue')->andThrow(new \RuntimeException('SMTP unreachable'));
        Mail::shouldReceive('to')->andReturn($pending);

        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();

        $order = app(OrderService::class)->createOrder($user, $country, $service, $provider, 0.50);

        $this->assertSame('pending', $order->status);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_mailer_send_is_silent_when_unconfigured(): void
    {
        config(['kamverify.admin_notification_email' => null]);

        app(AdminMailer::class)->send('test.key:1', 'Subj', 'Head', ['k' => 'v']);

        Mail::assertNothingQueued();
        $this->assertDatabaseMissing('admin_mail_log', ['dedupe_key' => 'test.key:1']);
    }

    public function test_dedupe_key_blocks_resend_directly(): void
    {
        $mailer = app(AdminMailer::class);

        $mailer->send('dup.key:9', 'Subj', 'Head', ['k' => 'v']);
        $mailer->send('dup.key:9', 'Subj', 'Head', ['k' => 'v']);

        $this->assertSame(1, Mail::queued(AdminActivityMail::class)->count());
        $this->assertDatabaseCount('admin_mail_log', 1);
    }
}
