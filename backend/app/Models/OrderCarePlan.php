<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Care Plan coverage period of an order (docs/global/QUOTATION-REVISIONS.md
 * §3.5). `included` = care paid inside the instalment / partner monthly figure
 * (its months are the instalment months); `separate` = its own monthly
 * subscription, billed one month at a time by `care_plan` invoices. A supersede
 * closes the active period at the last month already paid and opens the next
 * one the month after — so no month is billed twice and none is skipped.
 */
class OrderCarePlan extends Model
{
    public const INCLUDED = 'included';

    public const SEPARATE = 'separate';

    protected $fillable = [
        'order_id', 'revision_id', 'kind', 'monthly_myr', 'starts_on', 'ends_on', 'ended_at', 'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'monthly_myr' => 'decimal:2',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'ended_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'revision_id');
    }

    /** True when the month starting $month (a first-of-month date) lies inside this period. */
    public function covers(\DateTimeInterface $month): bool
    {
        $month = CarbonImmutable::instance($month)->startOfMonth();

        return $month->greaterThanOrEqualTo($this->starts_on)
            && ($this->ends_on === null || $month->lessThanOrEqualTo($this->ends_on));
    }
}
