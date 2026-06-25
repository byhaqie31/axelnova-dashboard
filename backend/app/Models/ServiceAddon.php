<?php

namespace App\Models;

use App\Observers\ServiceAddonObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([ServiceAddonObserver::class])]
class ServiceAddon extends Model
{
    protected $fillable = [
        'addon_key',
        'label',
        'amount_myr',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'amount_myr' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
