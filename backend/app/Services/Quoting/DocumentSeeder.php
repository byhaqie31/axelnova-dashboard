<?php

namespace App\Services\Quoting;

/**
 * Seeds a canonical STANDARD `document` (the `document.items[]` shape the PDF /
 * DocumentMapper render) from a priced multi-package estimate. This is the single
 * shared implementation behind BOTH the admin "Seed line items from scope" action
 * and the MCP connector — so a connector-created draft arrives with a prefilled,
 * PDF-ready document identical to what the admin button would have produced.
 *
 * Seeding rules (see docs/global/QUOTE_BUILDER.md):
 *   • one line per package base, amount = midpoint of the package's min/max,
 *     rounded to nearest RM 50 (the base line's own range, PRE-rush);
 *   • one line per active modifier / add-on at its exact fixed amount;
 *   • rush → a single "Rush delivery (+20%)" line = the uplift on the subtotal;
 *   • deposit 50%, the three standard terms, valid-until left null (send() defaults it);
 *   • every midpoint-seeded base line appends a matching `assumptions` note.
 */
final class DocumentSeeder
{
    /** Mirrors DocumentMapper::DEFAULT_TERMS — the three standard quotation terms. */
    private const DEFAULT_TERMS = [
        '50% deposit to commence; balance due on delivery before handover.',
        'Revisions are included as scoped per phase; further rounds are quoted separately.',
        'Third-party costs (domains, fonts, hosting) are billed at cost where applicable.',
    ];

    public function __construct(private readonly PricingEngine $engine) {}

    /**
     * Build the seeded document from a priced estimate. `$extraLineItems` are
     * priced-quote extras (the connector's line_items) that ride along as extra
     * document lines, never folded into the engine price.
     *
     * @param  list<array{label?: string, description?: ?string, amount_myr?: float}>  $extraLineItems
     * @return array{document: array<string, mixed>, assumptions: list<string>}
     */
    public function seed(MultiEstimateResult $estimate, bool $rush, array $extraLineItems = []): array
    {
        $items = [];
        $assumptions = [];

        foreach ($estimate->breakdown as $group) {
            $lines = $group['lines'] ?? [];
            if ($lines === []) {
                continue;
            }

            // lines[0] is always the base (PricingEngine::calculate pushes it first,
            // PRE-rush) — seed it at the range midpoint, rounded to RM 50.
            $baseMin = (float) ($lines[0][1] ?? 0);
            $baseMax = (float) ($lines[0][2] ?? 0);
            $rate = self::round50(($baseMin + $baseMax) / 2);
            $name = (string) ($group['name'] ?? ($lines[0][0] ?? 'Project'));

            $items[] = [
                'title' => $name,
                'desc' => (string) ($this->engine->packageTagline((string) ($group['package_key'] ?? '')) ?? ''),
                'qty' => 1,
                'unit' => 'project',
                'rate' => $rate,
            ];
            $assumptions[] = "{$name} seeded at range midpoint RM ".number_format($rate).' — adjust before sending.';

            // Remaining lines = modifiers + add-ons at their EXACT amount (min == max);
            // the rush [·, 0, 0] line (if any) is skipped by the > 0 guard.
            foreach (array_slice($lines, 1) as $line) {
                $amount = (float) ($line[2] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $items[] = [
                    'title' => ltrim((string) ($line[0] ?? 'Item'), '+ '),
                    'desc' => '',
                    'qty' => 1,
                    'unit' => '',
                    'rate' => $amount,
                ];
            }
        }

        // Priced-quote extras (connector line_items) → additional document lines.
        foreach ($extraLineItems as $extra) {
            $items[] = [
                'title' => (string) ($extra['label'] ?? 'Item'),
                'desc' => (string) ($extra['description'] ?? ''),
                'qty' => 1,
                'unit' => '',
                'rate' => (float) ($extra['amount_myr'] ?? 0),
            ];
        }

        // Rush: one "+20%" line computed on the running document subtotal.
        if ($rush && $items !== []) {
            $mult = (float) ($this->engine->getConfig()->config['rush_multiplier'] ?? 1.20);
            $subtotal = array_sum(array_map(static fn ($it): float => (float) $it['rate'], $items));
            $uplift = self::round50($subtotal * ($mult - 1));
            if ($uplift > 0) {
                $pct = (int) round(($mult - 1) * 100);
                $items[] = [
                    'title' => "Rush delivery (+{$pct}%)",
                    'desc' => '',
                    'qty' => 1,
                    'unit' => '',
                    'rate' => $uplift,
                ];
            }
        }

        return [
            'document' => [
                'layout' => 'standard',
                'items' => $items,
                'terms' => self::DEFAULT_TERMS,
                'deposit_pct' => 50,
            ],
            'assumptions' => $assumptions,
        ];
    }

    /**
     * Whether a document already carries content that seeding must NOT clobber
     * (locked decision #4 — the connector never overwrites an admin-edited
     * document, and the UI button confirms before replacing). True for a non-empty
     * standard items list, a legacy connector line_items list, or a detailed
     * payload with priced sections.
     */
    public static function hasContent(?array $document): bool
    {
        if (! is_array($document)) {
            return false;
        }

        return (! empty($document['items']) && is_array($document['items']))
            || (! empty($document['line_items']) && is_array($document['line_items']))
            || ! empty($document['payload']['sections'] ?? null);
    }

    private static function round50(float $value): int
    {
        return (int) (round($value / 50) * 50);
    }
}
