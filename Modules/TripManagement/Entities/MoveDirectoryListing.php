<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\Entities\User;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Modules\ZoneManagement\Entities\Zone;

/**
 * A truck owner customers can call when no truck is available in the app
 * (E-Ride Move > Directory). Added by the admin, with the owner's consent.
 */
class MoveDirectoryListing extends Model
{
    use HasUuid;

    public const PHOTO_FOLDER = 'move/directory/';

    protected $fillable = [
        'zone_id',
        'vehicle_category_id',
        'user_id',
        'owner_name',
        'phone',
        'whatsapp',
        'areas',
        'photo',
        'note',
        'consent_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'consent_at' => 'datetime',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function vehicleCategory()
    {
        return $this->belongsTo(VehicleCategory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contacts()
    {
        return $this->hasMany(MoveDirectoryContact::class, 'listing_id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? dynamicStorage('storage/app/public/' . rtrim(self::PHOTO_FOLDER, '/')) . '/' . $this->photo : null;
    }
}
