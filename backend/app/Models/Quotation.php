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
        'client_id',
        'name',
        'email',
        'phone',
        'company',
        'service_package_id',
        'package_key',
        'pricing_config_id',
        'form_payload',
        'estimate_min_myr',
        'estimate_max_myr',
        'estimate_eta_value',
        'estimate_eta_unit',
        'status',
        'ip_address',
        'user_agent',
        'submitted_at',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'form_payload' => 'array',
            'estimate_min_myr' => 'decimal:2',
            'estimate_max_myr' => 'decimal:2',
            'estimate_eta_value' => 'integer',
            'submitted_at' => 'datetime',
            'viewed_at' => 'datetime',
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
