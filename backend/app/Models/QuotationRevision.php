<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One numbered revision (R1, R2, …) of a quotation family — the commercial
 * content the client sees, as an auditable snapshot. The family row
 * (`quotations`) keeps its reference code for the life of the project and
 * mirrors the CURRENT revision's content; see QuotationRevisions for the rules
 * and docs/global/QUOTATION-REVISIONS.md for the model.
 *
 *   draft ──send──► sent ──accept──► accepted ──(newer accepted)──► superseded
 *     │               ├──reject──► rejected
 *     └──discard──►   └──(lazy)──► expired         discarded
 *
 * Only a draft is editable. Accepted and superseded revisions are frozen: their
 * PDF re-renders from the stored content, never re-priced.
 */
class QuotationRevision extends Model
{
    public const DRAFT = 'draft';

    public const SENT = 'sent';

    public const ACCEPTED = 'accepted';

    public const SUPERSEDED = 'superseded';

    public const REJECTED = 'rejected';

    public const EXPIRED = 'expired';

    public const DISCARDED = 'discarded';

    public const STATUSES = [
        self::DRAFT, self::SENT, self::ACCEPTED, self::SUPERSEDED,
        self::REJECTED, self::EXPIRED, self::DISCARDED,
    ];

    /** Non-final — at most one of these per family at a time. */
    public const OPEN = [self::DRAFT, self::SENT];

    /** States a new revision may be cloned from ("Revise"). */
    public const REVISABLE = [self::ACCEPTED, self::SENT, self::REJECTED, self::EXPIRED];

    /** The content columns a revision snapshots — the same names as on `quotations`. */
    public const CONTENT = [
        'service_package_id', 'package_key', 'pricing_config_id', 'form_payload',
        'document', 'locale', 'estimate_min_myr', 'estimate_max_myr',
        'estimate_eta_value', 'estimate_eta_unit',
    ];

    protected $fillable = [
        'quotation_id', 'number', 'status', 'change_reason', 'replaces_revision_id',
        'public_token', ...self::CONTENT, 'addons',
        'issued_at', 'sent_at', 'expires_at', 'accepted_at', 'superseded_at', 'created_by',
    ];

    /**
     * A draft's issue date follows its latest write (same rule as the quotation
     * row it replaced); it freezes once the revision leaves draft.
     */
    protected static function booted(): void
    {
        static::saving(function (QuotationRevision $revision): void {
            if ($revision->status === self::DRAFT) {
                $revision->issued_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'form_payload' => 'array',
            'document' => 'array',
            'addons' => 'array',
            'estimate_min_myr' => 'decimal:2',
            'estimate_max_myr' => 'decimal:2',
            'estimate_eta_value' => 'integer',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class)->withTrashed();
    }

    /** The revision this one was cloned from ("replaces R1"). */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_revision_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pricingConfig(): BelongsTo
    {
        return $this->belongsTo(PricingConfig::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** Accepted / superseded — the agreed terms; never edited, never re-priced. */
    public function isFrozen(): bool
    {
        return in_array($this->status, [self::ACCEPTED, self::SUPERSEDED], true);
    }

    /** "R2". */
    public function label(): string
    {
        return "R{$this->number}";
    }

    /** "AXNQ-2026-0017" on R1, "AXNQ-2026-0017 · R2" after. */
    public static function displayReference(string $referenceCode, int $number): string
    {
        return $number > 1 ? "{$referenceCode} · R{$number}" : $referenceCode;
    }

    /** "AXNQ-2026-0017" on R1, "AXNQ-2026-0017-R2" after — the PDF filename stem. */
    public static function fileStem(string $referenceCode, int $number): string
    {
        return $number > 1 ? "{$referenceCode}-R{$number}" : $referenceCode;
    }

    /** The public, token-gated PDF path (frontend Nitro route), or null before a token exists. */
    public function pdfPath(): ?string
    {
        return $this->public_token ? "/documents/{$this->public_token}/pdf" : null;
    }

    /** Add-on rows of this snapshot as (unsaved) QuotationAddon models — what renderers read. */
    public function addonModels(): Collection
    {
        return collect($this->addons ?? [])->map(fn (array $a) => (new QuotationAddon)->forceFill([
            'quotation_id' => $this->quotation_id,
            'addon_key' => $a['addon_key'] ?? null,
            'addon_label' => $a['addon_label'] ?? null,
            'amount_myr' => $a['amount_myr'] ?? 0,
        ]));
    }

    /**
     * This revision as a read-only Quotation: the family's identity (reference,
     * client, provenance) with THIS revision's content, dates, status and token.
     * Lets every quotation reader (DocumentMapper, finalAmount, PaymentPlan, the
     * admin + connector resources) render any revision with no revision-specific
     * code. Saving it throws — it is a view, not a row.
     */
    public function asQuotation(?Quotation $family = null): Quotation
    {
        $family ??= $this->quotation;

        $view = new Quotation;
        $view->setRawAttributes($family->getAttributes());
        $view->forceFill(array_merge(
            $this->only(self::CONTENT),
            [
                'status' => $this->status,
                'public_token' => $this->public_token,
                'issued_at' => $this->issued_at,
                'sent_at' => $this->sent_at,
                'expires_at' => $this->expires_at,
            ],
        ));
        $view->exists = true;
        $view->revisionView = $this;
        $view->setRelation('addons', $this->addonModels());
        $view->setRelation('pricingConfig', $this->pricingConfig ?? $family->pricingConfig);
        foreach (['order', 'referrer', 'updatedBy', 'client'] as $relation) {
            if ($family->relationLoaded($relation)) {
                $view->setRelation($relation, $family->getRelation($relation));
            }
        }

        return $view;
    }
}
