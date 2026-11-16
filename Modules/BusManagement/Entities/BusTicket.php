<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** One seat of a booking, with the passenger on it. */
class BusTicket extends Model
{
    use HasUuid;

    public const HELD = 'held';
    public const VALID = 'valid';
    public const BOARDED = 'boarded';
    public const NO_SHOW = 'no_show';
    public const CANCELLED = 'cancelled';

    protected $fillable = ['code', 'booking_id', 'departure_id', 'seat', 'passenger_name', 'phone', 'id_number', 'bags', 'fare', 'status', 'boarded_at', 'boarded_by',
        'collected_at', 'collected_by', 'print_count', 'last_printed_at'];

    protected $casts = ['fare' => 'float', 'bags' => 'integer', 'boarded_at' => 'datetime', 'collected_at' => 'datetime', 'print_count' => 'integer',
        'last_printed_at' => 'datetime'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(BusBooking::class, 'booking_id');
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(BusDeparture::class, 'departure_id');
    }
}
