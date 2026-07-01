<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit row. Written via the RecordsActivity trait from
 * state-changing actions; a null `actor_id` marks a system / gateway write.
 */
class ActivityLog extends Model
{
    protected $table = 'activity_log';

    /** Only created_at exists on the table. */
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'changes'];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
