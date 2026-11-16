<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\UserManagement\Entities\User;
/** Someone who works in an agency's portal: the manager, a counter agent, a boarding agent. */
class BusStaff extends Model
{
    public const MANAGER = 'manager';
    public const COUNTER = 'counter';
    public const BOARDING = 'boarding';
    public const ROLES = [self::MANAGER, self::COUNTER, self::BOARDING];

    protected $table = 'bus_staff';

    protected $fillable = ['agency_id', 'user_id', 'role', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
