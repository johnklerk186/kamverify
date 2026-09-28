<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'api_key',
        'base_url',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function providerServices(): HasMany
    {
        return $this->hasMany(ProviderService::class);
    }

    public function providerCountries(): HasMany
    {
        return $this->hasMany(ProviderCountry::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'provider_services')
            ->withPivot(['provider_service_code', 'cost', 'is_active'])
            ->withTimestamps();
    }

    public function countries()
    {
        return $this->belongsToMany(Country::class, 'provider_countries')
            ->withPivot(['provider_country_code', 'is_active'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}