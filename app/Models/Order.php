<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'country_id',
        'service_id',
        'provider_id',
        'provider_activation_id',
        'phone_number',
        'purchase_price',
        'selling_price',
        'profit',
        'status',
        'expires_at',
        'cancelled_at',
        'cancel_requested_at',
        'completed_at',
        'refund_amount',
        'provider_response',
        'provider_refund_status',
        'cancellation_reason',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'profit' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'provider_response' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public const ACTIVE_STATUSES = ['pending', 'processing', 'number_assigned', 'waiting_for_sms', 'sms_received'];

    /**
     * Allowed lifecycle transitions. Terminal states have no outgoing edges.
     * Customers can only cancel before an SMS arrives; completion is
     * driven by the provider SMS event, never by a button.
     */
    public const TRANSITIONS = [
        'pending'         => ['processing', 'number_assigned', 'failed', 'cancelled', 'expired'],
        'processing'      => ['number_assigned', 'failed', 'cancelled', 'expired'],
        'number_assigned' => ['waiting_for_sms', 'sms_received', 'cancelled', 'expired', 'failed'],
        'waiting_for_sms' => ['sms_received', 'cancelled', 'expired', 'failed'],
        'sms_received'    => ['completed'],
        'failed'          => ['refunded'],
        'cancelled'       => ['refunded'],
        'expired'         => ['refunded'],
        'completed'       => [],
        'refunded'        => [],
    ];

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? []);
    }

    /**
     * Customer-facing cancellation rule: only while waiting for the SMS,
     * before the activation window closes. Once an SMS arrives (or the
     * order completes/expires/fails), cancellation is off the table.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, ['number_assigned', 'waiting_for_sms'])
            && !$this->isExpired()
            && !$this->cancel_requested_at;
    }

    /**
     * Cancellation accepted and queued with the provider — refund is on
     * its way unless a code arrives first.
     */
    public function isCancelling(): bool
    {
        return $this->cancel_requested_at !== null && $this->isActive();
    }
}