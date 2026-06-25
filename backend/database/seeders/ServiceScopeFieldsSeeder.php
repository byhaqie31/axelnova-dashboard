<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use App\Models\ServiceScopeField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Promote the previously-hardcoded quote-builder scope inputs into the
 * service_scope_fields table so the four existing categories keep working
 * identically — but are now admin-editable. Amounts/thresholds are copied
 * exactly from PricingConfigSeeder's `modifiers` map and the old QuoteScopeFields
 * UI. Idempotent (firstOrCreate keyed on category + field_key).
 *
 * Note: `extra_language` is seeded at price_per_unit 0 to preserve the prior
 * behaviour (the legacy modifier was sent as a number with no applies_after, so
 * it never actually priced). It's now a slider you can price from the admin.
 */
class ServiceScopeFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            'web' => [
                ['extra_page', 'Number of pages', 'slider', [], ['min' => 1, 'max' => 20, 'default' => 5, 'unit' => 'page', 'free_threshold' => 5, 'price_per_unit' => 300]],
                ['cms', 'CMS / editable content', 'toggle', ['web_business', 'web_premium'], ['amount' => 1200, 'default' => false]],
                ['booking_flow', 'Booking / enquiry flow', 'toggle', ['web_business', 'web_premium'], ['amount' => 1500, 'default' => false]],
                ['extra_language', 'Number of languages', 'slider', [], ['min' => 1, 'max' => 4, 'default' => 1, 'unit' => 'language', 'free_threshold' => 1, 'price_per_unit' => 0]],
            ],
            'dashboard' => [
                ['extra_module', 'Number of modules', 'slider', [], ['min' => 1, 'max' => 15, 'default' => 5, 'unit' => 'module', 'free_threshold' => 5, 'price_per_unit' => 800]],
                ['user_roles', 'User roles', 'slider', [], ['min' => 1, 'max' => 6, 'default' => 2, 'unit' => 'role', 'free_threshold' => 6, 'price_per_unit' => 0]],
                ['real_time_features', 'Real-time updates (WebSocket)', 'toggle', ['dash_business', 'dash_enterprise'], ['amount' => 2500, 'default' => false]],
                ['charts_complexity', 'Charts complexity', 'select', [], ['default' => 'basic', 'options' => [
                    ['value' => 'none', 'label' => 'None', 'amount' => 0],
                    ['value' => 'basic', 'label' => 'Basic charts', 'amount' => 0],
                    ['value' => 'advanced', 'label' => 'Advanced / custom', 'amount' => 1500],
                ]]],
            ],
            'design-frontend' => [
                ['screens_count', 'Number of screens', 'slider', [], ['min' => 1, 'max' => 40, 'default' => 10, 'unit' => 'screen', 'free_threshold' => 40, 'price_per_unit' => 0]],
                ['components_count', 'Front-end components', 'slider', [], ['min' => 1, 'max' => 50, 'default' => 10, 'unit' => 'component', 'free_threshold' => 50, 'price_per_unit' => 0]],
                ['pages_count', 'Pages built', 'slider', [], ['min' => 1, 'max' => 30, 'default' => 5, 'unit' => 'page', 'free_threshold' => 30, 'price_per_unit' => 0]],
                ['design_system', 'Full design system (tokens + components)', 'toggle', [], ['amount' => 0, 'default' => false]],
                ['prototype', 'Interactive prototype', 'toggle', [], ['amount' => 0, 'default' => false]],
                ['state_management', 'State management (Pinia)', 'toggle', [], ['amount' => 0, 'default' => false]],
                ['testing', 'Unit tests included', 'toggle', [], ['amount' => 0, 'default' => false]],
            ],
            'saas' => [
                ['payment_method', 'Payment integration', 'select', [], ['default' => 'None', 'options' => [
                    ['value' => 'None', 'label' => 'None', 'amount' => 0],
                    ['value' => 'Stripe', 'label' => 'Stripe', 'amount' => 0],
                    ['value' => 'FPX / iPay88', 'label' => 'FPX / iPay88', 'amount' => 0],
                    ['value' => 'Both', 'label' => 'Both', 'amount' => 0],
                ]]],
                ['admin_portal', 'Admin portal needed', 'toggle', [], ['amount' => 0, 'default' => false]],
            ],
        ];

        $seeded = 0;
        foreach ($fields as $slug => $rows) {
            $category = ServiceCategory::where('slug', $slug)->first();
            if (! $category) {
                continue;
            }
            foreach ($rows as $i => [$key, $label, $type, $appliesTo, $config]) {
                ServiceScopeField::firstOrCreate(
                    ['service_category_id' => $category->id, 'field_key' => $key],
                    [
                        'label' => $label,
                        'type' => $type,
                        'applies_to' => $appliesTo,
                        'config' => $config,
                        'sort_order' => $i,
                        'active' => true,
                    ],
                );
                $seeded++;
            }
        }

        Cache::forget('quote_builder_config_v1');

        $this->command?->info("Service scope fields seeded ({$seeded}).");
    }
}
