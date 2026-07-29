<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\TripManagement\Entities\LateReturnPenaltyNotification;

class ProcessLateReturnPenaltyNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-late-return-penalty-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch notifications for late return penalty';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lateReturnPenaltyTrips = LateReturnPenaltyNotification::with('trip.driver')
            ->where('sending_notification_at', '<=', now())
            ->where('is_notification_sent', false)
            ->get();

        if ($lateReturnPenaltyTrips)
        {
            $push = getNotification('parcel_return_penalty');
            $notification = [
                'title' => $push['title'],
                'description' => $push['description'],
                'status' => $push['status'],
                'action' => $push['action'],
            ];
            $returnTimeExceedFee = (double)businessConfig('return_fee_for_driver_time_exceed', PARCEL_SETTINGS)?->value ?? 0;
            foreach ($lateReturnPenaltyTrips as $lateReturnPenaltyTrip)
            {
                $trip = $lateReturnPenaltyTrip->trip;
                $driver = $trip->driver;
                if ($driver->fcm_token)
                {
                    sendDeviceNotification(
                        fcm_token: $driver->fcm_token,
                        title: translate(key: $notification['title'], locale: $driver->current_language_key),
                        description: textVariableDataFormat(value: $notification['description'], parcelId: $trip->ref_id, approximateAmount: $returnTimeExceedFee, locale: $driver->current_language_key),
                        status: $notification['status'],
                        ride_request_id: $trip->id ?? null,
                        type: 'parcel',
                        notification_type: 'parcel',
                        action: $notification['action'] ?? null,
                        user_id: $driver->id ?? null,
                    );
                }

                $lateReturnPenaltyTrip->delete();
            }

        }
    }
}
