<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntityLike extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['entity_type', 'entity_id', 'ip_hash', 'cookie_id'];
}
