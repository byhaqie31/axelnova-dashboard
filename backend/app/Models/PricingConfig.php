<?php

namespace App\Models;

use App\Observers\PricingConfigObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([PricingConfigObserver::class])]
class PricingConfig extends Model
{
    protected $fillable = ['version', 'config', 'active', 'notes'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'active' => 'boolean',
        ];
    }

    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    public static function getActive(): self
    {
        return static::where('active', true)->firstOrFail();
    }
}
