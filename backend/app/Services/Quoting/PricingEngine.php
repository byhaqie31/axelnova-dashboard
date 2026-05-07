<?php

namespace App\Services\Quoting;

use App\Models\PricingConfig;
use InvalidArgumentException;

final class PricingEngine
{
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
        $packages = $cfg['base_packages'] ?? [];
        $modifierDefs = $cfg['modifiers'] ?? [];
        $addonDefs = $cfg['addons'] ?? [];
        $rushMultiplier = (float) ($cfg['rush_multiplier'] ?? 1.20);

        if (!isset($packages[$input->packageKey])) {
            throw new InvalidArgumentException("Unknown package key: {$input->packageKey}");
        }

        $base = $packages[$input->packageKey];
        $min = (float) $base['min'];
        $max = (float) $base['max'];
        $weeks = (int) $base['weeks'];
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
            $weeks = max(1, (int) floor($weeks * 0.70));
            $breakdown[] = ["Rush delivery (×{$rushMultiplier})", 0, 0];
        }

        // Round to nearest 50 MYR
        $min = (int) (round($min / 50) * 50);
        $max = (int) (round($max / 50) * 50);

        return new EstimateResult(
            minMyr: $min,
            maxMyr: $max,
            weeks: $weeks,
            breakdown: $breakdown,
        );
    }

    public function configForFrontend(): array
    {
        $cfg = $this->config->config;

        return [
            'version' => $this->config->version,
            'base_packages' => $cfg['base_packages'] ?? [],
            'modifiers' => $cfg['modifiers'] ?? [],
            'addons' => $cfg['addons'] ?? [],
            'rush_multiplier' => $cfg['rush_multiplier'] ?? 1.20,
            'currency' => $cfg['currency'] ?? 'MYR',
            'valid_for_days' => $cfg['valid_for_days'] ?? 30,
        ];
    }
}
