<?php

namespace App\Models;

use App\Services\Quoting\PaymentPlan;
use App\Services\Quoting\ScheduleNetting;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Order extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'order_number',
        'quotation_id',
        'current_revision_id',
        'client_id',
        'value_min_myr',
        'value_max_myr',
        'final_amount_myr',
        'deposit_pct',
        'deposit_amount_myr',
        'payment_plan',
        'schedule_netting',
        'revision_flags',
        'amount_paid_myr',
        'status',
        'started_at',
        'delivered_at',
        'completed_at',
        'revised_at',
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
            'schedule_netting' => 'array',
            'revision_flags' => 'array',
            'amount_paid_myr' => 'decimal:2',
            'started_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'revised_at' => 'datetime',
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

    /** True when the order is paid by deposit + a monthly schedule (instalment / partner). */
    public function isScheduled(): bool
    {
        $inputs = $this->planInputs();

        return $inputs !== null && in_array($inputs['payment_plan'] ?? null, [PaymentPlan::INSTALMENT, PaymentPlan::PARTNER], true);
    }

    /** True when the order is a lump sum billed by phase (40/40/20, 50/50, …). */
    public function isPhased(): bool
    {
        return ! empty($this->planInputs()['phases'] ?? null);
    }

    /**
     * The netting of the CURRENT revision's schedule against what was paid when
     * it superseded the previous one (QuotationRevisions §4.3), or null when the
     * order never was revised (or the netting belongs to an older revision).
     *
     * @return array<string, mixed>|null
     */
    public function currentNetting(): ?array
    {
        $netting = $this->schedule_netting;

        return is_array($netting) && ($netting['revision_id'] ?? null) === $this->current_revision_id ? $netting : null;
    }

    /**
     * The netting entry for one schedule item (`deposit`, `balance`, `phase:n`,
     * `instalment:n`) — settled / adjustment / due — or null when not netted.
     *
     * @return array<string, mixed>|null
     */
    public function nettedItem(string $key): ?array
    {
        return collect($this->currentNetting()['items'] ?? [])->firstWhere('key', $key);
    }

    /**
     * Invoices that bill the CURRENT revision's terms — live (non-void) ones
     * tagged with it (untagged legacy rows count as current). A superseded
     * revision's paid instalment 1 must never read as the new schedule's #1.
     *
     * @return Collection<int, Invoice>
     */
    public function currentInvoices(): Collection
    {
        $invoices = $this->relationLoaded('invoices') ? $this->invoices : $this->invoices()->get();

        return $invoices->filter(fn (Invoice $i) => $i->status !== 'void'
            && ($i->revision_id === null || $this->current_revision_id === null || $i->revision_id === $this->current_revision_id));
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
     * The order-page / invoice-form view of the plan: figures, every instalment
     * or phase with the live invoice billing it (current revision only), and —
     * after a revise — the netting state of each item (settled by prior payment
     * / adjustment / due). Null for a plain lump sum that was never revised.
     *
     * @return array<string, mixed>|null
     */
    public function planView(): ?array
    {
        $netting = $this->currentNetting();
        if (! $this->isScheduled() && ! $this->isPhased() && $netting === null) {
            return null;
        }

        $plan = $this->paymentPlan();
        $live = $this->currentInvoices();
        $all = $this->relationLoaded('invoices') ? $this->invoices : $this->invoices()->get();
        $ref = fn (?Invoice $i): ?array => $i ? ['id' => $i->id, 'number' => $i->invoice_number, 'status' => $i->status] : null;
        $netted = function (string $key) use ($all, $ref): ?array {
            $item = $this->nettedItem($key);
            if ($item === null) {
                return null;
            }

            return [
                'state' => $item['state'],
                'covered_myr' => $item['covered_myr'],
                'due_myr' => $item['due_myr'],
                'invoice' => $ref(isset($item['invoice_id']) ? $all->firstWhere('id', $item['invoice_id']) : null),
            ];
        };
        $open = fn (?array $state, ?array $invoice): bool => $invoice === null
            && ! in_array($state['state'] ?? null, [ScheduleNetting::SETTLED, ScheduleNetting::ADJUSTMENT], true);

        $byNo = $live->where('type', 'instalment')->keyBy('instalment_no');
        $schedule = array_map(fn (array $row): array => [
            'n' => $row['n'],
            'label' => $plan->instalmentLabel($row['n']),
            'date' => $row['date'],
            'amount' => $row['amount'],
            'invoice' => $ref($byNo->get($row['n'])),
            'netting' => $netted("instalment:{$row['n']}"),
        ], $plan->isScheduled() ? $plan->schedule() : []);

        $byPhase = $live->where('type', 'phase')->keyBy('instalment_no');
        $phases = array_map(fn (array $row): array => array_merge($row, [
            'label' => $plan->phaseLabel($row['n']),
            'name' => $row['label'],
            'invoice' => $ref($byPhase->get($row['n'])),
            'netting' => $netted("phase:{$row['n']}"),
        ]), $plan->phaseRows());

        $nextOpen = fn (array $rows) => collect($rows)->first(fn (array $row) => $open($row['netting'], $row['invoice']))['n'] ?? null;

        return [
            'plan' => $plan->plan(),
            'phased' => $plan->isPhased(),
            'deposit_label' => $plan->upfrontLabel(),
            'deposit_myr' => $this->deposit_due_myr,
            'deposit_invoice' => $ref($live->firstWhere('type', 'deposit')),
            'deposit_netting' => $netted('deposit'),
            'balance_netting' => $netted('balance'),
            'months' => $plan->months(),
            'monthly_myr' => $plan->instalmentAmount(),
            'billing_day' => $plan->billingDay(),
            'includes_care_plan' => $plan->includesCarePlan(),
            'care_plan_monthly_myr' => $plan->carePlanMonthly(),
            'first_date' => $plan->firstInstalmentDate()?->toDateString(),
            'last_date' => $plan->lastInstalmentDate()?->toDateString(),
            'plan_total_myr' => $plan->isScheduled()
                ? round((float) $this->deposit_due_myr + $plan->months() * $plan->instalmentAmount(), 2)
                : (float) $this->final_amount_myr,
            'next_instalment_no' => $nextOpen($schedule),
            'next_phase_no' => $nextOpen($phases),
            'schedule' => $schedule,
            'phases' => $phases,
            'netting' => $netting === null ? null : [
                'from_revision' => $netting['from_revision'] ?? null,
                'revision' => $netting['revision'] ?? null,
                'netted_at' => $netting['netted_at'] ?? null,
                'paid_myr' => $netting['paid_myr'],
                'total_myr' => $netting['total_myr'],
                'overpaid_myr' => $netting['overpaid_myr'],
                'adjustment_myr' => $netting['adjustment_myr'],
                'voided_invoices' => $netting['voided_invoices'] ?? [],
                'items' => array_map(fn (array $item) => array_merge($item, [
                    'invoice' => $ref(isset($item['invoice_id']) ? $all->firstWhere('id', $item['invoice_id']) : null),
                ]), $netting['items']),
            ],
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

    /** The quotation revision whose terms this order currently runs on (R1, then R2 after a revise …). */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'current_revision_id');
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

    /** Care Plan coverage periods (QuotationRevisions §3.5), oldest first. */
    public function carePlans(): HasMany
    {
        return $this->hasMany(OrderCarePlan::class)->orderBy('starts_on')->orderBy('id');
    }

    /** Legacy combined documents (pre invoices/receipts split). */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest('issued_at');
    }
}
