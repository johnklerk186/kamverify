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
        'provider_mapping',
        'pricing_config',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'customer_enabled' => 'boolean',
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
     * Services a customer can actually buy. is_active stays the
     * platform kill-switch; customer_enabled is the storefront flag.
     */
    public function scopeCustomerEnabled($query)
    {
        return $query->where('is_active', true)->where('customer_enabled', true);
    }
}