<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'is_active',
        'customer_enabled',
        'temporarily_unavailable',
        'provider_mapping',
        'pricing_config',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'customer_enabled' => 'boolean',
        'temporarily_unavailable' => 'boolean',
        'provider_mapping' => 'array',
        'pricing_config' => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Services a customer can actually BUY. is_active stays the
     * platform kill-switch, customer_enabled the storefront flag, and
     * temporarily_unavailable blocks purchase while the service stays
     * visible with an outage notice. All three purchase routes
     * (serviceCountries, quote, store) gate on this scope.
     */
    public function scopeCustomerEnabled($query)
    {
        return $query->where('is_active', true)
            ->where('customer_enabled', true)
            ->where('temporarily_unavailable', false);
    }

    /**
     * Services shown on storefront lists — includes temporarily
     * unavailable ones so they render with a "Temporarily Unavailable"
     * badge instead of disappearing.
     */
    public function scopeCustomerVisible($query)
    {
        return $query->where('is_active', true)->where('customer_enabled', true);
    }

    /** Outage notice shown on login and whenever the service is picked. */
    public function outageMessage(): string
    {
        return "{$this->name} verification is temporarily unavailable while we resolve a technical issue. "
            . "We're working to restore the service as soon as possible. Thank you for your patience.";
    }
}