<?php

namespace App\Models;

use App\Observers\PricingConfigObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[ObservedBy([PricingConfigObserver::class])]
class PricingConfig extends Model
{
    use HasFactory;

    protected $fillable = ['version', 'config', 'active', 'notes'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'active' => 'boolean',
        ];
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public static function getActive(): self
    {
        // Every PricingEngine::active() (quote store/update, ScopeSummary,
        // DocumentMapper) resolves the active row — cache it; the observer
        // forgets the key on any save/delete so activations apply immediately.
        return Cache::remember(
            'pricing_config_active_v1',
            3600,
            fn () => static::where('active', true)->firstOrFail(),
        );
    }
}
