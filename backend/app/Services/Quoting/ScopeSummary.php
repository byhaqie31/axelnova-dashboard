<?php

namespace App\Services\Quoting;

use App\Models\Quotation;

/**
 * The human-readable scope groups the admin detail pages render (order "Scope
 * snapshot", quotation spec grid). Reads form_payload strictly through the
 * normalizer — never raw — and emits only scalar values, so a canonical
 * payload's `request` / `source_meta` audit objects can never leak into the
 * UI as "[object Object]".
 *
 * Detailed / bespoke quotations return no groups: their scope is the authored
 * document (sections), which the pages present separately.
 */
final class ScopeSummary
{
    /** Pricing controls, audit blobs, and contact identity — never human scope. */
    private const SKIP = [
        'package_key', 'service_package_id', 'category_key', 'scope_values',
        'modifiers', 'addon_keys', 'addons', 'rush', 'breakdown', 'packages',
        'request', 'source_meta', 'created_via',
        'name', 'email', 'phone', 'company', 'message',
    ];

    /**
     * @return list<array{package_key: ?string, label: ?string, scope: array<string, mixed>}>
     */
    public static function forQuotation(Quotation $quotation): array
    {
        $packages = $quotation->normalizedForm()['packages'];
        if ($packages === []) {
            return [];
        }

        $engine = PricingEngine::active();

        $groups = [];
        foreach ($packages as $package) {
            $scope = array_merge(
                self::displayable((array) $package['scope_values']),
                self::displayable((array) $package['modifiers']),
            );

            // Legacy flat rows predate scope_values — their answers sit at the
            // top level of form_payload. Lift the displayable ones so old
            // funnel quotations keep the spec grid they have today.
            if ($scope === [] && count($packages) === 1) {
                $scope = self::displayable(is_array($quotation->form_payload) ? $quotation->form_payload : []);
            }

            $groups[] = [
                'package_key' => $package['package_key'],
                'label' => $package['package_key'] ? $engine->packageName($package['package_key']) : null,
                'scope' => $scope,
            ];
        }

        return $groups;
    }

    /**
     * Scalars and lists-of-scalars only, minus control keys. Objects and
     * associative arrays are dropped outright — they are structure, not scope.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function displayable(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (in_array($key, self::SKIP, true)) {
                continue;
            }
            if (is_scalar($value)) {
                $out[$key] = $value;

                continue;
            }
            if (is_array($value) && $value !== [] && array_is_list($value)
                && count(array_filter($value, 'is_scalar')) === count($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
