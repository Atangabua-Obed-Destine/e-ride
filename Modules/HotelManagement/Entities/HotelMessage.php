<?php

namespace Modules\HotelManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** One line of the guest and reception chat: text, or a card (request, price, kept room...). */
class HotelMessage extends Model
{
    public const UPDATED_AT = null;

    public const GUEST = 'guest';
    public const HOTEL = 'hotel';
    public const SYSTEM = 'system';

    protected $fillable = ['inquiry_id', 'sender', 'user_id', 'kind', 'body', 'data'];

    protected $casts = ['data' => 'array', 'created_at' => 'datetime'];
}
