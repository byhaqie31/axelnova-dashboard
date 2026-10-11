<?php

namespace App\Models;

use App\Services\Quoting\PaymentPlan;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'order_number',
        'quotation_id',
        'client_id',
        'value_min_myr',
        'value_max_myr',
        'final_amount_myr',
        'deposit_pct',
        'deposit_amount_myr',
        'payment_plan',
        'amount_paid_myr',
        'status',
        'started_at',
        'delivered_at',
        'completed_at',
        'due_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value_min_myr' => 'decimal:2',
            'value_max_myr' => 'decimal:2',
            'final_amount_myr' => 'decimal:2',
            'deposit_pct' => 'integer',
            'deposit_amount_myr' => 'decimal:2',
            'payment_plan' => 'array',
            'amount_paid_myr' => 'decimal:2',
            'started_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'due_at' => 'date',
        ];
    }

    /** Agreed total still owed — what "Pending" sums on the dashboard. */
    protected function remainingMyr(): Attribute
    {
        return Attribute::get(fn () => max(0, (float) $this->final_amount_myr - (float) $this->amount_paid_myr));
    }

    /**
     * Deposit due up front: the fixed amount carried from the quotation when it
     * agreed one, else the carried percentage rounded by the SAME rule the
     * quotation prints (PaymentPlan::pctDepositAmount — nearest ringgit).
     */
    protected function depositDueMyr(): Attribute
    {
        return Attribute::get(fn () => $this->deposit_amount_myr !== null
            ? round((float) $this->deposit_amount_myr, 2)
            : PaymentPlan::pctDepositAmount((float) $this->final_amount_myr, (int) ($this->deposit_pct ?? 0)));
    }

    /**
     * The instalment / partner plan inputs agreed on the quotation: the snapshot
     * taken on accept, else (orders accepted before the snapshot existed) the
     * accepted quotation's own plan. Null for a lump sum.
     *
     * @return array<string, mixed>|null
     */
    public function planInputs(): ?array
    {
        if (is_array($this->payment_plan) && $this->payment_plan !== []) {
            return $this->payment_plan;
        }

        return $this->quotation?->paymentPlan()->orderSnapshot();
    }

    /** True when the order is paid by deposit + a monthly schedule. */
    public function isScheduled(): bool
    {
        return $this->planInputs() !== null;
    }

    /**
     * The order's payment plan, rebuilt from its own figures (agreed total,
     * carried deposit) plus the plan inputs — what invoice labels, amounts and
     * due dates are derived from.
     */
    public function paymentPlan(): PaymentPlan
    {
        $deposit = $this->deposit_amount_myr !== null
            ? ['deposit_amount_myr' => (float) $this->deposit_amount_myr]
            : ['deposit_pct' => (int) ($this->deposit_pct ?? 0)];

        return PaymentPlan::fromDocument(
            array_merge($deposit, $this->planInputs() ?? []),
            (float) $this->final_amount_myr,
            $this->created_at,
        );
    }

    /**
     * The order-page / invoice-form view of a scheduled plan: figures, and every
     * instalment with the live (non-void) invoice issued for it. Null for a lump sum.
     *
     * @return array<string, mixed>|null
     */
    public function planView(): ?array
    {
        if (! $this->isScheduled()) {
            return null;
        }

        $plan = $this->paymentPlan();
        $invoices = $this->relationLoaded('invoices') ? $this->invoices : $this->invoices()->get();
        $live = $invoices->where('status', '!=', 'void');
        $byNo = $live->where('type', 'instalment')->keyBy('instalment_no');
        $deposit = $live->firstWhere('type', 'deposit');
        $ref = fn (?Invoice $i): ?array => $i ? ['id' => $i->id, 'number' => $i->invoice_number, 'status' => $i->status] : null;

        $schedule = array_map(fn (array $row): array => [
            'n' => $row['n'],
            'label' => $plan->instalmentLabel($row['n']),
            'date' => $row['date'],
            'amount' => $row['amount'],
            'invoice' => $ref($byNo->get($row['n'])),
        ], $plan->schedule());

        $next = collect($schedule)->first(fn (array $row) => $row['invoice'] === null);

        return [
            'plan' => $plan->plan(),
            'deposit_label' => $plan->upfrontLabel(),
            'deposit_myr' => $this->deposit_due_myr,
            'deposit_invoice' => $ref($deposit),
            'months' => $plan->months(),
            'monthly_myr' => $plan->instalmentAmount(),
            'billing_day' => $plan->billingDay(),
            'includes_care_plan' => $plan->includesCarePlan(),
            'first_date' => $plan->firstInstalmentDate()?->toDateString(),
            'last_date' => $plan->lastInstalmentDate()?->toDateString(),
            'plan_total_myr' => round((float) $this->deposit_due_myr + $plan->months() * $plan->instalmentAmount(), 2),
            'next_instalment_no' => $next['n'] ?? null,
            'schedule' => $schedule,
        ];
    }

    /** unpaid → deposit_paid → paid, derived from how much has landed. */
    protected function paymentStatus(): Attribute
    {
        return Attribute::get(function () {
            $paid = (float) $this->amount_paid_myr;
            $total = (float) $this->final_amount_myr;
            if ($paid <= 0) {
                return 'unpaid';
            }
            if ($total > 0 && $paid >= $total) {
                return 'paid';
            }

            return 'deposit_paid';
        });
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Issued invoices (deposit / partial / final), frozen snapshots. */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('issued_at');
    }

    /** Issued receipts (settled payments), frozen snapshots. */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->latest('issued_at');
    }

    /** The money ledger for this order — every movement, refunds included. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_at');
    }

    /** Legacy combined documents (pre invoices/receipts split). */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest('issued_at');
    }
}
