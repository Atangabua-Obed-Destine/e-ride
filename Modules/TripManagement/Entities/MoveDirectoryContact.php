<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** A customer tapped Call or WhatsApp on a directory listing. */
class MoveDirectoryContact extends Model
{
    public const UPDATED_AT = null;
    public const CHANNELS = ['call', 'whatsapp'];

    protected $fillable = [
        'listing_id',
        'customer_id',
        'channel',
    ];
}
