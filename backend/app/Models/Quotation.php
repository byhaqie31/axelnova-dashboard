<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

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
        ];
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

    /**
     * The single agreed price for this quotation. For a detailed (admin-built)
     * quote it's the document line-item total (+ tax if any); otherwise it
     * falls back to the top of the estimate range. Used when an order is born.
     */
    public function finalAmount(): float
    {
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
     * Expected completion date derived from the ETA, measured from $anchor
     * (defaults to now). Null when no ETA was captured.
     */
    public function dueDateFrom(?\Carbon\CarbonInterface $anchor = null): ?\Carbon\CarbonInterface
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
}
