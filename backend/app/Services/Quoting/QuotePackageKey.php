<?php

namespace App\Services\Quoting;

use App\Models\PricingConfig;
use App\Models\ServicePackage;

/**
 * Who owns a quote package key (`service_packages.quote_key.package`) — the key
 * the pricing engine, a quotation's form_payload and a scope field's
 * `applies_to` all address a package by.
 *
 * A key belongs to ONE package row. The engine resolves a key's id, tagline and
 * category on HIDDEN rows too (so old quotes keep resolving after a package is
 * hidden), which means a second row holding the same key — even a hidden draft —
 * would silently re-point new quotes at it. Legacy pricing-JSON keys
 * (`base_packages`) are reported separately: the admin may take one over on
 * purpose (that is how DB rows replace JSON entries); the connector may not.
 */
final class QuotePackageKey
{
    /** The other package row already holding $key, if any. */
    public static function heldBy(string $key, ?int $ignoreId = null): ?ServicePackage
    {
        return ServicePackage::query()
            ->where('quote_key->package', $key)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->first();
    }

    /** Whether the active pricing config's legacy JSON already prices $key. */
    public static function inPricingConfig(string $key): bool
    {
        $config = PricingConfig::where('active', true)->first();

        return array_key_exists($key, $config?->config['base_packages'] ?? []);
    }

    public static function takenMessage(string $key, ServicePackage $holder): string
    {
        return "The quote package key \"{$key}\" already belongs to the package \"{$holder->name}\" (id {$holder->id}). Pick a new key.";
    }
}
