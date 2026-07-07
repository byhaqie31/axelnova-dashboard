<?php

namespace App\Services\Quoting;

use Spatie\LaravelData\Data;

/**
 * The summed estimate for a multi-package quotation. Each package is priced by the
 * single-package PricingEngine::calculate() (order unchanged: base → modifiers →
 * add-ons → rush → round), then the min/max are summed and the ETA is the longest
 * package ETA (compared in days, original value/unit of the winner kept).
 *
 * `breakdown` is grouped per package — each group carries the package's own
 * `[label, min, max]` tuple lines (the existing single-package breakdown shape),
 * so the DocumentSeeder and the UI can present one block per package. The group's
 * `lines` are PRE-rush (rush is applied to the running total, never to the pushed
 * lines — see PricingEngine::calculate()); the group's `min`/`max` are the final
 * per-package rounded, rush-applied figures.
 */
class MultiEstimateResult extends Data
{
    public function __construct(
        public readonly int $minMyr,
        public readonly int $maxMyr,
        public readonly int $etaValue,
        public readonly string $etaUnit,
        /**
         * @var list<array{package_key: string, name: string, min: int, max: int, eta_value: int, eta_unit: string, lines: list<array{0: string, 1: float, 2: float}>}>
         */
        public readonly array $breakdown,
    ) {}
}
