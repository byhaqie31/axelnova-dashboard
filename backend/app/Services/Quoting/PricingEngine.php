<?php

namespace App\Services\Quoting;

use App\Models\PricingConfig;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use InvalidArgumentException;

final class PricingEngine
{
    private const ETA_UNITS = ['hour', 'day', 'week', 'month'];

    /** Rush time-reduction only makes sense for these units. */
    private const RUSH_UNITS = ['week', 'month'];

    public function __construct(private readonly PricingConfig $config) {}

    public static function active(): self
    {
        return new self(PricingConfig::getActive());
    }

    public function getConfig(): PricingConfig
    {
        return $this->config;
    }

    public function calculate(QuoteRequestInput $input): EstimateResult
    {
        $cfg = $this->config->config;
        $packages = $this->buildBasePackages();
        $modifierDefs = $cfg['modifiers'] ?? [];
        $addonDefs = $cfg['addons'] ?? [];
        $rushMultiplier = (float) ($cfg['rush_multiplier'] ?? 1.20);

        if (!isset($packages[$input->packageKey])) {
            throw new InvalidArgumentException("Unknown package key: {$input->packageKey}");
        }

        $base = $packages[$input->packageKey];
        $min = (float) $base['min'];
        $max = (float) $base['max'];
        $etaValue = (int) $base['eta_value'];
        $etaUnit = (string) $base['eta_unit'];
        $breakdown = [["Base: {$input->packageKey}", $min, $max]];

        foreach ($input->modifiers as $key => $value) {
            if (!isset($modifierDefs[$key])) {
                continue;
            }

            $def = $modifierDefs[$key];
            $appliesTo = $def['applies_to'] ?? 'all';

            if ($appliesTo !== 'all' && !in_array($input->packageKey, (array) $appliesTo, true)) {
                continue;
            }

            if (isset($def['applies_after'])) {
                $count = (int) $value;
                $threshold = (int) $def['applies_after'];
                if ($count > $threshold) {
                    $extra = ($count - $threshold) * (float) $def['amount'];
                    $min += $extra;
                    $max += $extra;
                    $breakdown[] = ['+'.($count - $threshold)." {$key}", $extra, $extra];
                }
            } elseif ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                $extra = (float) $def['amount'];
                $min += $extra;
                $max += $extra;
                $breakdown[] = ["+{$key}", $extra, $extra];
            }
        }

        foreach ($input->addonKeys as $addonKey) {
            if (!isset($addonDefs[$addonKey])) {
                continue;
            }
            $addon = $addonDefs[$addonKey];
            $amount = (float) $addon['amount'];
            $min += $amount;
            $max += $amount;
            $breakdown[] = ["Addon: {$addon['label']}", $amount, $amount];
        }

        if ($input->rush) {
            $min *= $rushMultiplier;
            $max *= $rushMultiplier;
            // Only meaningful for week/month projects; skip silently otherwise so the
            // price multiplier still applies but ETA stays put.
            if (in_array($etaUnit, self::RUSH_UNITS, true)) {
                $etaValue = max(1, (int) floor($etaValue * 0.70));
            }
            $breakdown[] = ["Rush delivery (×{$rushMultiplier})", 0, 0];
        }

        // Round to nearest 50 MYR
        $min = (int) (round($min / 50) * 50);
        $max = (int) (round($max / 50) * 50);

        return new EstimateResult(
            minMyr: $min,
            maxMyr: $max,
            etaValue: $etaValue,
            etaUnit: $etaUnit,
            breakdown: $breakdown,
        );
    }

    public function configForFrontend(): array
    {
        $cfg = $this->config->config;

        return [
            'version' => $this->config->version,
            'base_packages' => $this->buildBasePackages(),
            'categories' => $this->buildCategories(),
            'modifiers' => $cfg['modifiers'] ?? [],
            'addons' => $cfg['addons'] ?? [],
            'rush_multiplier' => $cfg['rush_multiplier'] ?? 1.20,
            'rush_units' => self::RUSH_UNITS,
            'currency' => $cfg['currency'] ?? 'MYR',
            'valid_for_days' => $cfg['valid_for_days'] ?? 30,
        ];
    }

    /**
     * Merge admin-managed service_packages on top of the pricing_configs JSON.
     * DB rows win where present; legacy JSON-only keys still resolve.
     *
     * Each entry is normalised to: ['min' => float, 'max' => float, 'eta_value' => int, 'eta_unit' => string].
     * Legacy JSON entries with `weeks` are translated to {eta_value: weeks, eta_unit: 'week'}.
     */
    private function buildBasePackages(): array
    {
        $merged = [];
        foreach ($this->config->config['base_packages'] ?? [] as $key => $entry) {
            $merged[$key] = [
                'min' => (float) ($entry['min'] ?? 0),
                'max' => (float) ($entry['max'] ?? 0),
                'eta_value' => (int) ($entry['eta_value'] ?? $entry['weeks'] ?? 4),
                'eta_unit' => (string) ($entry['eta_unit'] ?? 'week'),
            ];
        }

        $packages = ServicePackage::where('active', true)
            ->whereNotNull('quote_key')
            ->whereNotNull('price_max_myr')
            ->get();

        foreach ($packages as $p) {
            $key = $p->quote_key['package'] ?? null;
            if (!$key) {
                continue;
            }
            $merged[$key] = [
                'min' => (float) $p->price_min_myr,
                'max' => (float) $p->price_max_myr,
                'eta_value' => (int) ($p->eta_value ?: 4),
                'eta_unit' => in_array($p->eta_unit, self::ETA_UNITS, true) ? $p->eta_unit : 'week',
            ];
        }

        return $merged;
    }

    /**
     * Build the category tree for the public quote builder from active service_categories
     * that have at least one quotable package (non-null quote_key + price_max_myr).
     */
    private function buildCategories(): array
    {
        $categories = ServiceCategory::where('active', true)
            ->with(['packages' => fn ($q) => $q->where('active', true)
                ->whereNotNull('quote_key')
                ->whereNotNull('price_max_myr')
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return $categories
            ->filter(fn ($c) => $c->packages->isNotEmpty())
            ->map(fn ($c) => [
                'key' => $c->slug,
                'label' => $c->name,
                'icon' => $c->icon,
                'packages' => $c->packages->map(fn ($p) => [
                    'key' => $p->quote_key['package'],
                    'name' => $p->name,
                    'tagline' => $p->tagline,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
