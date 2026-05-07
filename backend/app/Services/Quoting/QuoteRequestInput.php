<?php

namespace App\Services\Quoting;

use Spatie\LaravelData\Data;

class QuoteRequestInput extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $company,
        public readonly string $packageKey,
        public readonly array $modifiers,
        public readonly array $addonKeys,
        public readonly bool $rush,
    ) {}
}
