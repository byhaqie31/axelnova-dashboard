<?php

namespace App\Services\Connector;

use App\Services\Quoting\PricingEngine;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The connector-facing view of the quote-builder catalog, plus the validation +
 * routing helpers the draft endpoint needs — all derived from the SAME merged
 * config the public `/quote` builder reads (PricingEngine::cachedFrontendConfig).
 *
 * This is the single source of truth the MCP connector reads before drafting:
 * `toArray()` is what `list_catalog` returns, and `isQuotable()` /
 * `validModifierKeys()` / `validAddonKeys()` / `splitModifiers()` are what the
 * draft request validates and prices against — so the catalog the AI sees and
 * the keys the draft accepts can never drift apart.
 *
 * Modifiers are presented + validated PER PACKAGE because the live system carries
 * two overlapping sources (admin-managed `service_scope_fields` per category and
 * the legacy JSON `modifiers` map), and the same key (e.g. `extra_language`) can
 * resolve to different definitions depending on the package's category. Scope
 * fields supersede a legacy modifier of the same key, matching the engine.
 */
final class ConnectorCatalog
{
    /** Normalised quotable packages, keyed by package key. */
    private array $packages = [];

    /** packageKey → category slug (only quotable, category-bearing packages). */
    private array $packageCategory = [];

    /** Active scope fields grouped by category slug (from the merged config). */
    private array $scopeFields = [];

    /** Legacy JSON modifiers: key → ['amount', 'applies_after'?, 'applies_to']. */
    private array $legacyModifiers = [];

