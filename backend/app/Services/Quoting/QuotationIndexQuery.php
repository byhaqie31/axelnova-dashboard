<?php

namespace App\Services\Quoting;

use App\Models\Quotation;
use App\Models\QuotationRevision;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The single quotation-listing query, shared by the admin index and the MCP
 * connector's list endpoint so the two can never drift apart (search + status +
 * date-range + newest-first sort live here once). Soft-deleted rows are excluded
 * automatically by the model's SoftDeletes scope — the connector must never see
 * them.
 *
 * Behaviour differences between the two callers are parameters, not forked code:
 *   • the admin defaults to hiding `accepted` (they live on the Orders page) unless
 *     it explicitly asks for them; the connector always lists every non-deleted row.
 *   • the admin passes a comma-separated `status` string; the connector a validated
 *     `status[]` array — both arrive here as a normalised list.
 *
 * The date filter + sort key is `submitted_at` (set to the creation time for
 * admin/connector-authored drafts), preserving the admin's existing behaviour.
 */
final class QuotationIndexQuery
{
    /**
     * @param  list<string>  $statuses  Empty = no status filter.
     * @param  bool  $excludeAcceptedWhenUnfiltered  Hide `accepted` when no status
     *                                               filter is set (admin only).
     */
    public function __construct(
        public readonly array $statuses = [],
        public readonly ?string $search = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly bool $excludeAcceptedWhenUnfiltered = false,
        public readonly bool $revisedAfterAcceptance = false,
    ) {}

    /**
     * The admin listing: `status` is a comma-separated string, and with no status
     * filter it hides accepted quotes (surfaced only via ?include_accepted=1).
     */
    public static function fromAdminRequest(Request $request): self
    {
        $statuses = collect(explode(',', (string) $request->query('status', '')))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();

        return new self(
            statuses: $statuses,
            search: $request->filled('search') ? (string) $request->query('search') : null,
            dateFrom: $request->filled('date_from') ? (string) $request->query('date_from') : null,
            dateTo: $request->filled('date_to') ? (string) $request->query('date_to') : null,
            excludeAcceptedWhenUnfiltered: ! $request->boolean('include_accepted'),
            revisedAfterAcceptance: $request->boolean('revised'),
        );
    }

    /**
     * The connector listing: `status` is a validated array, `q` is the search term,
     * and `from`/`to` bound the date range. It lists every non-deleted row by
     * default (no accepted-hiding).
     *
     * @param  array<string, mixed>  $filters  Validated ListQuotationsRequest data.
     */
    public static function fromConnectorFilters(array $filters): self
    {
        return new self(
            statuses: array_values(array_filter((array) ($filters['status'] ?? []))),
            search: ($filters['q'] ?? null) ?: null,
            dateFrom: ($filters['from'] ?? null) ?: null,
            dateTo: ($filters['to'] ?? null) ?: null,
            excludeAcceptedWhenUnfiltered: false,
        );
    }

    /**
     * Add the two revision scalars a list row shows without extra queries: the
     * family's current revision number and its open (draft / sent) revision as
     * "number:status" — for "AXNQ-… · R2" and the "R3 draft" chip.
     */
    public static function withRevisionColumns(Builder $query): Builder
    {
        // A sub-select alone would REPLACE the implicit `*` — keep every column
        // unless the caller already chose its own (the admin list does).
        if ($query->getQuery()->columns === null) {
            $query->select('quotations.*');
        }

        return $query
            ->selectSub(fn ($q) => $q->from('quotation_revisions')->select('number')
                ->whereColumn('quotation_revisions.id', 'quotations.current_revision_id'), 'current_revision_number')
            ->selectSub(fn ($q) => $q->from('quotation_revisions')->selectRaw("CONCAT(number, ':', status)")
                ->whereColumn('quotation_revisions.quotation_id', 'quotations.id')
                ->whereIn('status', QuotationRevision::OPEN)->orderByDesc('number')->limit(1), 'open_revision');
    }

    /** Build the filtered, newest-first query (soft-deleted rows excluded by scope). */
    public function builder(): Builder
    {
        // id is a stable tiebreaker so same-second rows keep a deterministic
        // newest-first order (previously arbitrary on a submitted_at tie).
        $query = Quotation::query()->latest('submitted_at')->latest('id');

        // One row per quotation FAMILY — `status` is the family's derived status.
        // A draft / sent filter also matches a family whose OPEN revision has that
        // status (an accepted R1 with an R2 draft in progress is "a draft" to work on).
        if ($this->statuses !== []) {
            $open = array_values(array_intersect($this->statuses, QuotationRevision::OPEN));
            $query->where(fn (Builder $q) => $q->whereIn('status', $this->statuses)
                ->when($open !== [], fn (Builder $q) => $q->orWhereExists(fn ($sub) => $sub->from('quotation_revisions')
                    ->whereColumn('quotation_revisions.quotation_id', 'quotations.id')
                    ->whereIn('quotation_revisions.status', $open))));
        } elseif ($this->excludeAcceptedWhenUnfiltered && ! $this->revisedAfterAcceptance) {
            // No status filter: accepted quotes produced an order and live on the
            // Orders page — hide them unless the caller asked for everything, or a
            // new revision is in progress on one (that needs the founder here).
            $query->where(fn (Builder $q) => $q->where('status', '!=', 'accepted')
                ->orWhereExists(fn ($sub) => $sub->from('quotation_revisions')
                    ->whereColumn('quotation_revisions.quotation_id', 'quotations.id')
                    ->whereIn('quotation_revisions.status', QuotationRevision::OPEN)));
        }

        // Revised after acceptance: some revision was opened from an accepted one
        // (and not discarded) — whether it has been accepted yet or not.
        if ($this->revisedAfterAcceptance) {
            $query->whereExists(fn ($sub) => $sub->from('quotation_revisions as rn')
                ->join('quotation_revisions as rp', 'rp.id', '=', 'rn.replaces_revision_id')
                ->whereColumn('rn.quotation_id', 'quotations.id')
                ->where('rn.status', '!=', QuotationRevision::DISCARDED)
                ->whereIn('rp.status', [QuotationRevision::ACCEPTED, QuotationRevision::SUPERSEDED]));
        }

        if ($this->search !== null && $this->search !== '') {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        // Range comparisons (not whereDate) so the submitted_at index stays
        // usable — DATE(submitted_at) would force a full scan.
        if ($this->dateFrom !== null && $this->dateFrom !== '') {
            $query->where('submitted_at', '>=', Carbon::parse($this->dateFrom)->startOfDay());
        }

        if ($this->dateTo !== null && $this->dateTo !== '') {
            $query->where('submitted_at', '<', Carbon::parse($this->dateTo)->addDay()->startOfDay());
        }

        return $query;
    }
}
