<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'name',
        'code',
        'dial_code',
        'flag',
        'is_active',
        'provider_mapping',
        'pricing_config',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}