    /** Merged add-ons: key → ['amount', 'label']. */
    private array $addons = [];

    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? PricingEngine::cachedFrontendConfig();
        $this->hydrate();
    }

    private function hydrate(): void
    {
        $base = $this->config['base_packages'] ?? [];

        // The public builder's `categories` tree is the definitive list of
        // quotable packages (name, tagline, category) — enrich each with its
        // price/ETA from the merged base_packages map.
        foreach ($this->config['categories'] ?? [] as $cat) {
            foreach ($cat['packages'] ?? [] as $p) {
                $key = $p['key'];
                $b = $base[$key] ?? [];
                $this->packages[$key] = [
                    'key' => $key,
                    'name' => $p['name'] ?? ($b['name'] ?? Str::headline($key)),
                    'tagline' => $p['tagline'] ?? null,
                    'category' => $cat['key'],
                    'category_label' => $cat['label'],
                    'price_min_myr' => (int) ($b['min'] ?? 0),
                    'price_max_myr' => (int) ($b['max'] ?? 0),
                    'eta_value' => (int) ($b['eta_value'] ?? 0),
                    'eta_unit' => (string) ($b['eta_unit'] ?? 'week'),
                ];
                $this->packageCategory[$key] = $cat['key'];
            }
        }

        $this->scopeFields = $this->config['scope_fields'] ?? [];
        $this->legacyModifiers = $this->config['modifiers'] ?? [];
        $this->addons = $this->config['addons'] ?? [];
    }

    /** True when a package key exists in the merged catalog and can be priced. */
    public function isQuotable(string $key): bool
    {
        return isset($this->packages[$key]);
    }

    /** @return list<string> all quotable package keys. */
    public function packageKeys(): array
    {
        return array_keys($this->packages);
    }

    /** @return list<string> every valid add-on key (add-ons apply globally). */
    public function validAddonKeys(): array
    {
        return array_keys($this->addons);
    }

    /** @return list<string> modifier keys the given package accepts. */
    public function validModifierKeys(string $packageKey): array
    {
        return array_keys($this->modifiersForPackage($packageKey));
    }

    /**
     * The normalised modifier definitions applicable to a package: scope fields
     * for its category (gated by applies_to) plus any legacy JSON modifiers gated
     * by applies_to. A scope field WINS over a legacy modifier of the same key
     * (it supersedes it in the engine), so each key maps to exactly one bucket.
     *
     * @return array<string, array> key → normalised entry (carries 'source').
     */
    public function modifiersForPackage(string $packageKey): array
    {
        $out = [];
        $categorySlug = $this->packageCategory[$packageKey] ?? null;

        foreach ($this->scopeFields[$categorySlug] ?? [] as $field) {
            if (! $this->applies($field['applies_to'] ?? 'all', $packageKey)) {
                continue;
            }
            $out[$field['field_key']] = $this->normaliseScopeField($field);
        }

        foreach ($this->legacyModifiers as $key => $def) {
            if (isset($out[$key])) {
                continue; // a scope field of the same key supersedes the legacy modifier
            }
            if (! $this->applies($def['applies_to'] ?? 'all', $packageKey)) {
                continue;
            }
            $out[$key] = $this->normaliseLegacyModifier($key, $def);
        }

        return $out;
    }

    /**
     * Route the connector's flat `modifiers` map onto the engine's two inputs:
     * scope-field keys → `scope_values`, legacy modifier keys → `modifiers`.
     * Unknown keys are dropped here (the draft request rejects them upstream with
     * an instructive 422 before this runs).
     *
     * @return array{modifiers: array<string, mixed>, scope_values: array<string, mixed>}
     */
    public function splitModifiers(string $packageKey, array $provided): array
    {
        $defs = $this->modifiersForPackage($packageKey);
        $modifiers = [];
        $scopeValues = [];

        foreach ($provided as $key => $value) {
            $def = $defs[$key] ?? null;
            if ($def === null) {
                continue;
            }
            if ($def['source'] === 'scope') {
                $scopeValues[$key] = $value;
            } else {
                $modifiers[$key] = $value;
            }
        }

        return ['modifiers' => $modifiers, 'scope_values' => $scopeValues];
    }

    /**
     * The full connector-facing catalog payload. `list_catalog` returns this so
     * Claude has every valid package/modifier/add-on key before it drafts.
     */
    public function toArray(): array
    {
        $packages = array_map(function (array $p): array {
            return array_merge($p, [
                'modifiers' => array_map(
                    fn (array $m) => Arr::except($m, 'source'),
                    array_values($this->modifiersForPackage($p['key'])),
                ),
            ]);
        }, array_values($this->packages));

        $addons = array_map(fn (string $k): array => [
            'key' => $k,
            'label' => (string) $this->addons[$k]['label'],
            'amount_myr' => (int) $this->addons[$k]['amount'],
        ], array_keys($this->addons));

        return [
            'currency' => $this->config['currency'] ?? 'MYR',
            'valid_for_days' => (int) ($this->config['valid_for_days'] ?? 30),
            'rush' => [
                'multiplier' => (float) ($this->config['rush_multiplier'] ?? 1.20),
                'reduces_eta_for_units' => $this->config['rush_units'] ?? ['week', 'month'],
                'note' => 'rush=true always multiplies the price by `multiplier`; it only shortens the ETA for week/month projects.',
            ],
            'packages' => $packages,
            'addons' => $addons,
            'bespoke' => [
                'note' => 'For work that does not fit a catalog package, set package_key to null and pass line_items[] (each with a label and amount_myr). The draft total is the sum of line_items; ETA is left blank for the admin to set. Do not send modifiers or addon_keys with a bespoke quote.',
            ],
        ];
    }

    private function applies(array|string $appliesTo, string $packageKey): bool
    {
        return $appliesTo === 'all' || in_array($packageKey, (array) $appliesTo, true);
    }

    /** Normalise an admin-managed scope field to the connector's modifier shape. */
    private function normaliseScopeField(array $field): array
    {
        $config = $field['config'] ?? [];
        $type = $field['type'];

        $entry = [
            'key' => $field['field_key'],
            'label' => $field['label'],
            'source' => 'scope',
        ];

        if ($type === 'slider') {
            return $entry + [
                'kind' => 'number',
                'value_hint' => 'integer — charged per unit above free_threshold',
                'pricing' => [
                    'min' => $config['min'] ?? null,
                    'max' => $config['max'] ?? null,
                    'default' => $config['default'] ?? null,
                    'unit' => $config['unit'] ?? null,
                    'free_threshold' => (int) ($config['free_threshold'] ?? 0),
                    'price_per_unit_myr' => (int) ($config['price_per_unit'] ?? 0),
                ],
            ];
        }

        if ($type === 'select') {
            return $entry + [
                'kind' => 'select',
                'value_hint' => 'one of the option `value`s below',
                'pricing' => [
                    'default' => $config['default'] ?? null,
                    'options' => array_map(fn ($o): array => [
                        'value' => $o['value'] ?? null,
                        'label' => $o['label'] ?? null,
                        'amount_myr' => (int) ($o['amount'] ?? 0),
                    ], $config['options'] ?? []),
                ],
            ];
        }

        // toggle
        return $entry + [
            'kind' => 'toggle',
            'value_hint' => 'boolean',
            'pricing' => [
                'amount_myr' => (int) ($config['amount'] ?? 0),
                'default' => (bool) ($config['default'] ?? false),
            ],
        ];
    }

    /** Normalise a legacy JSON modifier to the connector's modifier shape. */
    private function normaliseLegacyModifier(string $key, array $def): array
    {
        $numeric = isset($def['applies_after']);

        return [
            'key' => $key,
            'label' => Str::headline($key),
            'source' => 'legacy',
            'kind' => $numeric ? 'number' : 'toggle',
            'value_hint' => $numeric
                ? 'integer — charged per unit above free_threshold'
                : 'boolean',
            'pricing' => $numeric
                ? ['amount_myr' => (int) $def['amount'], 'free_threshold' => (int) $def['applies_after']]
                : ['amount_myr' => (int) $def['amount']],
        ];
    }
}
