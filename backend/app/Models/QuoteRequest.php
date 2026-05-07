<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuoteRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference_code',
        'name',
        'email',
        'phone',
        'company',
        'service_category_id',
        'service_package_id',
        'pricing_config_id',
        'form_payload',
        'estimate_min_myr',
        'estimate_max_myr',
        'estimate_weeks',
        'status',
        'project_status',
        'project_started_at',
        'project_delivered_at',
        'project_completed_at',
        'client_id',
        'quotation_id',
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
            'submitted_at' => 'datetime',
            'viewed_at' => 'datetime',
            'project_started_at' => 'datetime',
            'project_delivered_at' => 'datetime',
            'project_completed_at' => 'datetime',
        ];
    }

    public function pricingConfig(): BelongsTo
    {
        return $this->belongsTo(PricingConfig::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(QuoteRequestAddon::class);
    }
}
