<?php

namespace App\Console\Commands;

use App\Events\CustomerTripCancelledEvent;
use App\Events\DriverTripCancelledEvent;
use Illuminate\Console\Command;
use Modules\TripManagement\Entities\TempTripNotification;
use Modules\TripManagement\Entities\TripRequest;

class CancelPendingTrips extends Command
{
    protected $signature = 'trip-request:cancel';
    protected $description = 'Auto Cancel Pending Trip after certain period';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $activeMinutes = now()->subMinutes(get_cache('trip_request_active_time') ?? 10);
        $reverbConnected = checkReverbConnection();

        TripRequest::with(['customer', 'tempNotifications.user'])
            ->whereIn('current_status', [PENDING])
            ->whereNull('scheduled_at')
            ->where('updated_at', '<', $activeMinutes)
            ->chunk(100, function ($trips) use ($reverbConnected) {
                foreach ($trips as $trip) {
                    $tripType = $trip->type == RIDE_REQUEST ? 'trip' : PARCEL;
                    $push = getNotification(key: $tripType . '_canceled', group: 'customer');
                    sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
                        title: translate(key: $push['title'], locale: $trip->customer?->current_language_key),
                        description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, parcelId: $trip->ref_id, sentTime: pushSentTime($trip->updated_at), locale: $trip->customer?->current_language_key),
                        status: $push['status'],
                        ride_request_id: $trip->id,
                        type: $trip->type,
                        notification_type: $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                        action: $push['action'],
                        user_id: $trip->customer->id
                    );
                    $reverbConnected && DriverTripCancelledEvent::broadcast($trip);
                    if ($trip->tempNotifications->isNotEmpty()) {
                        $notification = [
                            'title' => $push['title'],
                            'description' => $push['description'],
                            'status' => $push['status'],
                            'ride_request_id' => $trip->id,
                            'type' => $trip->type,
                            'notification_type' => $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                            'action' => $push['action'],
                            'replace' => ['tripId' => $trip?->ref_id, 'sentTime' => pushSentTime($trip->updated_at)]
                        ];

                        foreach ($trip->tempNotifications as $tempNotification) {
                            if ($tempNotification?->user->is_active) {
                                sendDeviceNotification(
                                    fcm_token: $tempNotification->user?->fcm_token,
                                    title: translate(key: $notification['title'], locale: $tempNotification->user?->current_language_key),
                                    description: translate(key: $notification['description'], replace: $notification['replace'], locale: $tempNotification->user?->current_language_key),
                                    status: $notification['status'],
                                    image: $notification['image'] ?? null,
                                    ride_request_id: $notification['ride_request_id'] ?? null,
                                    type: $notification['type'] ?? null,
                                    notification_type: $notification['notification_type'] ?? null,
                                    action: $notification['action'] ?? null,
                                    user_id: $tempNotification->user->id ?? null,
                                );
                            }

                            $reverbConnected && CustomerTripCancelledEvent::broadcast($tempNotification->user, $trip);
                        }

                        TempTripNotification::where('trip_request_id', $trip->id)->delete();
                    }
                }
            });

        TripRequest::whereIn('current_status', [PENDING])
            ->whereNull('scheduled_at')
            ->where('updated_at', '<', $activeMinutes)->update([
                'current_status' => 'cancelled',
            ]);
    }
}
