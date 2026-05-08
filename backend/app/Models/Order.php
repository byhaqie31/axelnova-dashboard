<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'quotation_id',
        'client_id',
        'value_min_myr',
        'value_max_myr',
        'status',
        'started_at',
        'delivered_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value_min_myr' => 'decimal:2',
            'value_max_myr' => 'decimal:2',
            'started_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
