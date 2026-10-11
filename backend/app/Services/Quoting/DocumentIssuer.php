<?php

namespace App\Services\Quoting;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues invoices and receipts for an order: mints an atomic AXN-family number
 * (AXNI / AXNR via ReferenceCodeGenerator, each its own yearly counter), freezes
 * the DocumentData snapshot, and persists it. The PDF is never stored — it
 * renders on demand from the frozen `payload`.
 */
class DocumentIssuer
{
    /**
     * Issue-form fields kept on the invoice for re-editing. Editing merges new
     * values over these and re-runs DocumentMapper — the payload is always a
     * pure function of (order, inputs, number, issued).
     */
    /**
     * Display-only options (see DocumentMapper::amountDocument) — they change how
     * the invoice looks, never what it bills, so they stay editable once amounts
     * lock. Part of INPUT_KEYS: without that cleanInputs() would drop them and the
     * next edit would revert the invoice to the defaults.
     */
    public const DISPLAY_KEYS = [
        'showSummary', 'showRemaining', 'billingTitle', 'billingLabel', 'billingText',
        'scopeTitle', 'scopeItems',
    ];

    /** Validation for DISPLAY_KEYS — shared by every endpoint that takes invoice inputs. */
    public const DISPLAY_RULES = [
        'showSummary' => ['nullable', 'boolean'],
        'showRemaining' => ['nullable', 'boolean'],
        'billingTitle' => ['nullable', 'string', 'max:80'],
        'billingLabel' => ['nullable', 'string', 'max:40'],
        'billingText' => ['nullable', 'string', 'max:220'],
        'scopeTitle' => ['nullable', 'string', 'max:60'],
        'scopeItems' => ['nullable', 'array', 'max:8'],
        'scopeItems.*' => ['nullable', 'string', 'max:120'],
    ];

    /**
     * Invoice type + instalment number — shared by issue, preview and update.
     * `instalment` bills one numbered payment of the order's instalment /
     * partner plan; see assertPlanFits() for the plan-aware rules.
     */
    public const TYPE_RULES = [
        'invoiceType' => ['nullable', 'in:deposit,partial,final,instalment,phase,care_plan,adjustment'],
        'instalmentNo' => ['nullable', 'integer', 'min:1', 'max:120'],
        // care_plan: the month it bills (Y-m); adjustment: its printed label.
        'carePeriod' => ['nullable', 'date_format:Y-m'],
        'adjustmentLabel' => ['nullable', 'string', 'max:120'],
    ];

    /** Types numbered by instalment_no: a monthly instalment, or a phase of a phased lump sum. */
    private const NUMBERED_TYPES = ['instalment', 'phase'];

    private const INPUT_KEYS = [
        'invoiceType', 'instalmentNo', 'carePeriod', 'adjustmentLabel', 'amount', 'amountPaid', 'paymentRef', 'paymentMethod',
        'discountType', 'discountValue', 'discountLabel',
        'promoCode', 'promoType', 'promoValue', 'notes', 'dueAt',
        ...self::DISPLAY_KEYS,
    ];

