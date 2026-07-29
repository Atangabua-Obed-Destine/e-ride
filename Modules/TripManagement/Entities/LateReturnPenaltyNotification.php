<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\TripManagement\Database\Factories\LateReturnPenaltyNotificationFactory;

class LateReturnPenaltyNotification extends Model
{
    use HasFactory;

    protected $table = 'late_return_penalty_notifications';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'trip_request_id',
        'sending_notification_at',
        'is_notification_sent'
    ];

    public function trip()
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }
}
