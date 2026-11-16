<?php

namespace Modules\HotelManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** Someone who works in a hotel's portal: the manager, or a receptionist. */
class HotelStaff extends Model
{
    public const MANAGER = 'manager';
    public const RECEPTION = 'reception';
    public const ROLES = [self::MANAGER, self::RECEPTION];

    protected $table = 'hotel_staff';

    protected $fillable = ['hotel_id', 'user_id', 'role', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
