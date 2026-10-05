<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'is_enabled',
        'starts_at',
        'ends_at',
        'config',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
        'config'     => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Backend truth for "is the promotion live right now" — enabled flag
     * AND inside the [starts_at, ends_at) window. Expiry needs no cron or
     * cleanup: once now() passes ends_at this simply returns false and
     * every price falls back to normal.
     */
    public function isActive(): bool
    {
        $now = now();

        return $this->is_enabled
            && $this->starts_at !== null && $this->starts_at->lte($now)
            && $this->ends_at !== null && $this->ends_at->gt($now);
    }

    public function status(): string
    {
        if (!$this->is_enabled) {
            return 'disabled';
        }
        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return 'expired';
        }
        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        return 'active';
    }

    public function price(string $key): ?int
    {
        $value = $this->config[$key] ?? null;

        return $value === null ? null : (int) round((float) $value);
    }
}
