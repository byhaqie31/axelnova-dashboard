<?php

namespace App\Models;

use App\Observers\ServiceCategoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ServiceCategoryObserver::class])]
class ServiceCategory extends Model
{
    protected $fillable = ['slug', 'name', 'icon', 'description', 'sort_order', 'active', 'is_default'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ServicePackage::class);
    }
}
