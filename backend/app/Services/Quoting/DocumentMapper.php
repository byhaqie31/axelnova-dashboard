<?php

namespace App\Services\Quoting;

use App\Models\Order;
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
        'tagline' => 'Simple, effortless, human.',
        'reg' => 'Reg. 202603119899 (CA0420977-U)',
        'email' => 'baihaqie@axelnova.tech',
        'site' => 'axelnovaventures.com',
        'designedBy' => 'Designed by Qie / Axel Nova Ventures',
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

        // Detailed / customized layout — the builder authors the full presentation
        // content (sections, included, options, care, summary, panels, …) under
        // document.payload. Pass it straight through (same override pattern as
        // forOrder), stamping only the server-controlled identity fields.
        if (($doc['layout'] ?? 'standard') === 'detailed') {
            $payload = is_array($doc['payload'] ?? null) ? $doc['payload'] : [];
            $payloadClient = is_array($payload['client'] ?? null) ? $payload['client'] : [];

            return array_filter(array_merge($payload, [
                'layout' => 'detailed',
                'kind' => 'quotation',
                'number' => $quotation->reference_code,
                'issued' => $issuedAt->format('d F Y'),
                'validUntil' => $validUntil->format('d F Y'),
                'currency' => 'RM',
                'studio' => array_merge(self::STUDIO, array_filter([
                    'logo' => config('services.studio.logo_url') ?: null,
                ])),
                'client' => array_filter([
                    'name' => $quotation->name ?: $quotation->company ?: 'Client',
                    'attn' => $payloadClient['attn'] ?? null,
                    'address' => $payloadClient['address'] ?? null,
                    'email' => $quotation->email,
                ]),
                'project' => $payload['project'] ?? self::defaultProject($quotation),
            ]), fn ($v) => $v !== null && $v !== []);
        }

        return [
            // "standard" = the simple parties → scope-table format, good for
            // non-customized projects. The detailed/customized layout is built
            // from the customized quotation builder with richer data.
            'layout' => $doc['layout'] ?? 'standard',
            'kind' => 'quotation',
            'number' => $quotation->reference_code,
            'issued' => $issuedAt->format('d F Y'),
            'validUntil' => $validUntil->format('d F Y'),
            'currency' => 'RM',
            'studio' => array_merge(self::STUDIO, array_filter([
                // URL or base64 data URI; null/blank falls back to the bundled mark.
                'logo' => config('services.studio.logo_url') ?: null,
            ])),
            'client' => array_filter([
                'name' => $quotation->name ?: $quotation->company ?: 'Client',
                'attn' => $doc['client']['attn'] ?? null,
                'address' => $doc['client']['address'] ?? null,
                'email' => $quotation->email,
            ]),
            'project' => $doc['project'] ?? self::defaultProject($quotation),
            'subtitle' => $doc['subtitle'] ?? null,
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

    /**
     * Build invoice/receipt DocumentData from an order. Derived from the order's
     * quotation (line items → summary) plus admin-supplied payment details. The
     * caller (DocumentIssuer) freezes the returned array as the document payload.
     *
     * `$input` keys: number, issued, layout, amountPaid, paymentRef,
     * paymentMethod, statusLabel, notes, payload (full override).
     */
    public static function forOrder(Order $order, string $type, array $input = []): array
    {
        // Full override from a customized builder — stamp number/issued and use as-is.
        if (! empty($input['payload']) && is_array($input['payload'])) {
            return array_merge($input['payload'], array_filter([
                'kind' => $type,
                'number' => $input['number'] ?? null,
                'issued' => $input['issued'] ?? null,
            ]));
        }

        $quotation = $order->quotation;
        $doc = $quotation?->document ?? [];
        $items = $quotation ? self::items($quotation, $doc) : [];

        $subtotal = array_sum(array_map(
            fn ($it) => (float) ($it['qty'] ?? 1) * (float) ($it['rate'] ?? 0),
            $items,
        ));
        $discount = (float) ($doc['discount'] ?? 0);
        $total = $subtotal - $discount;

        $amountPaid = isset($input['amountPaid']) ? (float) $input['amountPaid'] : null;
        $balance = $amountPaid !== null ? max($total - $amountPaid, 0) : $total;

        // Line items → summary rows, then subtotal / discount / total.
        $rows = array_map(fn ($it) => [
            'label' => (string) ($it['title'] ?? 'Item')
                . (! empty($it['desc']) ? " ({$it['desc']})" : ''),
            'price' => (float) ($it['qty'] ?? 1) * (float) ($it['rate'] ?? 0),
        ], $items);
        $rows[] = ['label' => 'Subtotal', 'price' => $subtotal];
        if ($discount > 0) {
            $rows[] = ['label' => 'Discount', 'price' => $discount, 'negative' => true];
        }
        $rows[] = ['label' => $type === 'receipt' ? 'Total paid' : 'Total due',
            'price' => $total, 'total' => true, 'red' => true];

        // Payment panels.
        $panels = [];
        if ($type === 'receipt') {
            $panels[] = array_filter([
                'label' => 'Paid in full',
                'value' => $amountPaid ?? $total,
                'note' => self::paymentNote($input),
            ]);
        } else {
            if ($amountPaid !== null && $amountPaid > 0) {
                $panels[] = array_filter([
                    'label' => 'Deposit received',
                    'value' => $amountPaid,
                    'note' => self::paymentNote($input),
                ]);
            }
            $panels[] = array_filter([
                'label' => 'Balance due on completion',
                'value' => $balance,
                'accent' => true,
                'note' => 'Payable by card, online banking (FPX), bank transfer, or DuitNow QR to '
                    . self::STUDIO['name'] . '.',
            ]);
        }

        return array_filter([
            'layout' => $input['layout'] ?? 'detailed',
            'kind' => $type,
            'number' => $input['number'] ?? null,
            'issued' => $input['issued'] ?? now()->format('d F Y'),
            'status' => $input['statusLabel']
                ?? ($type === 'receipt' ? 'Paid in full'
                    : ($amountPaid ? 'Deposit received' : 'Issued')),
            'currency' => 'RM',
            'studio' => self::STUDIO,
            'client' => array_filter([
                'name' => $quotation?->name ?: $quotation?->company ?: 'Client',
                'attn' => $doc['client']['attn'] ?? null,
                'address' => $doc['client']['address'] ?? null,
                'email' => $quotation?->email,
            ]),
            'project' => $doc['project'] ?? ($quotation ? self::defaultProject($quotation) : 'Project'),
            'subtitle' => $doc['subtitle']
                ?? ($quotation?->reference_code ? "Ref {$quotation->reference_code}" : null),
            'summary' => ['rows' => $rows],
            'panels' => $panels,
            'notes' => $input['notes'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    private static function paymentNote(array $input): ?string
    {
        $bits = array_filter([
            $input['paymentMethod'] ?? null,
            ! empty($input['paymentRef']) ? "Ref {$input['paymentRef']}" : null,
        ]);

        return $bits ? implode("\n", $bits) : null;
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
