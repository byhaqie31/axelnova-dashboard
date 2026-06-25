<?php

namespace Database\Seeders;

use App\Models\ServiceAddon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class ServiceAddonsSeeder extends Seeder
{
    public function run(): void
    {
        // The original hardcoded add-ons (pricing_configs JSON), promoted to the
        // DB so they're admin-editable. Keys + amounts match the JSON exactly so
        // existing quotes and the builder are unchanged. sort_order preserves the
        // prior display order. Idempotent — keyed on addon_key.
        $addons = [
            ['addon_key' => 'logo',            'label' => 'Logo / brand identity', 'amount_myr' => 1500],
            ['addon_key' => 'copywriting',     'label' => 'Copywriting',           'amount_myr' => 800],
            ['addon_key' => 'seo',             'label' => 'SEO setup',             'amount_myr' => 600],
            ['addon_key' => 'analytics',       'label' => 'Analytics integration', 'amount_myr' => 400],
            ['addon_key' => 'maintenance_3mo', 'label' => '3 months maintenance',  'amount_myr' => 4500],
        ];

        foreach ($addons as $i => $addon) {
            ServiceAddon::firstOrCreate(
                ['addon_key' => $addon['addon_key']],
                [
                    'label' => $addon['label'],
                    'amount_myr' => $addon['amount_myr'],
                    'sort_order' => $i,
                    'active' => true,
                ],
            );
        }

        Cache::forget('quote_builder_config_v1');

        $this->command?->info('Service add-ons seeded ('.count($addons).').');
    }
}
