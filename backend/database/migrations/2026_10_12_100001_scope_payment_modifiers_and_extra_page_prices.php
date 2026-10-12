<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Package modifiers after the 2026-10 catalog rewrite. The modifiers live in the
 * legacy `pricing_configs.config.modifiers` JSON (no admin editor, seeders never
 * run on deploy), so the change ships as a data migration that clones the active
 * row into a new version — the old row stays as history, every quotation keeps
 * the pricing_config_id it was priced with, and reactivating the old row undoes it.
 *
 *  - stripe_integration / fpx_integration: off the creative, audit and design
 *    system packages (was `applies_to: all`); kept on every web, booking,
 *    e-commerce, admin and design_build package, and on the legacy JSON keys.
 *  - extra_page (per page above 5): RM500 on web_business, RM800 on web_premium
 *    via `amount_by_package` (PricingEngine::modifierAmount). The flat amount
 *    (legacy web_essential) is unchanged.
 *
 * Base prices, add-ons and the rush rule are copied untouched. A fresh database
 * (tests, new installs) has no active row, so this is a no-op there.
 */
return new class extends Migration
{
    private const VERSION = '2026.10.12';

    /** Packages that lose the payment modifiers. */
    private const NO_PAYMENTS = [
        'single_asset', 'campaign_pack', 'event_activation', 'brand_refresh',
        'content_retainer', 'ux_audit', 'design_system',
    ];

    /** Packages that keep them (web, booking, e-commerce, admin, design_build). */
    private const PAYMENTS = [
        'web_starter', 'web_business', 'web_premium',
        'booking_lite', 'booking_pro',
        'store_starter', 'store_pro',
        'admin_starter', 'admin_business',
        'design_build',
    ];

    private const EXTRA_PAGE_PRICES = ['web_business' => 500, 'web_premium' => 800];

    public function up(): void
    {
        if (DB::table('pricing_configs')->where('version', self::VERSION)->exists()) {
            return;
        }

        $active = DB::table('pricing_configs')->where('active', true)->orderByDesc('id')->first();
        if (! $active) {
            return;
        }

        $config = json_decode($active->config, true);
        $modifiers = $config['modifiers'] ?? [];

        foreach (['stripe_integration', 'fpx_integration'] as $key) {
            if (! isset($modifiers[$key])) {
                continue;
            }
            $appliesTo = $modifiers[$key]['applies_to'] ?? 'all';
            // `all` becomes an explicit list: every legacy JSON package plus the
            // named build packages, minus the ones that drop payments.
            $current = $appliesTo === 'all' ? array_keys($config['base_packages'] ?? []) : (array) $appliesTo;
            $modifiers[$key]['applies_to'] = array_values(array_diff(
                array_unique([...$current, ...self::PAYMENTS]),
                self::NO_PAYMENTS,
            ));
        }

        $modifiers['extra_page'] ??= ['amount' => 500, 'applies_after' => 5, 'applies_to' => []];
        $extraPageTo = $modifiers['extra_page']['applies_to'] ?? 'all';
        if ($extraPageTo !== 'all') {
            $modifiers['extra_page']['applies_to'] = array_values(array_unique([
                ...(array) $extraPageTo,
                ...array_keys(self::EXTRA_PAGE_PRICES),
            ]));
        }
        $modifiers['extra_page']['amount_by_package'] = self::EXTRA_PAGE_PRICES;

        $config['modifiers'] = $modifiers;

        DB::transaction(function () use ($config, $active) {
            $id = DB::table('pricing_configs')->insertGetId([
                'version' => self::VERSION,
                'config' => json_encode($config),
                'active' => true,
                'notes' => "Payment modifiers scoped to build packages; extra_page RM500 (web_business) / RM800 (web_premium). Cloned from {$active->version}.",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('pricing_configs')->where('id', '!=', $id)->update(['active' => false]);
        });

        $this->forgetCaches();
    }

    public function down(): void
    {
        $row = DB::table('pricing_configs')->where('version', self::VERSION)->first();
        if (! $row) {
            return;
        }

        // Quotations may reference this row, so it is deactivated, never deleted.
        DB::transaction(function () use ($row) {
            DB::table('pricing_configs')->where('id', $row->id)->update(['active' => false]);
            $previous = DB::table('pricing_configs')->where('id', '<', $row->id)->orderByDesc('id')->first();
            if ($previous) {
                DB::table('pricing_configs')->where('id', $previous->id)->update(['active' => true]);
            }
        });

        $this->forgetCaches();
    }

    /** Same keys PricingConfigObserver clears (the query builder skips the observer). */
    private function forgetCaches(): void
    {
        Cache::forget('quote_builder_config_v1');
        Cache::forget('pricing_config_active_v1');
    }
};
