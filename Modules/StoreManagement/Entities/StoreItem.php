<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreItem extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = ['store_id', 'store_menu_category_id', 'name', 'description', 'price', 'image', 'is_available', 'sort'];

    protected $casts = [
        'price' => 'float',
        'is_available' => 'boolean',
        'sort' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreMenuCategory::class, 'store_menu_category_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? dynamicStorage('storage/app/public/store/item') . '/' . $this->image : null;
    }
}
