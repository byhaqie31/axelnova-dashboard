<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
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
