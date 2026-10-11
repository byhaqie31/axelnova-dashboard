<?php

namespace App\Services\Quoting;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderCarePlan;
use App\Models\QuotationRevision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Care Plan coverage on an order (§3.5). There is no billing engine — care is
 * either inside the instalment figure (`included`) or a separate monthly
 * subscription billed by `care_plan` invoices (`separate`). This class keeps
 * the PERIODS straight so the invoice form can refuse a month already billed
 * or outside any period; CarePlanBoundary holds the month arithmetic.
 */
class CarePlans
{
    /**
     * Open the period a revision's plan calls for (none when it has no Care
     * Plan). Included care runs over the instalment months; separate care
     * starts the month after acceptance unless a boundary says otherwise.
     */
    public static function open(Order $order, ?QuotationRevision $revision, PaymentPlan $plan, ?CarbonImmutable $startsOn = null): ?OrderCarePlan
    {
        $kind = $plan->careKind();
        if ($kind === null) {
            return null;
        }

        if ($kind === OrderCarePlan::INCLUDED) {
            $first = $plan->firstInstalmentDate()->startOfMonth();

            return $order->carePlans()->create([
                'revision_id' => $revision?->id,
                'kind' => OrderCarePlan::INCLUDED,
                'monthly_myr' => null,
                'starts_on' => $startsOn && $startsOn->greaterThan($first) ? $startsOn : $first,
                'ends_on' => $plan->lastInstalmentDate()->endOfMonth()->startOfDay(),
            ]);
        }

        return $order->carePlans()->create([
            'revision_id' => $revision?->id,
            'kind' => OrderCarePlan::SEPARATE,
            'monthly_myr' => $plan->carePlanMonthly(),
            'starts_on' => $startsOn ?? CarbonImmutable::now()->startOfMonth()->addMonthNoOverflow(),
            'ends_on' => null,
        ]);
    }

    /**
     * Supersede (§3.5): end every active period on the supersede date — at the
     * last month already covered — and open the new revision's period (if it
     * has Care Plan) the month after. Returns the "care plan changed" flag, or
     * null when the arrangement is the same.
     *
     * @return array{from: ?string, to: ?string, from_monthly_myr: ?float, to_monthly_myr: ?float, on: string, ends_on: ?string, starts_on: ?string}|null
     */
    public static function supersede(Order $order, ?QuotationRevision $previous, QuotationRevision $revision, PaymentPlan $oldPlan, PaymentPlan $newPlan): ?array
    {
        $on = CarbonImmutable::now();

        $active = $order->carePlans()->whereNull('ended_at')->get();
        if ($active->isEmpty() && $oldPlan->careKind() !== null) {
            // An order accepted before periods existed: materialise its R1 period first.
            $active = collect([self::open($order, $previous, $oldPlan)]);
        }

        $oldStart = null;
        $covered = null;
        foreach ($active as $period) {
            $through = self::coveredThrough($order, $period, $oldPlan);
            $boundary = CarePlanBoundary::at($period->starts_on, $through, $on);
            $period->update([
                'ends_on' => $boundary['old_ends_on'],
                'ended_at' => $on,
                'end_reason' => "Superseded by {$revision->label()}",
            ]);
            $oldStart = $oldStart === null || $period->starts_on->lessThan($oldStart) ? $period->starts_on : $oldStart;
            $covered = $through !== null && ($covered === null || $through->greaterThan($covered)) ? $through : $covered;
        }

        $startsOn = CarePlanBoundary::at($oldStart, $covered, $on)['new_starts_on'];
        $opened = self::open($order, $revision, $newPlan, $startsOn);

        // Flag any change of arrangement; the same separate subscription at the
        // same price simply carries on (its period was still closed + reopened).
        $from = $active->first();
        $unchanged = $from?->kind === OrderCarePlan::SEPARATE && $opened?->kind === OrderCarePlan::SEPARATE
            && (float) $from->monthly_myr === (float) $opened->monthly_myr;
        if (($from === null && $opened === null) || $unchanged) {
            return null;
        }

        return [
            'from' => $from?->kind,
            'to' => $opened?->kind,
            'from_monthly_myr' => $from?->monthly_myr !== null ? (float) $from->monthly_myr : null,
            'to_monthly_myr' => $opened?->monthly_myr !== null ? (float) $opened->monthly_myr : null,
            'on' => $on->toDateString(),
            'ends_on' => $from?->fresh()->ends_on?->toDateString(),
            'starts_on' => $opened?->starts_on?->toDateString(),
        ];
    }

    /**
     * The last month a period's care has been billed-and-kept: the latest paid
     * instalment (included care) or the latest live care_plan invoice (separate
     * care). Null when nothing is covered yet.
     */
    public static function coveredThrough(Order $order, OrderCarePlan $period, PaymentPlan $plan): ?CarbonImmutable
    {
        if ($period->kind === OrderCarePlan::INCLUDED) {
            $numbers = Invoice::where('order_id', $order->id)
                ->where('type', 'instalment')
                ->where('status', 'paid')
                ->when($period->revision_id, fn ($q) => $q->where('revision_id', $period->revision_id))
                ->pluck('instalment_no');

            return $numbers
                ->map(fn ($n) => $plan->instalmentDate((int) $n)?->startOfMonth())
                ->filter()
                ->sort()
                ->last();
        }

        $month = Invoice::where('order_id', $order->id)
            ->where('type', 'care_plan')
            ->where('status', '!=', 'void')
            ->where('care_period', '>=', $period->starts_on->toDateString())
            ->max('care_period');

        return $month ? CarbonImmutable::parse($month)->startOfMonth() : null;
    }

    /**
     * Check a care_plan invoice for month $month (Y-m): it must fall inside a
     * SEPARATE period of the order and no other live care_plan invoice may bill
     * it. Returns the period (for its monthly figure) or the refusal message.
     */
    public static function billable(Order $order, string $month, ?Invoice $ignore = null): OrderCarePlan|string
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();

        /** @var Collection<int, OrderCarePlan> $periods */
        $periods = $order->carePlans()->where('kind', OrderCarePlan::SEPARATE)->get();
        $period = $periods->first(fn (OrderCarePlan $p) => $p->covers($start));
        if (! $period) {
            return $periods->isEmpty()
                ? 'This order has no separate Care Plan subscription to bill.'
                : 'No Care Plan period covers '.$start->format('F Y').' on this order.';
        }

        $taken = Invoice::where('order_id', $order->id)
            ->where('type', 'care_plan')
            ->where('status', '!=', 'void')
            ->whereDate('care_period', $start->toDateString())
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->first();

        return $taken
            ? 'Care Plan for '.$start->format('F Y')." is already billed on {$taken->invoice_number}. Void that invoice first to re-issue it."
            : $period;
    }
}
