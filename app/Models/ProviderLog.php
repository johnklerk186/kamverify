<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderLog extends Model
{
    protected $fillable = [
        'provider_id',
        'order_id',
        'action',
        'activation_id',
        'status',
        'http_status',
        'error_code',
        'message',
        'duration_ms',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
