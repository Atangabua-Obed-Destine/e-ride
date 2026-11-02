<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** Money E-Ride sent to a partner (recorded by an admin after paying out). */
class StorePayout extends Model
{
    use HasUuid;

    protected $fillable = ['store_id', 'amount', 'method', 'reference', 'note', 'created_by'];

    protected $casts = ['amount' => 'float'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
