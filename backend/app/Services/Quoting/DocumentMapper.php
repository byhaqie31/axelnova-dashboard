<?php

namespace App\Services\Quoting;

use App\Models\Quotation;

/**
 * Maps a Quotation row to the `DocumentData` shape consumed by the Axel Nova PDF
 * module (axelnova-pdf — see frontend/server/utils/pdf/types.ts). The frontend
 * Nitro route fetches this JSON and renders it; this class is the single source
 * of truth for what a quotation looks like as a document.
 */
class DocumentMapper
{
    /** Studio details on every document. */
    private const STUDIO = [
        'name' => 'Axel Nova Ventures',
        'tagline' => 'Design & Engineering Studio',
        'reg' => 'Reg. 202603119899 (CA0420977-U)',
        'email' => 'baihaqie@axelnova.tech',
        'site' => 'axelnovaventures.com',
        'location' => 'Kuala Lumpur, Malaysia',
    ];

    private const DEFAULT_TERMS = [
        '50% deposit to commence; balance due on delivery before handover.',
        'Revisions are included as scoped per phase; further rounds are quoted separately.',
        'Third-party costs (domains, fonts, hosting) are billed at cost where applicable.',
    ];

    public static function toDocumentData(Quotation $quotation): array
    {
        $doc = $quotation->document ?? [];
        $validForDays = (int) ($quotation->pricingConfig?->config['valid_for_days'] ?? 30);

        $issuedAt = $quotation->sent_at ?? $quotation->created_at ?? now();
        $validUntil = ($quotation->created_at ?? now())->copy()->addDays($validForDays);

        $terms = ! empty($doc['terms']) && is_array($doc['terms'])
            ? array_values(array_filter($doc['terms']))
            : self::DEFAULT_TERMS;

        return [
            'kind' => 'quotation',
            'number' => $quotation->reference_code,
            'issued' => $issuedAt->format('d F Y'),
            'validUntil' => $validUntil->format('d F Y'),
            'currency' => 'RM',
            'studio' => self::STUDIO,
            'client' => array_filter([
                'name' => $quotation->name ?: $quotation->company ?: 'Client',
                'attn' => $doc['client']['attn'] ?? null,
                'address' => $doc['client']['address'] ?? null,
                'email' => $quotation->email,
            ]),
            'project' => $doc['project'] ?? self::defaultProject($quotation),
            'intro' => $doc['intro'] ?? null,
            'items' => self::items($quotation, $doc),
            'discount' => (float) ($doc['discount'] ?? 0),
            'taxLabel' => $doc['tax_label'] ?? 'SST',
            'taxRate' => (float) ($doc['tax_rate'] ?? 0),
            'depositPct' => (int) ($doc['deposit_pct'] ?? 50),
            'terms' => $terms,
            'pay' => [
                'online' => 'Card & FPX online banking via secure link',
            ],
        ];
    }

    private static function defaultProject(Quotation $quotation): string
    {
        return $quotation->company
            ? "{$quotation->company} — project quotation"
            : 'Project quotation';
    }

    /**
     * Prefer admin-authored line items; otherwise derive presentable items from
     * the priced breakdown stored on the quotation (legacy / un-edited rows).
     */
    private static function items(Quotation $quotation, array $doc): array
    {
        if (! empty($doc['items']) && is_array($doc['items'])) {
            return array_values(array_map(fn ($it) => [
                'title' => (string) ($it['title'] ?? 'Item'),
                'desc' => $it['desc'] ?? null,
                'qty' => (float) ($it['qty'] ?? 1),
                'unit' => $it['unit'] ?? null,
                'rate' => (float) ($it['rate'] ?? 0),
            ], $doc['items']));
        }

        // Fallback: breakdown tuples [label, min, max] → one line each at the upper figure.
        $breakdown = $quotation->form_payload['breakdown'] ?? [];
        $items = [];
        foreach ($breakdown as $line) {
            $rate = (float) ($line[2] ?? 0);
            if ($rate <= 0) {
                continue;
            }
            $items[] = [
                'title' => (string) ($line[0] ?? 'Item'),
                'qty' => 1,
                'rate' => $rate,
            ];
        }

        if (empty($items)) {
            $items[] = [
                'title' => $quotation->package_key ?: 'Project',
                'qty' => 1,
                'rate' => (float) $quotation->estimate_max_myr,
            ];
        }

        return $items;
    }
}
