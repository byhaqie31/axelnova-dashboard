<?php

namespace App\Models;

use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'referral_partner_id',
        'name',
        'email',
        'phone',
        'company',
        'project_type',
        'budget_hint',
        'timeline_hint',
        'message',
        'source',
        'status',
        'quotation_id',
        'ip_address',
        'user_agent',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** The referrer whose ?ref link brought this inquiry in (null = public/organic). */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class, 'referral_partner_id');
    }
}
