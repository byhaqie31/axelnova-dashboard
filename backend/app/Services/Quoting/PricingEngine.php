<?php

namespace App\Services\Quoting;

use App\Models\PricingConfig;
use App\Models\ServiceAddon;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\ServiceScopeField;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PricingEngine
{
    private const ETA_UNITS = ['hour', 'day', 'week', 'month'];

    /** Rush time-reduction only makes sense for these units. */
    private const RUSH_UNITS = ['week', 'month'];

    /** Memoised merged catalog — built once per instance (calculate + config + name lookups). */
    private ?array $basePackages = null;

    /** Memoised merged add-ons (admin-managed service_addons over pricing JSON). */
    private ?array $addons = null;

    /** Memoised active scope fields, grouped by category slug. */
    private ?array $scopeFields = null;

    /** Memoised packageKey (quote_key.package) → category slug map. */
    private ?array $packageCategory = null;

    public function __construct(private readonly PricingConfig $config) {}

    public static function active(): self
    {
        return new self(PricingConfig::getActive());
    }

    /**
     * The merged quote-builder config (pricing JSON + admin-managed catalog),
     * cached for 1h under a key the Pricing/ServiceCategory/ServicePackage
     * observers invalidate on every catalog edit. This is the single source of
     * truth shared by the config endpoint and the server-side validators, so
     * they accept exactly the package / addon keys the builder offered.
     */
    public static function cachedFrontendConfig(): array
    {
        return Cache::remember(
            'quote_builder_config_v1',
            3600,
            fn () => self::active()->configForFrontend(),
        );
    }

    public function getConfig(): PricingConfig
    {
        return $this->config;
    }

    /**
     * Human-readable package name from the merged catalog (admin-managed
     * service_packages name; humanised key for legacy JSON-only entries). Used
     * wherever a package would otherwise surface as a raw slug — breakdown lines,
     * document line items, customer email.
     */
    public function packageName(string $key): string
    {
        return $this->buildBasePackages()[$key]['name'] ?? Str::headline($key);
    }

    /**
     * Merged add-on definitions (admin-managed service_addons over the legacy
     * pricing JSON), keyed by addon_key → ['amount' => float, 'label' => string].
     * Shared by calculate(), the config endpoint, and the quote consumers so the
     * builder, the price, and the stored line all agree.
     */
    public function addons(): array
    {
        return $this->buildAddons();
    }

    public function calculate(QuoteRequestInput $input): EstimateResult
    {
        $cfg = $this->config->config;
        $packages = $this->buildBasePackages();
        $modifierDefs = $cfg['modifiers'] ?? [];
        $addonDefs = $this->buildAddons();
        $rushMultiplier = (float) ($cfg['rush_multiplier'] ?? 1.20);

        if (!isset($packages[$input->packageKey])) {
            throw new InvalidArgumentException("Unknown package key: {$input->packageKey}");
        }

        $base = $packages[$input->packageKey];
        $min = (float) $base['min'];
        $max = (float) $base['max'];
        $etaValue = (int) $base['eta_value'];
        $etaUnit = (string) $base['eta_unit'];
        // Label with the catalog name (not the slug) — this breakdown is shown to
        // the client in the email and the PDF fallback line items.
        $breakdown = [['Base: '.($base['name'] ?? $input->packageKey), $min, $max]];

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
                    // De-slug the modifier key for the client-facing breakdown.
                    $breakdown[] = ['+'.($count - $threshold).' '.str_replace('_', ' ', $key), $extra, $extra];
                }
            } elseif ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                $extra = (float) $def['amount'];
                $min += $extra;
                $max += $extra;
                $breakdown[] = ['+'.str_replace('_', ' ', $key), $extra, $extra];
            }
        }

        // Data-driven scope fields (admin-managed; supersede the JSON modifiers
        // above). Evaluate the selected package's category fields gated by applies_to.
        // MUST stay in sync with usePricingEngine.ts calculate().
        $catSlug = $this->packageCategories()[$input->packageKey] ?? null;
        foreach ($this->buildScopeFields()[$catSlug] ?? [] as $field) {
            $appliesTo = $field['applies_to'];
            if ($appliesTo !== 'all' && !in_array($input->packageKey, (array) $appliesTo, true)) {
                continue;
            }
            $fc = $field['config'];
            $value = $input->scopeValues[$field['field_key']] ?? ($fc['default'] ?? null);
            $extra = 0.0;
            $label = '';

            if ($field['type'] === 'slider') {
                $over = max(0, (int) $value - (int) ($fc['free_threshold'] ?? 0));
                $extra = $over * (float) ($fc['price_per_unit'] ?? 0);
                $unit = $fc['unit'] ?? str_replace('_', ' ', $field['field_key']);
                $label = '+'.$over.' '.$unit;
            } elseif ($field['type'] === 'toggle') {
                if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                    $extra = (float) ($fc['amount'] ?? 0);
                    $label = '+'.$field['label'];
                }
            } elseif ($field['type'] === 'select') {
                foreach ($fc['options'] ?? [] as $opt) {
                    if ((string) ($opt['value'] ?? '') === (string) $value) {
                        $extra = (float) ($opt['amount'] ?? 0);
                        $label = $field['label'].': '.($opt['label'] ?? $opt['value'] ?? '');
                        break;
                    }
                }
            }

            if ($extra > 0) {
                $min += $extra;
                $max += $extra;
                $breakdown[] = [$label, $extra, $extra];
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
            'addons' => $this->buildAddons(),
            'scope_fields' => $this->buildScopeFields(),
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
     * Each entry is normalised to: ['min' => float, 'max' => float, 'eta_value' => int, 'eta_unit' => string, 'name' => string].
     * `name` is the catalog name (DB) or a humanised key (legacy JSON-only); it
     * de-slugs the package everywhere it surfaces to the client.
     * Legacy JSON entries with `weeks` are translated to {eta_value: weeks, eta_unit: 'week'}.
     */
    private function buildBasePackages(): array
    {
        if ($this->basePackages !== null) {
            return $this->basePackages;
        }

        $merged = [];
        foreach ($this->config->config['base_packages'] ?? [] as $key => $entry) {
            $merged[$key] = [
                'min' => (float) ($entry['min'] ?? 0),
                'max' => (float) ($entry['max'] ?? 0),
                'eta_value' => (int) ($entry['eta_value'] ?? $entry['weeks'] ?? 4),
                'eta_unit' => (string) ($entry['eta_unit'] ?? 'week'),
                'name' => (string) ($entry['name'] ?? Str::headline($key)),
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
                'name' => $p->name,
            ];
        }

        return $this->basePackages = $merged;
    }

    /**
     * Merge admin-managed service_addons on top of the pricing_configs JSON.
     *
     * A DB row claims its key: active rows appear (in sort_order); a row that
     * exists but is inactive removes the key entirely — so deactivating a seeded
     * add-on actually hides it instead of falling back to the JSON copy. Legacy
     * JSON keys with no DB row still resolve. Output: addon_key → ['amount', 'label'].
     */
    private function buildAddons(): array
    {
        if ($this->addons !== null) {
            return $this->addons;
        }

        $dbAddons = ServiceAddon::orderBy('sort_order')->get();
        $claimed = $dbAddons->pluck('addon_key')->all();

        $merged = [];
        // Legacy JSON add-ons that no DB row has taken over.
        foreach ($this->config->config['addons'] ?? [] as $key => $entry) {
            if (!in_array($key, $claimed, true)) {
                $merged[$key] = [
                    'amount' => (float) ($entry['amount'] ?? 0),
                    'label' => (string) ($entry['label'] ?? Str::headline($key)),
                ];
            }
        }
        // Admin-managed add-ons, in sort order; inactive ones stay excluded.
        foreach ($dbAddons as $a) {
            if ($a->active) {
                $merged[$a->addon_key] = [
                    'amount' => (float) $a->amount_myr,
                    'label' => $a->label,
                ];
            }
        }

        return $this->addons = $merged;
    }

    /**
     * Active scope fields grouped by category slug, normalised for the builder +
     * the engine: ['<slug>' => [ ['field_key','label','type','applies_to','config'], … ]].
     * `applies_to` is emitted as 'all' for an empty array so the TS union stays
     * `string[] | 'all'` (matching the legacy modifier shape).
     */
    private function buildScopeFields(): array
    {
        if ($this->scopeFields !== null) {
            return $this->scopeFields;
        }

        $rows = ServiceScopeField::where('active', true)
            ->with('category:id,slug')
            ->orderBy('service_category_id')
            ->orderBy('sort_order')
            ->get();

        $map = [];
        foreach ($rows as $f) {
            $slug = $f->category?->slug;
            if (!$slug) {
                continue;
            }
            $map[$slug][] = [
                'field_key' => $f->field_key,
                'label' => $f->label,
                'type' => $f->type,
                'applies_to' => !empty($f->applies_to) ? $f->applies_to : 'all',
                'config' => $f->config ?? [],
            ];
        }

        return $this->scopeFields = $map;
    }

    /**
     * Map each quotable packageKey (quote_key.package) to its category slug — needed
     * to resolve which scope fields apply (a field's category, not its quote_key).
     */
    private function packageCategories(): array
    {
        if ($this->packageCategory !== null) {
            return $this->packageCategory;
        }

        $map = [];
        $packages = ServicePackage::whereNotNull('quote_key')->with('category:id,slug')->get();
        foreach ($packages as $p) {
            $key = $p->quote_key['package'] ?? null;
            if ($key && $p->category) {
                $map[$key] = $p->category->slug;
            }
        }

        return $this->packageCategory = $map;
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
