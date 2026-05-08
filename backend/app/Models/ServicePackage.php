<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackage extends Model
{
    protected $fillable = [
        'service_category_id',
        'slug',
        'name',
        'tagline',
        'price_min_myr',
        'price_max_myr',
        'unit',
        'duration_text',
        'revisions',
        'featured',
        'features',
        'cta',
        'quote_key',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price_min_myr' => 'decimal:2',
            'price_max_myr' => 'decimal:2',
            'features' => 'array',
            'quote_key' => 'array',
            'featured' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
}
