<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Attribution for a model. `logActivity()` appends an audit row AND stamps the
 * record's `updated_by` with the acting user in one call. The actor is the
 * authenticated user, or null — a null actor marks a system / gateway-webhook
 * write, so gateway-driven payments log `actor_id = null` for free (no auth user
 * on a webhook request).
 *
 * Apply only to models whose table has an `updated_by` column.
 */
trait RecordsActivity
{
    public function logActivity(string $action, ?array $changes = null): ActivityLog
    {
        $actorId = Auth::id();

        // Stamp the updater without firing model events (no observer recursion,
        // no touching the caller's other pending changes — save those first).
        if ($this->getKey() && $this->getAttribute('updated_by') !== $actorId) {
            $this->forceFill(['updated_by' => $actorId])->saveQuietly();
        }

        $log = ActivityLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => class_basename($this),
            'subject_id' => $this->getKey(),
            'changes' => $changes,
        ]);

        // Tell the LogAdminActivity middleware this request is already audited, so
        // it won't also write a generic catch-all row for the same action.
        app()->instance('activity.recorded', true);

        return $log;
    }

    /** Who last touched this record (null = system / gateway). */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
