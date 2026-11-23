<?php

namespace Modules\HotelManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A hotel linked to the hotel module of its POS Innova by an erh_ key (only its hash is kept). */
class HotelPosLink extends Model
{
    use HasUuid;

    protected $fillable = ['hotel_id', 'key_hash', 'key_hint', 'created_by'];

    protected $casts = [
        'hotel_day' => 'date',
        'linked_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'last_availability_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function types(): HasMany
    {
        return $this->hasMany(HotelPosType::class, 'link_id');
    }

    /** POS checked in within the last 15 minutes (it sends a heartbeat every 5). */
    public function isConnected(): bool
    {
        return $this->last_heartbeat_at !== null && $this->last_heartbeat_at->gt(now()->subMinutes(15));
    }
}
