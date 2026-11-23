<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An E-Ride kitchen linked to its POS Innova system by a key (PosChannelService). */
class StorePosLink extends Model
{
    use HasUuid;

    protected $fillable = ['store_id', 'key_hash', 'key_hint', 'pos_location_id', 'pos_location_name', 'pos_version', 'qty_display',
        'linked_at', 'last_seen_at', 'last_heartbeat_at', 'last_catalog_at', 'revoked_at', 'created_by'];

    protected $casts = ['linked_at' => 'datetime', 'last_seen_at' => 'datetime', 'last_heartbeat_at' => 'datetime',
        'last_catalog_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StorePosItem::class, 'link_id');
    }

    /** Heard from within the last 15 minutes (E-Ride's clock). */
    public function isConnected(): bool
    {
        return $this->last_heartbeat_at !== null && $this->last_heartbeat_at->gt(now()->subMinutes(15));
    }
}
