<?php

namespace Database\Seeders;

use App\Models\PricingConfig;
use Illuminate\Database\Seeder;

class PricingConfigSeeder extends Seeder
{
    public function run(): void
    {
        PricingConfig::create([
            'version' => '2026.05.01',
            'active' => true,
            'notes' => 'Initial pricing config — Phase 3 launch.',
            'config' => [
                'base_packages' => [
                    'web_essential'      => ['min' => 1500, 'max' => 2500,  'weeks' => 1],
                    'web_business'       => ['min' => 3000, 'max' => 5000,  'weeks' => 2],
                    'web_premium'        => ['min' => 6000, 'max' => 10000, 'weeks' => 4],
                    'dash_starter'       => ['min' => 4000, 'max' => 6000,  'weeks' => 3],
                    'dash_business'      => ['min' => 8000, 'max' => 12000, 'weeks' => 5],
                    'dash_enterprise'    => ['min' => 15000,'max' => 25000, 'weeks' => 10],
                    'design_audit'       => ['min' => 800,  'max' => 1500,  'weeks' => 1],
                    'design_figma'       => ['min' => 2500, 'max' => 5000,  'weeks' => 2],
                    'design_full'        => ['min' => 6000, 'max' => 12000, 'weeks' => 5],
                    'frontend_components'=> ['min' => 2000, 'max' => 4000,  'weeks' => 2],
                    'frontend_pages'     => ['min' => 4000, 'max' => 8000,  'weeks' => 3],
                    'frontend_full'      => ['min' => 10000,'max' => 18000, 'weeks' => 6],
                    'saas_mvp_sprint'    => ['min' => 12000,'max' => 20000, 'weeks' => 6],
                    'saas_full_mvp'      => ['min' => 25000,'max' => 40000, 'weeks' => 10],
                ],
                'modifiers' => [
                    'extra_page'        => ['amount' => 300,  'applies_after' => 5,  'applies_to' => ['web_essential','web_business','web_premium']],
                    'extra_module'      => ['amount' => 800,  'applies_after' => 5,  'applies_to' => ['dash_starter','dash_business','dash_enterprise']],
                    'extra_language'    => ['amount' => 600,  'applies_to' => 'all'],
                    'cms'               => ['amount' => 1200, 'applies_to' => ['web_business','web_premium']],
                    'booking_flow'      => ['amount' => 1500, 'applies_to' => ['web_business','web_premium']],
                    'stripe_integration'=> ['amount' => 1800, 'applies_to' => 'all'],
                    'fpx_integration'   => ['amount' => 2200, 'applies_to' => 'all'],
                    'real_time_features'=> ['amount' => 2500, 'applies_to' => ['dash_business','dash_enterprise','saas_full_mvp']],
                    'advanced_charts'   => ['amount' => 1500, 'applies_to' => ['dash_starter','dash_business','dash_enterprise']],
                ],
                'addons' => [
                    'logo'            => ['amount' => 1500, 'label' => 'Logo / brand identity'],
                    'copywriting'     => ['amount' => 800,  'label' => 'Copywriting'],
                    'seo'             => ['amount' => 600,  'label' => 'SEO setup'],
                    'analytics'       => ['amount' => 400,  'label' => 'Analytics integration'],
                    'maintenance_3mo' => ['amount' => 4500, 'label' => '3 months maintenance'],
                ],
                'rush_multiplier' => 1.20,
                'currency' => 'MYR',
                'valid_for_days' => 30,
            ],
        ]);
    }
}
