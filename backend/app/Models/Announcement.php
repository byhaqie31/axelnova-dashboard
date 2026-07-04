<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company notice authored from the cockpit (Task 6). `audience` scopes who
 * sees it once published: 'team' (workspace only), 'partners' (a later
 * phase — the partner portal doesn't read this table yet), or 'all' (both).
 * `published_at` null = draft; the team feed (App\Http\Controllers\Api\V1\
 * Team\AnnouncementsController) only ever reads published team/all rows.
 * No soft-deletes — the brief's schema carries none, and "unpublish" (revert
 * to draft) is the only retraction verb; there's no hard-delete endpoint.
 */
class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'audience',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /** The admin who authored the announcement. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
