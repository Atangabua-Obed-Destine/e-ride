<?php

namespace App\Jobs;

use App\Events\CustomerTripRequestEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Entities\TempTripNotification;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;

class ProcessPushNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $radius;
    protected $trip;
    protected $parcelWeight;
    public function __construct( $radius, $trip, $parcelWeight = null)
    {
        $this->radius = $radius;
        $this->trip = $trip;
        $this->parcelWeight = $parcelWeight;
    }


    public function handle(): void
    {
        try {
            $tripRequestService = app()->make(TripRequestServiceInterface::class);

            $find_drivers = $tripRequestService->findNearestDrivers(
                latitude: $this->trip->coordinate->pickup_coordinates->latitude,
                longitude: $this->trip->coordinate->pickup_coordinates->longitude,
                zoneId: $this->trip->zone_id,
                radius: $this->radius,
                vehicleCategoryId: $this->trip->vehicle_category_id,
                requestType: $this->trip->type,
                rideRequestType: $this->trip->ride_request_type,
                parcelWeight: $this->parcelWeight,
                femaleDriverOnly: (bool)($this->trip->is_female_driver_requested ?? false)
            );
            $reverbConnected = checkReverbConnection();

            if (empty($find_drivers)) {
                return;
            }

            $requestType = $this->trip->type == PARCEL ? 'parcel_request' : (
            $this->trip->ride_request_type == SCHEDULED ? 'schedule_trip_request' : 'ride_request'
            );
            $push = getNotification('new_' . $requestType);
            $notification = [
                'title' => $push['title'],
                'description' => $push['description'],
                'status' => $push['status'],
                'ride_request_id' => $this->trip->id,
                'type' => $this->trip->type,
                'notification_type' => $this->trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                'action' => $push['action'],
                'replace' => [
                    'tripId' => $this->trip->ref_id,
                    'parcelId' => $this->trip->parcel_id,
                    'approximateAmount' => getCurrencyFormat($this->trip->estimated_fare),
                    'dropOffLocation' => $this->trip->coordinate->destination_address,
                    'pickUpLocation' => $this->trip->coordinate->pickup_address,
                ],
            ];

            $tempNotificationRows = [];
            $deviceNotifications = [];

            foreach ($find_drivers as $data) {
                try {
                    if ($data?->user?->fcm_token && $data?->user?->is_active) {
                        $tempNotificationRows[] = [
                            'user_id' => $data->user->id,
                            'trip_request_id' => $this->trip->id,
                        ];
                        $deviceNotifications[] = [
                            'fcm_token' => $data->user->fcm_token,
                            'title' => translate(key: $notification['title'], locale: $data->user?->current_language_key),
                            'description' => translate(key: $notification['description'], replace: $notification['replace'], locale: $data->user?->current_language_key),
                            'status' => $notification['status'],
                            'image' => $notification['image'] ?? null,
                            'ride_request_id' => $notification['ride_request_id'] ?? null,
                            'type' => $notification['type'] ?? null,
                            'notification_type' => $notification['notification_type'] ?? null,
                            'action' => $notification['action'] ?? null,
                            'user_id' => $data->user->id ?? null,
                        ];
                    }
                    $data?->user && $reverbConnected && CustomerTripRequestEvent::broadcast($data->user, $this->trip);
                } catch (\Throwable $e) {
                    \Log::warning('ProcessPushNotifications: per-driver notify failed', [
                        'trip_id'   => $this->trip->id,
                        'driver_id' => $data?->user?->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            if (!empty($tempNotificationRows)) {
                try {
                    TempTripNotification::insert($tempNotificationRows);
                } catch (\Throwable $e) {
                    \Log::warning('ProcessPushNotifications: temp notification batch insert failed', [
                        'trip_id' => $this->trip->id,
                        'count' => count($tempNotificationRows),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!empty($deviceNotifications)) {
                sendBatchDeviceNotification($deviceNotifications);
            }

            if (!is_null(businessConfig('server_key', NOTIFICATION_SETTINGS))) {
                try {
                    sendTopicNotification(
                        'admin_notification',
                        translate('new_request_notification'),
                        translate('new_request_has_been_placed'),
                        'null',
                        $this->trip->id,
                        $this->trip->type,
                    );
                } catch (\Throwable $e) {
                    \Log::warning('ProcessPushNotifications: admin topic notify failed', [
                        'trip_id' => $this->trip->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('ProcessPushNotifications: job failed', [
                'trip_id' => $this->trip->id ?? null,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }
}