    /**
     * Issue an invoice (deposit / partial / final). A recorded payment accrues
     * onto the order's running paid total and stamps `paid_at` when fully paid.
     *
     * @param  array  $input  invoiceType, amountPaid, paymentRef, paymentMethod,
     *                        status, issued, payload override.
     */
    public static function issueInvoice(Order $order, array $input = []): Invoice
    {
        return DB::transaction(function () use ($order, $input) {
            $input = self::normaliseInstalment($input);
            $number = ReferenceCodeGenerator::generate(DocumentType::Invoice);

            $payload = DocumentMapper::forOrder($order, 'invoice', array_merge($input, [
                'number' => $number,
                'issued' => $input['issued'] ?? now()->format('d F Y'),
            ]));

            $status = $input['status'] ?? 'issued';

            $invoice = Invoice::create([
                'order_id' => $order->id,
                // The revision whose terms this bills — a supersede voids the
                // unpaid ones and nets the new schedule against the paid ones.
                'revision_id' => $order->current_revision_id,
                'invoice_number' => $number,
                'public_token' => Str::random(48),
                'type' => $input['invoiceType'] ?? 'deposit',
                'instalment_no' => $input['instalmentNo'] ?? null,
                'care_period' => self::carePeriodDate($input),
                'payload' => $payload,
                'inputs' => self::cleanInputs($input),
                'amount_total' => self::payloadTotal($payload),
                'amount_paid' => null,
                'payment_ref' => $input['paymentRef'] ?? null,
                'payment_method' => $input['paymentMethod'] ?? null,
                'status' => $status,
                'issued_at' => now(),
                // An instalment is due on its scheduled date; anything else 14 days out.
                'due_at' => $input['dueAt'] ?? self::scheduledDue($order, $input) ?? now()->addDays(14)->toDateString(),
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            return $invoice;
        });
    }

    /**
     * Re-edit an issued invoice in place: merge the new form fields over the
     * stored issue inputs, re-run DocumentMapper, and re-freeze the payload —
     * same AXNI number, same public token, same issued date. The caller is
     * responsible for the amounts-locked / void guards.
     *
     * Keys PRESENT in $input override the stored value (a present null clears
     * it, e.g. removing a discount); absent keys keep the stored value.
     */
    public static function updateInvoice(Invoice $invoice, array $input): Invoice
    {
        return DB::transaction(function () use ($invoice, $input) {
            $invoice->loadMissing('order.quotation');

            $inputs = self::normaliseInstalment(array_replace(
                self::effectiveInputs($invoice),
                Arr::only($input, self::INPUT_KEYS),
            ));

            $payload = DocumentMapper::forOrder($invoice->order, 'invoice', array_merge($inputs, [
                'number' => $invoice->invoice_number,
                // Keep the frozen issue date — editing is a correction, not a re-issue.
                'issued' => $invoice->payload['issued'] ?? $invoice->issued_at?->format('d F Y'),
            ]));

            $invoice->update([
                'payload' => $payload,
                'inputs' => self::cleanInputs($inputs),
                'amount_total' => self::payloadTotal($payload),
                'type' => $inputs['invoiceType'] ?? $invoice->type,
                'instalment_no' => $inputs['instalmentNo'] ?? null,
                'care_period' => self::carePeriodDate($inputs),
                'due_at' => $inputs['dueAt'] ?? $invoice->due_at,
            ]);

            return $invoice->refresh();
        });
    }

    /**
     * Plan-aware checks for an invoice's type (issue, preview, update). Throws a
     * 422 when the bill doesn't fit the order's CURRENT revision:
     *   • instalment — an instalment / partner order, a number inside its
     *     schedule, not already billed on a live invoice of this revision;
     *   • phase — a phased lump-sum order, a phase inside it, not already billed;
     *   • deposit — not on a phased order (bill Phase 1);
     *   • care_plan — a month inside a separate Care Plan period, not billed yet;
     *   • after a revise, nothing the netting already settled or adjusted.
     * $checkTaken = false (preview) skips the "already billed" checks only.
     */
    public static function assertPlanFits(Order $order, array $input, ?Invoice $ignore = null, bool $checkTaken = true): void
    {
        $type = $input['invoiceType'] ?? null;
        $n = (int) ($input['instalmentNo'] ?? 0);

        if ($type === 'deposit' && $order->isPhased()) {
            throw ValidationException::withMessages([
                'invoiceType' => 'This order is billed by phase — issue Phase 1 instead of a deposit.',
            ]);
        }

        if ($type === 'care_plan') {
            if (empty($input['carePeriod'])) {
                throw ValidationException::withMessages(['carePeriod' => 'Pick the month this Care Plan invoice bills.']);
            }
            $billable = CarePlans::billable($order, (string) $input['carePeriod'], $ignore);
            if (is_string($billable) && ($checkTaken || ! str_contains($billable, 'already billed'))) {
                throw ValidationException::withMessages(['carePeriod' => $billable]);
            }

            return;
        }

        if ($type === 'instalment') {
            if (! $order->isScheduled()) {
                throw ValidationException::withMessages([
                    'invoiceType' => 'This order has no instalment or partner plan — use a deposit, partial or final invoice.',
                ]);
            }
            $months = $order->paymentPlan()->months();
            if ($n < 1 || $n > $months) {
                throw ValidationException::withMessages([
                    'instalmentNo' => "Pick which instalment this invoice bills (1–{$months}).",
                ]);
            }
        }

        if ($type === 'phase') {
            if (! $order->isPhased()) {
                throw ValidationException::withMessages([
                    'invoiceType' => 'This order is not billed by phase — use a deposit, partial or final invoice.',
                ]);
            }
            $count = count($order->paymentPlan()->phases());
            if ($n < 1 || $n > $count) {
                throw ValidationException::withMessages([
                    'instalmentNo' => "Pick which phase this invoice bills (1–{$count}).",
                ]);
            }
        }

        // After a revise: an item the netting settled (or billed by its adjustment
        // invoice) is never billed again.
        $key = match ($type) {
            'deposit' => 'deposit',
            'instalment' => "instalment:{$n}",
            'phase' => "phase:{$n}",
            default => null,
        };
        $netted = $key ? $order->nettedItem($key) : null;
        if ($checkTaken && in_array($netted['state'] ?? null, [ScheduleNetting::SETTLED, ScheduleNetting::ADJUSTMENT], true)) {
            throw ValidationException::withMessages([
                in_array($type, self::NUMBERED_TYPES, true) ? 'instalmentNo' : 'invoiceType' => $netted['state'] === ScheduleNetting::SETTLED
                    ? "{$netted['label']} was settled by payments made before the revision — nothing left to bill."
                    : "{$netted['label']} is billed by the revision's adjustment invoice — nothing left to bill.",
            ]);
        }

        if ($checkTaken && in_array($type, self::NUMBERED_TYPES, true)) {
            // Live invoices of the CURRENT revision only — a superseded revision's
            // instalment 1 is not the new schedule's instalment 1.
            $taken = Invoice::where('order_id', $order->id)
                ->where('type', $type)
                ->where('instalment_no', $n)
                ->where('status', '!=', 'void')
                ->when($order->current_revision_id, fn ($q) => $q->where(fn ($q) => $q
                    ->where('revision_id', $order->current_revision_id)->orWhereNull('revision_id')))
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->first();
            if ($taken) {
                $what = $type === 'phase' ? 'Phase' : 'Instalment';
                throw ValidationException::withMessages([
                    'instalmentNo' => "{$what} {$n} is already billed on {$taken->invoice_number}. Void that invoice first to re-issue it.",
                ]);
            }
        }
    }

    /** Only a numbered invoice (instalment / phase) carries a number; only care_plan a month. */
    private static function normaliseInstalment(array $input): array
    {
        if (! in_array($input['invoiceType'] ?? null, self::NUMBERED_TYPES, true)) {
            $input['instalmentNo'] = null;
        } elseif (isset($input['instalmentNo'])) {
            $input['instalmentNo'] = (int) $input['instalmentNo'];
        }
        if (($input['invoiceType'] ?? null) !== 'care_plan') {
            $input['carePeriod'] = null;
        }

        return $input;
    }

    /** The care_period date column for a care_plan invoice ("2026-11" → 2026-11-01). */
    private static function carePeriodDate(array $input): ?string
    {
        return ($input['invoiceType'] ?? null) === 'care_plan' && ! empty($input['carePeriod'])
            ? "{$input['carePeriod']}-01"
            : null;
    }

    /** The scheduled due date of the instalment an invoice bills, if any. */
    private static function scheduledDue(Order $order, array $input): ?string
    {
        if (($input['invoiceType'] ?? null) !== 'instalment' || empty($input['instalmentNo']) || ! $order->isScheduled()) {
            return null;
        }

        return $order->paymentPlan()->instalmentDate((int) $input['instalmentNo'])?->toDateString();
    }

    /**
     * The issue-form fields to pre-fill the edit form with — stored inputs, or
     * the legacy fallback for invoices issued before `inputs` existed.
     */
    public static function effectiveInputs(Invoice $invoice): array
    {
        return $invoice->inputs ?? self::legacyInputs($invoice);
    }

    /**
     * Best-effort inputs for invoices issued before `inputs` existed: the net
     * total as the billed amount (discounts were already applied into it) and
     * the payload notes flattened back to text. Good enough to re-edit — the
     * live preview shows the regenerated document before anything is saved.
     */
    private static function legacyInputs(Invoice $invoice): array
    {
        $notes = $invoice->payload['notes'] ?? null;
        if (is_array($notes)) {
            $notes = implode("\n", array_map(
                fn ($n) => trim(($n['label'] ?? '').' '.($n['text'] ?? '')),
                $notes,
            ));
        }

        return array_filter([
            'invoiceType' => $invoice->type,
            'instalmentNo' => $invoice->instalment_no,
            'amount' => (float) $invoice->amount_total,
            'notes' => is_string($notes) && trim($notes) !== '' ? $notes : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Whitelist + drop empties — what gets persisted to `invoices.inputs`.
     * Only null and '' count as empty: `false` (a hidden section) is kept.
     */
    private static function cleanInputs(array $input): array
    {
        return array_filter(
            Arr::only($input, self::INPUT_KEYS),
            fn ($v) => $v !== null && $v !== '',
        );
    }

    /**
     * Issue a receipt confirming a settled payment, optionally tied to the
     * invoice it settles.
     *
     * @param  array  $input  invoice_id, amountPaid, paymentRef, paymentMethod,
     *                        issued, payload override.
     */
    public static function issueReceipt(Order $order, array $input = []): Receipt
    {
        return DB::transaction(function () use ($order, $input) {
            $number = ReferenceCodeGenerator::generate(DocumentType::Receipt);

            $payload = DocumentMapper::forOrder($order, 'receipt', array_merge($input, [
                'number' => $number,
                'issued' => $input['issued'] ?? now()->format('d F Y'),
            ]));

            return Receipt::create([
                'order_id' => $order->id,
                'invoice_id' => $input['invoice_id'] ?? null,
                'receipt_number' => $number,
                'public_token' => Str::random(48),
                'payload' => $payload,
                'amount' => isset($input['amountPaid']) ? (float) $input['amountPaid'] : self::payloadTotal($payload),
                'payment_ref' => $input['paymentRef'] ?? null,
                'payment_method' => $input['paymentMethod'] ?? null,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        });
    }

    /**
     * Issue a receipt for a succeeded payment — the ledger-anchored path. Works
     * even when the payment has no invoice (a deposit paid before any invoice was
     * issued still gets a receipt). The frozen snapshot is built from the
     * payment → its invoice (if any) → order, with amount / ref / method taken
     * from the payment itself.
     */
    public static function receiptForPayment(Payment $payment): Receipt
    {
        return DB::transaction(function () use ($payment) {
            $payment->loadMissing('order.quotation');
            $order = $payment->order;
            $number = ReferenceCodeGenerator::generate(DocumentType::Receipt);

            $payload = DocumentMapper::forOrder($order, 'receipt', [
                'number' => $number,
                'issued' => now()->format('d F Y'),
                'amount' => (float) $payment->amount_myr,
                'paymentRef' => $payment->reference,
                'paymentMethod' => $payment->method->value,
            ]);

            return Receipt::create([
                'order_id' => $order->id,
                'invoice_id' => $payment->invoice_id,
                'payment_id' => $payment->id,
                'receipt_number' => $number,
                'public_token' => Str::random(48),
                'payload' => $payload,
                'amount' => (float) $payment->amount_myr,
                'payment_ref' => $payment->reference,
                'payment_method' => $payment->method->value,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        });
    }

    /** Total = the document's "total/red" summary row, falling back to subtotal. */
    private static function payloadTotal(array $payload): float
    {
        $rows = $payload['summary']['rows'] ?? [];
        foreach ($rows as $row) {
            if (! empty($row['total'])) {
                return (float) ($row['price'] ?? 0);
            }
        }

        return (float) ($rows[0]['price'] ?? 0);
    }
}
