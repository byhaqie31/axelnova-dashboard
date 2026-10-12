<?php

namespace App\Models;

use App\Observers\ServicePackageObserver;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ServicePackageObserver::class])]
class ServicePackage extends Model
{
    use RecordsActivity;

    /** Anonymous likes (entity_likes table, scoped to this entity type). */
    public function likes(): HasMany
    {
        return $this->hasMany(EntityLike::class, 'entity_id')->where('entity_type', 'service_package');
    }

    protected $fillable = [
        'service_category_id',
        'slug',
        'name',
        'tagline',
        'price_min_myr',
        'price_max_myr',
        'unit',
        'duration_text',
        'eta_value',
        'eta_unit',
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
            'eta_value' => 'integer',
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

    /**
     * The ONE rule for whether a package is public: it is effectively Live — on
     * /services, and quotable if wired — only while BOTH its own `active` flag and
     * its service's are on. Pass `$in` to ask about another service (a move). The
     * connector's edit guard, `public_url` and `quotable` all read this, so they
     * can't drift apart. It never changes the package's own flag.
     */
    public function isEffectivelyLive(?ServiceCategory $in = null): bool
    {
        return $this->active && (bool) ($in ?? $this->category)?->active;
    }

    /** isEffectivelyLive() as a query — the packages the quote engine may price. */
    public function scopeEffectivelyLive(Builder $query): Builder
    {
        return $query->where('active', true)->whereHas('category', fn (Builder $q) => $q->where('active', true));
    }

    /** `live` | `hidden` — isEffectivelyLive() as a label (the package's own flag stays `active`). */
    protected function effectiveVisibility(): Attribute
    {
        return Attribute::get(fn () => $this->isEffectivelyLive() ? 'live' : 'hidden');
    }
}
