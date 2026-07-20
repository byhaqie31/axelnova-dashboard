<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Feedback extends Model
{
    use HasFactory, SoftDeletes;

    /** "feedback" is uncountable, but be explicit rather than rely on pluralisation. */
    protected $table = 'feedback';

    protected $fillable = [
        'reference_code',
        'order_id',
        'client_id',
        'public_token',
        'name',
        'email',
        'project_label',
        'overall',
        'rating_design',
        'rating_communication',
        'rating_delivery',
        'rating_value',
        'nps',
        'praise',
        'improve',
        'publish_consent',
        'attribution_name',
        'attribution_role',
        'status',
        'source',
        'featured',
        'sort_order',
        'submitted_at',
        'reviewed_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'publish_consent' => 'boolean',
            'featured' => 'boolean',
            'overall' => 'integer',
            'rating_design' => 'integer',
            'rating_communication' => 'integer',
            'rating_delivery' => 'integer',
            'rating_value' => 'integer',
            'nps' => 'integer',
            'sort_order' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** Unguessable page credential — uniqueness-checked against ALL rows (soft-deleted included). */
    public static function mintToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::withTrashed()->where('public_token', $token)->exists());

        return $token;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Mean of the non-null dimension scores (design/communication/delivery/value), 1dp. */
    protected function averageRating(): Attribute
    {
        return Attribute::get(function () {
            $scores = array_filter([
                $this->rating_design,
                $this->rating_communication,
                $this->rating_delivery,
                $this->rating_value,
            ], fn ($s) => $s !== null);

            return $scores === [] ? null : round(array_sum($scores) / count($scores), 1);
        });
    }

    /** Standard NPS banding: promoter ≥ 9, passive 7–8, detractor ≤ 6. */
    protected function npsBucket(): Attribute
    {
        return Attribute::get(function () {
            if ($this->nps === null) {
                return null;
            }

            return match (true) {
                $this->nps >= 9 => 'promoter',
                $this->nps >= 7 => 'passive',
                default => 'detractor',
            };
        });
    }
}
