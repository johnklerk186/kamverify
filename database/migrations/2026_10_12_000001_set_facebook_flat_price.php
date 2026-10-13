<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pin Facebook's customer price to a flat 1,000 XAF.
 *
 * pricing_config['price'] is an absolute selling price resolved by
 * PricingService; it wins over markup and is floored at the converted
 * provider cost so a country whose cost exceeds 1,000 XAF can never
 * sell at a loss. The existing fixed markup is kept so clearing the
 * price in admin later restores the previous pricing.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('services')->where('slug', 'facebook')->get()->each(function ($row) {
            $config = json_decode($row->pricing_config ?? '', true) ?: [];
            $config['price'] = 1000;
            DB::table('services')->where('id', $row->id)
                ->update(['pricing_config' => json_encode($config)]);
        });
    }

    public function down(): void
    {
        DB::table('services')->where('slug', 'facebook')->get()->each(function ($row) {
            $config = json_decode($row->pricing_config ?? '', true) ?: [];
            unset($config['price']);
            DB::table('services')->where('id', $row->id)
                ->update(['pricing_config' => json_encode($config)]);
        });
    }
};
