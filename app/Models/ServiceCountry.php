<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-service country settings — an absent row means the country is
 * enabled and not popular for that service. Rows only exist where an
 * admin has set an explicit override.
 */
class ServiceCountry extends Model
{
    protected $fillable = [
        'service_id',
        'country_id',
        'is_enabled',
        'is_popular',
        'popular_sort',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_popular' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
