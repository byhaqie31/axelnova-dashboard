<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Quoting\DocumentIssuer;
use App\Services\Quoting\DocumentMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The Payments module — the money ledger. Every movement is a Payment row;
 * PaymentObserver keeps the order/invoice paid caches in sync. Refunds are
 * negative rows, never status flips.
 */
class PaymentsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::with(['order', 'client', 'invoice', 'refunds', 'receipt'])
            ->latest('paid_at')
            ->latest('id');

        foreach (['gateway', 'method', 'status', 'type'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('paid_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('paid_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('client', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return PaymentResource::collection($query->paginate(20));
    }

    public function show(Payment $payment): PaymentResource
    {
        $payment->load(['order', 'client', 'invoice', 'refunds', 'receipt', 'parent', 'recordedBy']);

        return new PaymentResource($payment);
    }

    /** Record a manual payment against an order. The observer recomputes caches. */
    public function store(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['nullable', 'integer', Rule::exists('invoices', 'id')->where('order_id', $order->id)],
            'amount_myr' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            // Required — every ledger row needs a traceable payment reference.
            'reference' => ['required', 'string', 'max:191'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = PaymentService::record($order, $data, $request->user()?->id);
        $payment->load(['order', 'client', 'invoice', 'refunds', 'receipt']);

        return response()->json([
            'message' => 'Payment recorded.',
            'payment' => new PaymentResource($payment),
        ], 201);
    }

    /** Refund (part of) a succeeded payment — a negative row against the original. */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        abort_unless(
            $payment->type === PaymentType::Payment && $payment->status === PaymentStatus::Succeeded,
            422,
            'Only a succeeded payment can be refunded.',
        );

        $refundable = PaymentService::refundableMyr($payment);
        abort_if($refundable <= 0, 422, 'This payment is already fully refunded.');

        $data = $request->validate([
            'amount_myr' => ['required', 'numeric', 'gt:0', "max:{$refundable}"],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $refund = PaymentService::refund($payment, (float) $data['amount_myr'], $data['notes'] ?? null, $request->user()?->id);
        $refund->load(['order', 'client', 'invoice', 'refunds', 'receipt']);

        return response()->json([
            'message' => 'Refund recorded.',
            'payment' => new PaymentResource($refund),
        ], 201);
    }

    /**
     * Move a payment's invoice allocation — link, re-link, or unlink (null).
     * Fixes the stranded-invoice case: a payment recorded without an invoice
     * leaves that invoice `issued` forever, since the observer only recomputes
     * the invoice a payment points at.
     */
    public function allocate(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->type === PaymentType::Payment, 422, 'Refunds follow their parent payment allocation.');
        abort_unless($payment->status === PaymentStatus::Succeeded, 422, 'Only a succeeded payment can be allocated.');

        $data = $request->validate([
            'invoice_id' => [
                'present', 'nullable', 'integer',
                Rule::exists('invoices', 'id')
                    ->where('order_id', $payment->order_id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $invoice = isset($data['invoice_id']) ? Invoice::find($data['invoice_id']) : null;
        abort_if($invoice && $invoice->status === 'void', 422, 'A void invoice cannot receive allocations.');

        if ($payment->invoice_id !== $invoice?->id) {
            PaymentService::allocate($payment, $invoice);
        }

        $payment->load(['order', 'client', 'invoice', 'refunds', 'receipt']);

        return response()->json([
            'message' => 'Allocation updated.',
            'payment' => new PaymentResource($payment),
        ]);
    }

    /** Preview the would-be (or issued) receipt for a payment — powers the live preview. */
    public function receiptPreview(Payment $payment): JsonResponse
    {
        $payment->loadMissing('order.quotation');

        $payload = DocumentMapper::forOrder($payment->order, 'receipt', [
            'number' => $payment->receipt()->value('receipt_number') ?? 'DRAFT',
            'issued' => now()->format('d F Y'),
            'amount' => (float) $payment->amount_myr,
            'paymentRef' => $payment->reference,
            'paymentMethod' => $payment->method->value,
        ]);

        return response()->json($payload);
    }

    /** Issue a receipt for a succeeded payment. Idempotent — returns the existing one. */
    public function issueReceipt(Payment $payment): JsonResponse
    {
        abort_unless($payment->status === PaymentStatus::Succeeded, 422, 'Only a succeeded payment can produce a receipt.');

        $receipt = $payment->receipt()->first() ?? DocumentIssuer::receiptForPayment($payment);

        return response()->json([
            'message' => 'Receipt ready.',
            'receipt' => [
                'id' => $receipt->id,
                'number' => $receipt->receipt_number,
                'pdf_path' => $receipt->pdf_path,
            ],
        ]);
    }
}
