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

    /** Bank / payment details shown on quotations, invoices, and receipts. */
    private const BANK = [
        'name' => 'OCBC Bank',
        'acct' => '7051415701',
        'holder' => 'Axel Nova Ventures',
        'online' => 'Card (credit & debit) and FPX online banking',
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
        // Prefer the stored expiry (set when the quote was sent) so the PDF's
        // "valid until" matches the lifecycle; fall back for self-serve/unsent rows.
        $validUntil = $quotation->expires_at
            ?? ($quotation->created_at ?? now())->copy()->addDays($validForDays);

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
                'online' => self::BANK['online'],
                'bank' => self::BANK['name'].' — '.self::BANK['holder'],
                'acct' => self::BANK['acct'],
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

        // Amount-based invoice/receipt: bill (or confirm) an explicit sum tied to
        // the order's agreed total — the standard path — instead of itemising the
        // quotation. Keeps the document's total linked to the order.
        if (isset($input['amount']) && (float) $input['amount'] > 0) {
            return self::amountDocument($order, $quotation, $doc, $type, $input);
        }

        $items = $quotation ? self::items($quotation, $doc) : [];

        $subtotal = array_sum(array_map(
            fn ($it) => (float) ($it['qty'] ?? 1) * (float) ($it['rate'] ?? 0),
            $items,
        ));
        // Quotation-level discount (legacy, rarely set) plus per-invoice discount and
        // promo applied at billing time off the agreed subtotal. Each can be a fixed
        // amount or a percentage; all three reduce the total.
        $baseDiscount = (float) ($doc['discount'] ?? 0);
        [$discountAmt, $discountLabel] = self::billingAdjustment(
            $input['discountType'] ?? null, $input['discountValue'] ?? null, $subtotal, $input['discountLabel'] ?? 'Discount',
        );
        [$promoAmt, $promoLabel] = self::billingAdjustment(
            $input['promoType'] ?? null, $input['promoValue'] ?? null, $subtotal, 'Promo', $input['promoCode'] ?? null,
        );
        $total = max($subtotal - $baseDiscount - $discountAmt - $promoAmt, 0);

        $amountPaid = isset($input['amountPaid']) ? (float) $input['amountPaid'] : null;
        $balance = $amountPaid !== null ? max($total - $amountPaid, 0) : $total;

        // Line items → summary rows, then subtotal, discounts/promo, and the total.
        $rows = array_map(fn ($it) => [
            'label' => (string) ($it['title'] ?? 'Item')
                .(! empty($it['desc']) ? " ({$it['desc']})" : ''),
            'price' => (float) ($it['qty'] ?? 1) * (float) ($it['rate'] ?? 0),
        ], $items);
        $rows[] = ['label' => 'Subtotal', 'price' => $subtotal];
        if ($baseDiscount > 0) {
            $rows[] = ['label' => 'Discount', 'price' => $baseDiscount, 'negative' => true];
        }
        if ($discountAmt > 0) {
            $rows[] = ['label' => $discountLabel, 'price' => $discountAmt, 'negative' => true];
        }
        if ($promoAmt > 0) {
            $rows[] = ['label' => $promoLabel, 'price' => $promoAmt, 'negative' => true];
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
                'note' => 'Payable to '.self::BANK['name'].' '.self::BANK['acct']
                    .' ('.self::BANK['holder'].'), or by card / FPX online banking.',
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
            'notes' => self::noteLines($input['notes'] ?? null),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Amount-based invoice / receipt: the document bills (or confirms) one explicit
     * figure, shown against the order's agreed total for context. This is what links
     * the invoice's `amount_total` to the order instead of the quotation line items.
     */
    private static function amountDocument(Order $order, ?Quotation $quotation, array $doc, string $type, array $input): array
    {
        $amount = round((float) $input['amount'], 2);
        $agreed = round((float) $order->final_amount_myr, 2);

        $rows = [];

        if ($type === 'receipt') {
            if ($agreed > 0 && abs($agreed - $amount) > 0.009) {
                $rows[] = ['label' => 'Agreed project total', 'price' => $agreed];
            }
            $rows[] = ['label' => 'Total paid', 'price' => $amount, 'total' => true, 'red' => true];
            $panels = [array_filter([
                'label' => 'Paid',
                'value' => $amount,
                'note' => self::paymentNote($input),
            ])];
            $status = $input['statusLabel'] ?? 'Payment received';
        } else {
            $labels = ['deposit' => 'Deposit', 'partial' => 'Partial payment', 'final' => 'Final balance'];
            $billLabel = $labels[$input['invoiceType'] ?? ''] ?? 'Amount';

            // Payment context from the order: the agreed total and the ledger-paid
            // cache frame this bill — deposit/partial show what remains after it,
            // final shows what's been paid and that this settles the balance.
            $paid = round((float) $order->amount_paid_myr, 2);

            if ($agreed > 0) {
                $rows[] = ['label' => 'Agreed project total', 'price' => $agreed];
            }
            if ($paid > 0) {
                $rows[] = ['label' => 'Paid to date', 'price' => $paid, 'negative' => true, 'green' => true];
            }

            // Discount + promo apply to the billed amount; each can be a fixed sum
            // or a percentage, and both reduce the total due.
            [$discountAmt, $discountLabel] = self::billingAdjustment(
                $input['discountType'] ?? null, $input['discountValue'] ?? null, $amount, $input['discountLabel'] ?? 'Discount',
            );
            [$promoAmt, $promoLabel] = self::billingAdjustment(
                $input['promoType'] ?? null, $input['promoValue'] ?? null, $amount, 'Promo', $input['promoCode'] ?? null,
            );
            $net = max($amount - $discountAmt - $promoAmt, 0);

            if ($discountAmt > 0 || $promoAmt > 0) {
                $rows[] = ['label' => $billLabel, 'price' => $amount];
                if ($discountAmt > 0) {
                    $rows[] = ['label' => $discountLabel, 'price' => $discountAmt, 'negative' => true];
                }
                if ($promoAmt > 0) {
                    $rows[] = ['label' => $promoLabel, 'price' => $promoAmt, 'negative' => true];
                }
                $rows[] = ['label' => 'Total due', 'price' => $net, 'total' => true, 'red' => true];
            } else {
                $rows[] = ['label' => "{$billLabel} due", 'price' => $net, 'total' => true, 'red' => true];
            }

            // What's still owed on the agreed total once this bill is settled.
            $remaining = $agreed > 0 ? round(max($agreed - $paid - $net, 0), 2) : 0.0;
            if ($remaining > 0.009) {
                $rows[] = ['label' => 'Remaining after this payment', 'price' => $remaining, 'priceMuted' => true];
            }

            $panels = [array_filter([
                'label' => 'Amount due',
                'value' => $net,
                'accent' => true,
                'note' => 'Payable to '.self::BANK['name'].' '.self::BANK['acct']
                    .' ('.self::BANK['holder'].'), or by card / FPX online banking.',
            ])];
            if ($remaining > 0.009) {
                $panels[] = [
                    'label' => 'Balance after this payment',
                    'value' => $remaining,
                    'note' => 'Remaining on the agreed project total.',
                ];
            }
            $status = $input['statusLabel'] ?? "{$billLabel} invoice";
        }

        return array_filter([
            'layout' => $input['layout'] ?? 'detailed',
            'kind' => $type,
            'number' => $input['number'] ?? null,
            'issued' => $input['issued'] ?? now()->format('d F Y'),
            'status' => $status,
            'currency' => 'RM',
            'studio' => self::STUDIO,
            'client' => array_filter([
                'name' => $quotation?->name ?: $quotation?->company ?: 'Client',
                'attn' => $doc['client']['attn'] ?? null,
                'address' => $doc['client']['address'] ?? null,
                'email' => $quotation?->email,
            ]),
            'project' => $doc['project'] ?? ($quotation ? self::defaultProject($quotation) : 'Project'),
            'subtitle' => $doc['subtitle'] ?? ($quotation?->reference_code ? "Ref {$quotation->reference_code}" : null),
            'summary' => ['rows' => $rows],
            'panels' => $panels,
            'notes' => self::noteLines($input['notes'] ?? null),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Notes arrive from the issue form as free text, but the PDF contract
     * (types.ts `NoteLine[]`) wants {label, text} rows — wrap the string.
     * Already-shaped arrays (builder payloads) pass through untouched.
     */
    private static function noteLines(mixed $notes): ?array
    {
        if (is_array($notes)) {
            return $notes ?: null;
        }

        $text = trim((string) $notes);

        return $text === '' ? null : [['label' => '', 'text' => $text]];
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
     * Resolve a billing-time discount or promo into a [amount, label] pair. A
     * 'percent' value is applied to the agreed subtotal (capped at 100%); 'amount'
     * is taken as-is. Returns [0, null] when there's nothing to apply.
     *
     * @return array{0: float, 1: ?string}
     */
    private static function billingAdjustment(?string $type, mixed $value, float $base, string $fallbackLabel, ?string $code = null): array
    {
        $v = (float) ($value ?? 0);
        if ($v <= 0) {
            return [0.0, null];
        }

        $amount = $type === 'percent' ? round($base * min($v, 100) / 100, 2) : $v;
        $label = $code ?: $fallbackLabel;
        if ($type === 'percent') {
            $label .= ' ('.rtrim(rtrim(number_format($v, 2), '0'), '.').'%)';
        }

        return [$amount, $label];
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

        // Fallback: breakdown tuples [label, min, max] → one line each at the upper
        // figure. flatBreakdown() flattens the grouped-per-package shape (and legacy
        // flat rows) to the same tuple list this loop has always consumed.
        $breakdown = $quotation->flatBreakdown();
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
                // Resolve the catalog name from the DB; never show the raw slug.
                'title' => $quotation->package_key
                    ? PricingEngine::active()->packageName($quotation->package_key)
                    : 'Project',
                'qty' => 1,
                'rate' => (float) $quotation->estimate_max_myr,
            ];
        }

        return $items;
    }
}
