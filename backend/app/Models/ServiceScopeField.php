<?php

namespace App\Models;

use App\Observers\ServiceScopeFieldObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One admin-managed scope input on one service category (a slider, select or
 * toggle). Supersedes the hardcoded QuoteScopeFields blocks + the pricing_configs
 * JSON `modifiers` map: defines BOTH the builder UI and the per-package pricing.
 *
 * `config` holds type-specific settings:
 *  - slider: { min, max, default, unit, free_threshold, price_per_unit }
 *  - toggle: { amount, default }
 *  - select: { default, options: [{ value, label, amount }] }
 * `applies_to` is an array of quote_key.package strings ([] = all packages).
 */
#[ObservedBy([ServiceScopeFieldObserver::class])]
class ServiceScopeField extends Model
{
    protected $fillable = [
        'service_category_id',
        'field_key',
        'label',
        'type',
        'applies_to',
        'config',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'applies_to' => 'array',
            'config' => 'array',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
}
