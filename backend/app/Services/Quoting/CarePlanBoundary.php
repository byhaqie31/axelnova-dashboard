<?php

namespace App\Services\Quoting;

use Carbon\CarbonImmutable;

/**
 * Where a Care Plan period ends and the next begins when a revision supersedes
 * another (§3.5). Pure — no I/O. Coverage is counted in whole months:
 *
 *   • the old period ends at the end of the LAST MONTH ALREADY BILLED-AND-KEPT
 *     under it (paid instalments for `included` care, live care_plan invoices
 *     for `separate` care); nothing covered → it closes empty (ends the day
 *     before it started);
 *   • the new period starts the month right after that — or, when nothing was
 *     covered yet, where the old one was due to start (its months were never
 *     billed, so they are the new plan's to bill); with no old period at all,
 *     the month after the supersede date.
 *
 * So old.ends_on + 1 day == new.starts_on whenever both exist: never a month
 * billed twice, never one skipped.
 */
final class CarePlanBoundary
{
    /**
     * @return array{old_ends_on: ?CarbonImmutable, new_starts_on: CarbonImmutable}
     */
    public static function at(?CarbonImmutable $oldStartsOn, ?CarbonImmutable $coveredThrough, CarbonImmutable $on): array
    {
        $oldStartsOn = $oldStartsOn?->startOfMonth();
        $coveredThrough = $coveredThrough?->startOfMonth();

        $oldEndsOn = match (true) {
            $oldStartsOn === null => null,
            $coveredThrough !== null => $coveredThrough->endOfMonth()->startOfDay(),
            default => $oldStartsOn->subDay(),
        };

        $newStartsOn = match (true) {
            $coveredThrough !== null => $coveredThrough->addMonthNoOverflow(),
            $oldStartsOn !== null => $oldStartsOn,
            default => $on->startOfMonth()->addMonthNoOverflow(),
        };

        return ['old_ends_on' => $oldEndsOn, 'new_starts_on' => $newStartsOn];
    }
}
