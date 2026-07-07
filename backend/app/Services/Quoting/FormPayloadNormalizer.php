<?php

namespace App\Services\Quoting;

/**
 * Normalises ANY historical `form_payload` shape into the canonical multi-package
 * reader shape. This is the "one write shape, three writers, one renderer" seam:
 * a public-funnel row, a legacy admin row, an MCP-connector row, and a new
 * multi-package row all read identically downstream (PDF mapper, connector view,
 * admin resource, and — via its own port — the frontend hydration).
 *
 * Pure transform: no DB, no engine, no side effects. Writers resolve
 * `service_package_id` and stamp it into `packages[]` at write time; this reader
 * only reflects what's stored (null for legacy rows that predate resolution).
 *
 * Canonical shape:
 *   [
 *     'packages' => [ ['package_key','service_package_id','scope_values','modifiers','addon_keys'], … ],
 *     'rush' => bool,
 *     'breakdown' => [ grouped-per-package … ],   // see MultiEstimateResult
 *     'source_meta' => ['created_via' => ?string],
 *   ]
 */
final class FormPayloadNormalizer
{
    /**
     * @param  array<string, mixed>|null  $formPayload
     * @param  array<string, mixed>|null  $document  Fallback source for created_via (connector rows stamp it there).
     * @return array{
     *     packages: list<array{package_key: ?string, service_package_id: ?int, scope_values: array, modifiers: array, addon_keys: list<string>}>,
     *     rush: bool,
     *     breakdown: list<array<string, mixed>>,
     *     source_meta: array{created_via: ?string}
     * }
     */
    public static function normalize(?array $formPayload, ?string $fallbackPackageKey = null, ?array $document = null): array
    {
        $fp = $formPayload ?? [];

        // created_via: canonical source_meta first, then legacy top-level, then the
        // connector's document stamp (its read scoping keys on document.created_via).
        $createdVia = $fp['source_meta']['created_via']
            ?? ($fp['created_via']
            ?? ($document['created_via'] ?? null));

        if (! empty($fp['packages']) && is_array($fp['packages'])) {
            // New canonical shape — packages[] already present.
            $packages = array_values(array_map(
                fn ($p) => self::normalizePackageEntry((array) $p),
                array_filter($fp['packages'], 'is_array'),
            ));
        } else {
            // Legacy single-package shape (funnel, connector, pre-multi admin) →
            // one entry from the flat keys, falling back to the row's scalar
            // package_key column when form_payload omits it.
            $key = ($fp['package_key'] ?? $fallbackPackageKey) ?: null;
            $packages = [];
            if ($key !== null) {
                $packages[] = self::normalizePackageEntry([
                    'package_key' => $key,
                    'service_package_id' => $fp['service_package_id'] ?? null,
                    'scope_values' => $fp['scope_values'] ?? [],
                    'modifiers' => $fp['modifiers'] ?? [],
                    'addon_keys' => $fp['addon_keys'] ?? [],
                ]);
            }
        }

        return [
            'packages' => $packages,
            'rush' => (bool) ($fp['rush'] ?? false),
            'breakdown' => self::normalizeBreakdown($fp['breakdown'] ?? [], $packages),
            'source_meta' => ['created_via' => $createdVia],
        ];
    }

    /**
     * Flatten a stored breakdown (grouped OR legacy-flat) into a plain list of
     * `[label, min, max]` tuples. The customer email and the DocumentMapper
     * fallback consume this — for a single-package/flat row it returns exactly the
     * tuples they read before, so their rendered output is unchanged.
     *
     * @return list<array{0: string, 1: float, 2: float}>
     */
    public static function flattenBreakdown(mixed $breakdown): array
    {
        if (! is_array($breakdown) || $breakdown === []) {
            return [];
        }

        if (self::isGrouped($breakdown)) {
            $lines = [];
            foreach ($breakdown as $group) {
                foreach ((array) ($group['lines'] ?? []) as $line) {
                    $lines[] = $line;
                }
            }

            return array_values($lines);
        }

        return array_values($breakdown);
    }

    /** @return array{package_key: ?string, service_package_id: ?int, scope_values: array, modifiers: array, addon_keys: list<string>} */
    private static function normalizePackageEntry(array $p): array
    {
        return [
            'package_key' => ($p['package_key'] ?? null) ?: null,
            'service_package_id' => isset($p['service_package_id']) ? ((int) $p['service_package_id'] ?: null) : null,
            'scope_values' => (array) ($p['scope_values'] ?? []),
            'modifiers' => (array) ($p['modifiers'] ?? []),
            'addon_keys' => array_values((array) ($p['addon_keys'] ?? [])),
        ];
    }

    /**
     * Return the breakdown grouped-per-package. New writers store it grouped; a
     * legacy flat tuple list is wrapped into a single group anchored to the row's
     * (first) package so every render path sees the same grouped shape.
     *
     * @param  list<array{package_key: ?string}>  $packages
     */
    private static function normalizeBreakdown(array $breakdown, array $packages): array
    {
        if ($breakdown === []) {
            return [];
        }

        if (self::isGrouped($breakdown)) {
            return array_values($breakdown);
        }

        // Legacy flat tuples → one synthetic group for the row's package.
        return [[
            'package_key' => $packages[0]['package_key'] ?? null,
            'name' => null,
            'min' => null,
            'max' => null,
            'eta_value' => null,
            'eta_unit' => null,
            'lines' => array_values($breakdown),
        ]];
    }

    /** Grouped breakdown entries are associative arrays carrying a 'lines' key. */
    private static function isGrouped(array $breakdown): bool
    {
        $first = $breakdown[array_key_first($breakdown)] ?? null;

        return is_array($first) && array_key_exists('lines', $first);
    }
}
