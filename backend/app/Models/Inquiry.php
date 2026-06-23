<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id',
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
}
