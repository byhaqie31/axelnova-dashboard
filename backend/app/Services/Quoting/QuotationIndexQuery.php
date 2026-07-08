<?php

namespace App\Services\Quoting;

use App\Models\Quotation;
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

    /** Build the filtered, newest-first query (soft-deleted rows excluded by scope). */
    public function builder(): Builder
    {
        // id is a stable tiebreaker so same-second rows keep a deterministic
        // newest-first order (previously arbitrary on a submitted_at tie).
        $query = Quotation::query()->latest('submitted_at')->latest('id');

        if ($this->statuses !== []) {
            $query->whereIn('status', $this->statuses);
        } elseif ($this->excludeAcceptedWhenUnfiltered) {
            // No status filter: accepted quotes produced an order and live on the
            // Orders page — hide them unless the caller asked for everything.
            $query->where('status', '!=', 'accepted');
        }

        if ($this->search !== null && $this->search !== '') {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        if ($this->dateFrom !== null && $this->dateFrom !== '') {
            $query->whereDate('submitted_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== null && $this->dateTo !== '') {
            $query->whereDate('submitted_at', '<=', $this->dateTo);
        }

        return $query;
    }
}
