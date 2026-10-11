<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One email the dashboard sent (or definitively failed to send) — spec §5.2.
 * Rows are written by the mail listeners (App\Services\Mail\EmailLogger) and
 * advanced by the Resend webhook; the admin "Emails" panel reads them.
 *
 * Status only moves forward by rank (sent → delayed → delivered →
 * bounced|failed → complained), so a late or repeated delivery report can
 * never undo a bounce — which also makes webhook handling idempotent.
 */
class EmailLog extends Model
{
    /** Status → rank. A report may only move a row to a strictly higher rank. */
    public const RANK = [
        'sent' => 0,
        'delayed' => 1,
        'delivered' => 2,
        'bounced' => 3,
        'failed' => 3,
        'complained' => 4,
    ];

    public const PROBLEMS = ['bounced', 'complained', 'failed'];

    /**
     * Short alias → model, for `related_type`. Deliberately NOT a global
     * Relation::morphMap — that would rewrite every morph type app-wide.
     */
    public const RELATED = [
        'quotation' => Quotation::class,
        'order' => Order::class,
        'invoice' => Invoice::class,
        'payment' => Payment::class,
        'inquiry' => Inquiry::class,
        'feedback' => Feedback::class,
        'referral' => Referral::class,
        'external_account' => ExternalAccount::class,
        'user' => User::class,
        'email_log' => self::class,
    ];

    protected $fillable = [
        'type', 'audience', 'related_type', 'related_id', 'to_email', 'to_name', 'subject',
        'route', 'status', 'status_detail', 'message_id', 'provider_id', 'triggered_by',
        'sent_at', 'status_changed_at', 'alerted_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'alerted_at' => 'datetime',
    ];

    /** The alias for a model's class (e.g. Invoice → "invoice"). */
    public static function aliasFor(Model $model): string
    {
        $alias = array_search($model::class, self::RELATED, true);

        return $alias !== false ? $alias : Str::snake(class_basename($model));
    }

    /** The record this email is about, or null when it has none / is gone. */
    public function relatedRecord(): ?Model
    {
        $class = self::RELATED[$this->related_type] ?? null;

        return $class && $this->related_id ? $class::find($this->related_id) : null;
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Move to a higher-ranked status; returns false (and changes nothing)
     * otherwise. The rank check runs inside the UPDATE itself, so two reports
     * processed at the same moment can't move a row backwards. A problem
     * status keeps the earlier reason unless given a new one; any other
     * status replaces it (a "delivered" row drops its old "delayed" note).
     */
    public function advanceTo(string $status, ?string $detail = null): bool
    {
        if (! array_key_exists($status, self::RANK)) {
            throw new InvalidArgumentException("Unknown email status [{$status}].");
        }

        $lower = array_keys(array_filter(self::RANK, fn (int $rank): bool => $rank < self::RANK[$status]));

        $changes = ['status' => $status, 'status_changed_at' => now()];
        if ($detail !== null || ! in_array($status, self::PROBLEMS, true)) {
            $changes['status_detail'] = $detail;
        }

        $updated = static::query()->whereKey($this->getKey())->whereIn('status', $lower)->update($changes);
        $this->refresh();

        return $updated === 1;
    }

    public function isProblem(): bool
    {
        return in_array($this->status, self::PROBLEMS, true);
    }
}
