<?php

namespace Modules\PlaceManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** What an owner's strikes led to: a pause from listing, or a block after a scam. */
class ListingOwner extends Model
{
    protected $primaryKey = 'owner_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['owner_id', 'banned_until', 'blocked'];

    protected $casts = [
        'banned_until' => 'datetime',
        'blocked' => 'boolean',
    ];

    public function canList(): bool
    {
        return !$this->blocked && !($this->banned_until && $this->banned_until->isFuture());
    }
}
