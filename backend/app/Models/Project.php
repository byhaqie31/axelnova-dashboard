<?php

namespace App\Models;

use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use RecordsActivity;

    /** Anonymous likes (entity_likes table, scoped to this entity type). */
    public function likes(): HasMany
    {
        return $this->hasMany(EntityLike::class, 'entity_id')->where('entity_type', 'project');
    }

    /** Studio partners this project was built with (see CollaborationPartner). */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(CollaborationPartner::class, 'collaboration_partner_project')
            ->withPivot(['mode', 'role_note', 'credit_public'])
            ->withTimestamps();
    }

    /** Partners the public site may credit on this project: credit on, partner live. */
    public function publicPartners(): BelongsToMany
    {
        return $this->partners()
            ->wherePivot('credit_public', true)
            ->where('collaboration_partners.active', true);
    }

    protected $fillable = [
        'slug',
        'name',
        'description',
        'long_description',
        'status',
        'url',
        'repo',
        'tags',
        'stack',
        'featured',
        'sort_order',
        'cover_image_url',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'stack' => 'array',
            'featured' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
