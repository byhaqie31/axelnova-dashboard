<?php

namespace App\Services\Quoting;

use Spatie\LaravelData\Data;

class EstimateResult extends Data
{
    public function __construct(
        public readonly int $minMyr,
        public readonly int $maxMyr,
        public readonly int $weeks,
        public readonly array $breakdown,
    ) {}
}
