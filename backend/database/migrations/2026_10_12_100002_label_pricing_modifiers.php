<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Display labels (and a unit for counted modifiers) on the active pricing
 * config's legacy modifiers, so the /services estimator and the connector's
 * list_catalog can show "Stripe card payments" instead of a de-slugged key.
 * Presentation only — amounts, applies_to and thresholds are never touched,
 * and a label already present is kept. Updated in place: labels never change
 * a price, so no new pricing version is needed.
 */
return new class extends Migration
{
    private const LABELS = [
        'extra_page' => ['label' => 'Number of pages', 'unit' => 'page'],
        'extra_module' => ['label' => 'Number of modules', 'unit' => 'module'],
        'extra_language' => ['label' => 'Extra language'],
        'cms' => ['label' => 'CMS'],
        'booking_flow' => ['label' => 'Booking flow'],
        'stripe_integration' => ['label' => 'Stripe card payments'],
        'fpx_integration' => ['label' => 'FPX online banking'],
        'real_time_features' => ['label' => 'Real-time updates'],
        'advanced_charts' => ['label' => 'Advanced charts'],
    ];

    public function up(): void
    {
        $active = DB::table('pricing_configs')->where('active', true)->orderByDesc('id')->first();
        if (! $active) {
            return;
        }

        $config = json_decode($active->config, true);
        $modifiers = $config['modifiers'] ?? [];
        foreach (self::LABELS as $key => $meta) {
            if (isset($modifiers[$key])) {
                $modifiers[$key] += $meta; // keeps any label already set
            }
        }
        $config['modifiers'] = $modifiers;

        DB::table('pricing_configs')->where('id', $active->id)->update([
            'config' => json_encode($config),
            'updated_at' => now(),
        ]);

        Cache::forget('quote_builder_config_v1');
        Cache::forget('pricing_config_active_v1');
    }

    public function down(): void
    {
        // Labels are display-only and harmless to keep.
    }
};
