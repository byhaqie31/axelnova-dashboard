<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** Anonymous likes (entity_likes table, scoped to this entity type). */
    public function likes(): HasMany
    {
        return $this->hasMany(EntityLike::class, 'entity_id')->where('entity_type', 'project');
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
