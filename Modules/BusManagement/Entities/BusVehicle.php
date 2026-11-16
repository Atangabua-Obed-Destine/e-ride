<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** A bus of an agency, with the settings of its seat plan (see Lib\SeatPlan). */
class BusVehicle extends Model
{
    use HasUuid;

    public const CLASSES = ['classic', 'vip', 'executive'];
    public const READY = 'ready';
    public const GARAGE = 'garage';
    public const RETIRED = 'retired';
    public const STATUSES = [self::READY, self::GARAGE, self::RETIRED];
    public const AMENITIES = ['air_con', 'usb', 'wifi', 'toilet', 'tv', 'water', 'reclining'];

    protected $fillable = [
        'agency_id', 'registration', 'name', 'class', 'status', 'total_seats', 'driver_position', 'is_double_decker', 'upper_deck_seats',
        'has_rear_seats', 'rear_seats_count', 'seats_per_row', 'aisle_position', 'first_seat_position', 'door_positions', 'cargo_kg',
        'amenities', 'photos',
    ];

    protected $casts = [
        'is_double_decker' => 'boolean',
        'has_rear_seats' => 'boolean',
        'door_positions' => 'array',
        'amenities' => 'array',
        'photos' => 'array',
        'total_seats' => 'integer',
        'upper_deck_seats' => 'integer',
        'rear_seats_count' => 'integer',
        'seats_per_row' => 'integer',
        'aisle_position' => 'integer',
        'cargo_kg' => 'integer',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }

    public function seatPlan(): array
    {
        return \Modules\BusManagement\Lib\SeatPlan::build($this->only([
            'total_seats', 'driver_position', 'is_double_decker', 'upper_deck_seats', 'has_rear_seats', 'rear_seats_count', 'seats_per_row',
            'aisle_position', 'first_seat_position', 'door_positions',
        ]));
    }
}
