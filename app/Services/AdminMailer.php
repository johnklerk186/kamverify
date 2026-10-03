<?php

namespace App\Services;

use App\Mail\AdminActivityMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Admin activity emails (ADMIN_NOTIFICATION_EMAIL).
 *
 * Guarantees:
 *  - Idempotent: a unique dedupe_key per (event, entity) means repeated
 *    webhooks, status polls and queue retries can never double-send.
 *  - Failure-isolated: every error is caught and logged — mail delivery
 *    can never break a deposit, order, refund or ticket flow.
 *  - Silent when ADMIN_NOTIFICATION_EMAIL is not configured.
 */
class AdminMailer
{
    /**
     * @param string      $dedupeKey Unique per event instance, e.g. "order.completed:42"
     * @param string      $subject   Email subject line
     * @param string      $heading   Heading shown inside the email body
     * @param array       $fields    Label => plain-text value pairs (never secrets)
     * @param string|null $note      Optional lead-in paragraph
     */
    public function send(string $dedupeKey, string $subject, string $heading, array $fields = [], ?string $note = null): void
    {
        try {
            $to = config('kamverify.admin_notification_email');

            if (!$to) {
                return;
            }

            $inserted = DB::table('admin_mail_log')->insertOrIgnore([
                'dedupe_key' => $dedupeKey,
                'subject' => $subject,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return; // already sent for this event instance
            }

            // afterCommit: when called inside a DB transaction (order
            // create/refund/status transitions), the queued mail must
            // not fire before the state it describes is durable.
            Mail::to($to)->queue(new AdminActivityMail($subject, $heading, $fields, $note))
                ->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Admin notification email failed', [
                'dedupe_key' => $dedupeKey,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
