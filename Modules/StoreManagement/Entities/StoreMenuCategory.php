<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreMenuCategory extends Model
{
    use HasUuid, SoftDeletes, HasServingTimes;

    protected $fillable = ['store_id', 'name', 'sort', 'is_active', 'served_days', 'served_from', 'served_until'];

    protected $casts = [
        'sort' => 'integer',
        'is_active' => 'boolean',
        'served_days' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreItem::class)->orderBy('sort')->orderBy('name');
    }
}
