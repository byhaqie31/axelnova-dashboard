<?php

namespace App\Services\Quoting;

/**
 * Builds a canonical DETAILED `document` (layout=detailed + document.payload) from
 * the MCP connector's structured `detailed` input — priced sections (the scope),
 * a "What's included" list, side-by-side option cards, and a care plan.
 *
 * Mirrors the admin builder's detailed buildPayload + DetailedProposalFields shape
 * exactly, so the SAME DocumentMapper + PDF template render it and a connector
 * detailed draft re-opens in the admin builder's detailed mode. Detailed quotes are
 * priced by their own section totals (Quotation::sumDetailedSections), NOT the
 * engine — Claude provides the prices.
 */
final class DetailedDocumentBuilder
{
    /**
     * @param  array<string, mixed>  $detailed  Validated connector `detailed` input.
     * @return array{document: array<string, mixed>, total: float} total = Σ section prices (the agreed range, min == max).
     */
    public function build(array $detailed, ?string $project, ?string $intro): array
    {
        $depositPct = (int) ($detailed['deposit_pct'] ?? 50);
        // Deposit / plan inputs the connector may send beside deposit_pct —
        // stored on the document as given (only the keys present), and used
        // below to derive the panels + terms so they agree with the PDF mapper.
        $planInputs = PaymentPlan::inputsFrom($detailed);

        // Priced sections — each row's amount_myr → row.price; section.total = Σ rows.
        $sections = array_map(function (array $s): array {
            $rows = array_map(fn (array $r): array => [
                'title' => (string) $r['title'],
                'detail' => (string) ($r['detail'] ?? ''),
                'price' => (float) $r['amount_myr'],
            ], array_values($s['rows']));

            return [
                'title' => (string) $s['title'],
                'rows' => $rows,
                'totalLabel' => $s['title'].' total',
                'total' => (float) array_sum(array_column($rows, 'price')),
            ];
        }, array_values($detailed['sections']));

        $scopeTotal = (float) array_sum(array_column($sections, 'total'));

        // Summary: one row per section + the project total (matches the admin builder).
        $summaryRows = array_map(fn (array $s): array => [
            'label' => $s['title'], 'price' => $s['total'],
        ], $sections);
        $summaryRows[] = ['label' => 'Project total', 'price' => $scopeTotal, 'total' => true, 'red' => true];

        // Deposit / balance (or deposit / monthly) panels from the SAME derivation
        // the PDF mapper uses — fixed amount wins over pct, pct rounds to the ringgit.
        $plan = PaymentPlan::fromDocument(array_merge(['deposit_pct' => $depositPct], $planInputs), $scopeTotal);
        $panels = $plan->panels();

        $payload = array_filter([
            'project' => $project,
            'intro' => $intro,
            'subtitle' => $detailed['subtitle'] ?? null,
            'sections' => $sections,
            'summary' => ['rows' => $summaryRows],
            'panels' => $panels,
            'included' => self::buildIncluded($detailed['included'] ?? []),
            'options' => self::buildOptions($detailed['options'] ?? []),
            'care' => self::buildCare($detailed['care'] ?? []),
            // Deposit bullet derived from the proposal's own plan — the panels
            // above already show those figures, so the terms must agree.
            'paymentTerms' => ['items' => DocumentMapper::defaultTerms($plan)],
        ], fn ($v) => $v !== null && $v !== []);

        return [
            'document' => array_merge([
                'layout' => 'detailed',
                'deposit_pct' => $depositPct,
            ], $planInputs, [
                'payload' => $payload,
            ]),
            'total' => $scopeTotal,
        ];
    }

    /** @param  list<array<string, mixed>>  $groups */
    private static function buildIncluded(array $groups): array
    {
        return array_values(array_map(function (array $g): array {
            $out = [
                'items' => array_values(array_map('strval', $g['items'] ?? [])),
                'columns' => ((int) ($g['columns'] ?? 1)) === 2 ? 2 : 1,
            ];
            if (! empty($g['eyebrow'])) {
                $out['eyebrow'] = (string) $g['eyebrow'];
            }
            if (! empty($g['note'])) {
                $out['note'] = (string) $g['note'];
            }

            return $out;
        }, $groups));
    }

    /** @param  list<array<string, mixed>>  $cards */
    private static function buildOptions(array $cards): ?array
    {
        if ($cards === []) {
            return null;
        }

        $built = array_values(array_map(function (array $c): array {
            $out = [
                'badge' => (string) ($c['badge'] ?? 'OPTION'),
                'title' => (string) $c['title'],
                'price' => (float) $c['amount_myr'],
            ];
            if (! empty($c['recommended'])) {
                $out['accent'] = true;
            }
            if (! empty($c['sub'])) {
                $out['sub'] = (string) $c['sub'];
            }
            if (isset($c['was_myr']) && $c['was_myr'] !== '') {
                $out['priceWas'] = (float) $c['was_myr'];
            }
            if (! empty($c['price_note'])) {
                $out['priceNote'] = (string) $c['price_note'];
            }

            return $out;
        }, $cards));

        return ['title' => 'Package options', 'cards' => $built];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private static function buildCare(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $built = array_values(array_map(function (array $r): array {
            $out = [
                'label' => (string) $r['label'],
                'detail' => (string) ($r['detail'] ?? ''),
                'price' => (float) $r['amount_myr'],
            ];
            if (! empty($r['period'])) {
                $out['period'] = (string) $r['period'];
            }

            return $out;
        }, $rows));

        return ['title' => 'Care & support', 'rows' => $built];
    }
}
