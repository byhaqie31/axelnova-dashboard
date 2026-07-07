<?php

namespace App\Models;

use App\Services\Quoting\FormPayloadNormalizer;
use App\Support\RecordsActivity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'reference_code',
        'source',
        'public_token',
        'client_id',
        'name',
        'email',
        'phone',
        'company',
        'service_package_id',
        'package_key',
        'pricing_config_id',
        'form_payload',
        'document',
        'estimate_min_myr',
        'estimate_max_myr',
        'estimate_eta_value',
        'estimate_eta_unit',
        'status',
        'ip_address',
        'user_agent',
        'submitted_at',
        'viewed_at',
        'sent_at',
        'expires_at',
        'referral_partner_id',
    ];

    protected function casts(): array
    {
        return [
            'form_payload' => 'array',
            'document' => 'array',
            'estimate_min_myr' => 'decimal:2',
            'estimate_max_myr' => 'decimal:2',
            'estimate_eta_value' => 'integer',
            'submitted_at' => 'datetime',
            'viewed_at' => 'datetime',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Lazy expiry: flip every overdue sent quotation to 'expired'. Called on read
     * (admin list, public document view) so the lifecycle stays correct without a
     * scheduler. Cheap, indexed, and a no-op when nothing is overdue.
     */
    public static function expireOverdue(): void
    {
        static::query()
            ->where('status', 'sent')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /** True when this is a sent quote already past its expiry date. */
    public function isOverdue(): bool
    {
        return $this->status === 'sent'
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function pricingConfig(): BelongsTo
    {
        return $this->belongsTo(PricingConfig::class);
    }

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(QuotationAddon::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class, 'referral_partner_id');
    }

    /**
     * The single agreed price for this quotation. Priced from the document the
     * client actually sees — the detailed builder's composed section totals
     * first, then the standard builder's line items (+ tax), and only as a last
     * resort the top of the engine estimate range. Used when an order is born.
     */
    public function finalAmount(): float
    {
        // Detailed (admin-composed) layout — the client-facing total is the sum
        // of the document's own priced sections, not the engine estimate.
        $detailed = self::sumDetailedSections($this->document);
        if ($detailed !== null) {
            return $detailed;
        }

        $doc = $this->document;

        if (is_array($doc) && ! empty($doc['items']) && is_array($doc['items'])) {
            $subtotal = 0.0;
            foreach ($doc['items'] as $item) {
                $subtotal += (float) ($item['qty'] ?? 0) * (float) ($item['rate'] ?? 0);
            }
            $taxRate = (float) ($doc['tax_rate'] ?? 0);

            return round($subtotal * (1 + $taxRate / 100), 2);
        }

        return (float) $this->estimate_max_myr;
    }

    /**
     * Sum the priced sections of a detailed-layout document, or null when the
     * document isn't a detailed build (or carries no priced sections). This is
     * the single source of truth for "what a detailed quote is worth" — shared
     * by finalAmount() and the controller when it stamps the stored estimate.
     */
    public static function sumDetailedSections(?array $document): ?float
    {
        if (! is_array($document) || ($document['layout'] ?? null) !== 'detailed') {
            return null;
        }

        $sections = $document['payload']['sections'] ?? null;
        if (! is_array($sections) || $sections === []) {
            return null;
        }

        return (float) array_sum(array_map(
            fn ($s) => (float) ($s['total'] ?? 0),
            $sections,
        ));
    }

    /**
     * Expected completion date derived from the ETA, measured from $anchor
     * (defaults to now). Null when no ETA was captured.
     */
    public function dueDateFrom(?CarbonInterface $anchor = null): ?CarbonInterface
    {
        $value = (int) ($this->estimate_eta_value ?? 0);
        if ($value <= 0) {
            return null;
        }

        $date = ($anchor ?? now())->copy();

        return match ($this->estimate_eta_unit) {
            'hour' => $date->addHours($value),
            'day' => $date->addDays($value),
            'month' => $date->addMonths($value),
            default => $date->addWeeks($value),
        };
    }

    /** Deposit percentage carried from the detailed document, default 50%. */
    public function depositPct(): int
    {
        $doc = $this->document;
        $pct = is_array($doc) ? (int) ($doc['deposit_pct'] ?? 0) : 0;

        return $pct > 0 ? $pct : 50;
    }

    /**
     * Human-friendly ETA — e.g. "5 days", "2 weeks", "1 month".
     */
    public function getEtaLabelAttribute(): string
    {
        $value = (int) ($this->estimate_eta_value ?? 0);
        $unit = (string) ($this->estimate_eta_unit ?? 'week');
        $plural = $value === 1 ? $unit : "{$unit}s";

        return "{$value} {$plural}";
    }

    /**
     * The canonical multi-package reader view of this row's form_payload,
     * regardless of which of the historical shapes it was written in (funnel,
     * legacy admin, connector, or new multi-package). Every backend render path
     * reads through this so all shapes look identical downstream.
     *
     * @return array{packages: list<array>, rush: bool, breakdown: list<array>, source_meta: array{created_via: ?string}}
     */
    public function normalizedForm(): array
    {
        return FormPayloadNormalizer::normalize($this->form_payload, $this->package_key, $this->document);
    }

    /**
     * Flat `[label, min, max]` breakdown tuples from whatever breakdown shape is
     * stored (grouped-per-package or legacy-flat). The customer email and the
     * DocumentMapper line-item fallback consume this.
     *
     * @return list<array{0: string, 1: float, 2: float}>
     */
    public function flatBreakdown(): array
    {
        return FormPayloadNormalizer::flattenBreakdown($this->form_payload['breakdown'] ?? []);
    }
}
