<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/** Someone a customer shares their trips with in one tap (WhatsApp). */
class TrustedContact extends Model
{
    use HasUuid;

    public const MAX_PER_CUSTOMER = 3;

    protected $fillable = ['user_id', 'name', 'phone', 'night_share'];

    /** night_share: this contact follows every night ride (TripCheckinService). */
    protected $casts = ['night_share' => 'boolean'];
}
